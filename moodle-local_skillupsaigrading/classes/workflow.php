<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_skillupsaigrading;

use local_skillupsaigrading\activity\adapter;
use local_skillupsaigrading\activity\assign;
use local_skillupsaigrading\ai\registry;

/**
 * Manage submission-bound reviews, provider calls and an immutable decision trail.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow {
    /** Review records. */
    public const JOB = access::C . '_job';
    /** Human review decisions. */
    public const DECISION = access::C . '_decision';

    /**
     * Read an activity-scoped job, then enforce target authorization.
     */
    public static function read(adapter $activity, int $id): \stdClass {
        global $DB;
        $job = $DB->get_record(self::JOB, ['id' => $id, 'cmid' => $activity->module()->id]);
        if (!$job || !$activity->visible($job->studentid)) {
            access::fail('accessdenied');
        }
        return $job;
    }

    /**
     * Test source, rubric and attempt against the saved review snapshot.
     */
    public static function current(adapter $activity, \stdClass $job): bool {
        try {
            [$submission, $signature] = $activity->source($job->studentid, $job->sourceid);
            return (int)$submission->id === (int)$job->submissionid &&
                (int)$submission->attemptnumber === (int)$job->attempt &&
                hash_equals($job->signature, $signature) &&
                hash_equals($job->rubrichash, hash('sha256', evaluation::json($activity->rubric())));
        } catch (\moodle_exception) {
            return false;
        }
    }

    /**
     * Capture a single deduplicated review request and queue AI work.
     */
    public static function create(
        adapter $activity,
        int $studentid,
        int $sourceid,
        string $nonce,
        string $signature,
        string $rubrichash,
        string $excerpt,
        string $provider,
        bool $consent
    ): int {
        global $DB, $USER;
        require_capability(access::CAP . ':review', $activity->context());
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $nonce)) {
            access::fail('invalidexcerpt');
        }
        $local = $provider === 'local';
        $config = null;
        if (!$local) {
            require_capability(access::CAP . ':useai', $activity->context());
            $config = registry::config($provider);
            if (!$config || !$consent) {
                access::fail('provideroff');
            }
        }
        $lock = access::lock($activity->module()->id);
        try {
            $existing = $DB->get_record(self::JOB, ['cmid' => $activity->module()->id, 'nonce' => $nonce]);
            if ($existing) {
                if (
                    (int)$existing->userid !== (int)$USER->id || (int)$existing->studentid !== $studentid ||
                        !$activity->visible($studentid)
                ) {
                    access::fail('accessdenied');
                }
                return (int)$existing->id;
            }
            $since = time() - DAYSECS;
            $limit = max(1, min(500, (int)(get_config(access::C, 'dailylimit') ?: 50)));
            $used = $DB->count_records_select(self::JOB, 'cmid = ? AND timecreated >= ?', [$activity->module()->id, $since]);
            if ($used >= $limit) {
                access::fail('ratelimit');
            }
            [$submission, $current, $text] = $activity->extract($studentid, $sourceid);
            if (!hash_equals($current, $signature)) {
                access::fail('stalesnapshot');
            }
            $subject = \core_user::get_user($studentid, '*', MUST_EXIST);
            $selection = evaluation::select($excerpt, evaluation::redact($text, $subject));
            $rubric = $activity->rubric();
            $encoded = evaluation::json($rubric);
            if (!hash_equals(hash('sha256', $encoded), $rubrichash)) {
                access::fail('stalesnapshot');
            }
            $worksheet = $local ? evaluation::worksheet($rubric, $selection) : null;
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record(self::JOB, (object)[
                'cmid' => $activity->module()->id, 'courseid' => $activity->course()->id, 'studentid' => $studentid,
                'userid' => $USER->id, 'submissionid' => $submission->id, 'attempt' => $submission->attemptnumber,
                'sourceid' => $sourceid, 'signature' => $current, 'nonce' => $nonce,
                'rubrichash' => hash('sha256', $encoded), 'rubric' => $encoded, 'excerpt' => $local ? null : $selection,
                'provider' => $provider, 'providerrev' => $config['revision'] ?? '', 'model' => $config['model'] ?? '',
                'modelversion' => $config['modelversion'] ?? '', 'state' => $local ? 'ready' : 'queued',
                'reviewstate' => 'pending', 'timecreated' => time(), 'timemodified' => time(),
                'result' => $local ? evaluation::json($worksheet) : null, 'evaluatedat' => $local ? time() : 0,
            ]);
            if (!$local) {
                $task = new \local_skillupsaigrading\task\evaluate();
                $task->set_custom_data(['id' => $id, 'cmid' => $activity->module()->id]);
                \core\task\manager::queue_adhoc_task($task);
            }
            self::audit($activity, $id, 'requested');
            $transaction->allow_commit();
            return (int)$id;
        } finally {
            $lock->release();
        }
    }

    /**
     * Run the asynchronous provider request as the original requesting teacher.
     */
    public static function run(int $cmid, int $id): void {
        global $DB, $USER;
        $lock = access::lock($cmid, 10);
        $original = $USER;
        try {
            $job = $DB->get_record(self::JOB, ['id' => $id, 'cmid' => $cmid, 'state' => 'queued']);
            if (!$job) {
                return;
            }
            $state = 'failed';
            $result = null;
            $suggested = null;
            $reported = null;
            try {
                \core\session\manager::set_user(access::active_user($job->userid));
                $activity = new assign($cmid, 'useai');
                require_capability(access::CAP . ':review', $activity->context());
                $config = registry::config($job->provider);
                if ($config && hash_equals($job->providerrev, $config['revision'])) {
                    if (!self::current($activity, $job)) {
                        $state = 'stale';
                    } else {
                        $DB->update_record(self::JOB, ['id' => $id, 'state' => 'working', 'timemodified' => time()]);
                        $rubric = json_decode($job->rubric, true, 20, JSON_THROW_ON_ERROR);
                        $messages = evaluation::messages($rubric, $job->excerpt);
                        $response = registry::connector($job->provider)->generate($messages, $config);
                        $reported = $response['reportedmodel'];
                        $result = evaluation::validate($response['text'], $rubric, $job->excerpt);
                        if (self::current($activity, $job)) {
                            $suggested = $result['percent'];
                            $state = 'ready';
                        } else {
                            $result = null;
                            $state = 'stale';
                        }
                    }
                }
            } catch (\Exception) {
                // Neither a failed response nor the evidence is written to cron logs.
                $result = null;
                $suggested = null;
                $state = 'failed';
            }
            $DB->update_record(self::JOB, [
                'id' => $id, 'excerpt' => null, 'result' => $result === null ? null : evaluation::json($result),
                'evaluatedat' => $state === 'ready' ? time() : 0,
                'reportedmodel' => $reported, 'suggested' => $suggested, 'state' => $state, 'timemodified' => time(),
            ]);
            if (isset($activity)) {
                self::audit($activity, $id, $state);
            }
        } finally {
            \core\session\manager::set_user($original);
            $lock->release();
        }
    }

    /**
     * Save explicit human review or a read-only snapshot of the native grade.
     */
    public static function decide(adapter $activity, int $id, int $previous, string $action, string $feedback): void {
        global $DB, $USER;
        require_capability(access::CAP . ':review', $activity->context());
        if (!in_array($action, ['edit', 'approve', 'reject', 'compare'], true) || \core_text::strlen($feedback) > 10000) {
            access::fail('invalidreview');
        }
        $lock = access::lock($activity->module()->id);
        try {
            $job = self::read($activity, $id);
            if ($job->state !== 'ready' || (int)$job->latestdecision !== $previous || !self::current($activity, $job)) {
                access::fail('stalesnapshot');
            }
            if ($action === 'compare') {
                if ($job->suggested === null) {
                    access::fail('unavailablegrade');
                }
                $human = $activity->human_percent($job->studentid, $activity->submission($job->studentid));
                if ($human === null) {
                    access::fail('unavailablegrade');
                }
                $job->human = $human;
                $job->humanat = time();
                $feedback = sprintf('%.5F', $human);
            } else if ($action !== 'edit') {
                $job->reviewstate = $action === 'approve' ? 'approved' : 'rejected';
            } else {
                $job->reviewstate = 'pending';
            }
            $transaction = $DB->start_delegated_transaction();
            $job->latestdecision = $DB->insert_record(self::DECISION, [
                'jobid' => $id, 'userid' => $USER->id, 'action' => $action,
                'feedback' => clean_param($feedback, PARAM_TEXT), 'timecreated' => time(),
            ]);
            $job->timemodified = time();
            $DB->update_record(self::JOB, $job);
            self::audit($activity, $id, $action);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove a review and its decisions in a single delegated transaction.
     */
    public static function erase(int $id): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::DECISION, ['jobid' => $id]);
        $DB->delete_records(self::JOB, ['id' => $id]);
        $transaction->allow_commit();
    }

    /**
     * Emit a Moodle event without evidence, rubric text, output or feedback.
     */
    private static function audit(adapter $activity, int $id, string $action): void {
        \local_skillupsaigrading\event\review_updated::create([
            'objectid' => $id, 'context' => $activity->context(), 'other' => ['action' => $action],
        ])->trigger();
    }
}
