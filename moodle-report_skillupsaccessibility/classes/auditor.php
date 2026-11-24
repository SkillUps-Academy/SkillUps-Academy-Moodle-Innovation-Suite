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

namespace report_skillupsaccessibility;

use stdClass;

/**
 * Queue and run course audits while rechecking the original actor.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class auditor {
    /** @var int Maximum sources handled by one course audit. */
    public const LIMIT = 400;

    /**
     * Enqueue one authorized course audit and suppress concurrent duplicates.
     *
     * @param stdClass $course Moodle course
     * @param string $origin Request origin
     * @return int Job ID
     */
    public static function enqueue(stdClass $course, string $origin = 'manual'): int {
        global $DB, $USER;
        if (!scope::allowed($course, 'scan') || !in_array($origin, ['manual', 'bulk', 'scheduled'], true)) {
            scope::fail();
        }
        return store::lock((int)$course->id, static function () use ($DB, $USER, $course, $origin): int {
            return store::transaction(static function () use ($DB, $USER, $course, $origin): int {
                $jobs = $DB->get_records_select(
                    store::JOB,
                    'courseid = :course AND (status = :queued OR status = :running)',
                    ['course' => $course->id, 'queued' => 'queued', 'running' => 'running'],
                    'id DESC',
                );
                foreach ($jobs as $job) {
                    if ($job->created > time() - DAYSECS) {
                        return (int)$job->id;
                    }
                    $DB->update_record(store::JOB, (object)[
                        'id' => $job->id, 'status' => 'failed', 'finished' => time(), 'errorcode' => 'interrupted',
                    ]);
                }
                $id = $DB->insert_record(store::JOB, (object)[
                    'courseid' => $course->id, 'actorid' => $USER->id, 'origin' => $origin, 'status' => 'queued',
                    'created' => time(), 'errorcode' => '',
                ]);
                $task = new \report_skillupsaccessibility\task\scan();
                $task->set_custom_data(['jobid' => $id]);
                \core\task\manager::queue_adhoc_task($task);
                self::event((int)$id, (int)$course->id, 'queued');
                return (int)$id;
            });
        });
    }

    /**
     * Process a queued job under its original identity and report terminal state.
     *
     * @param int $id Job ID
     */
    public static function run(int $id): void {
        global $DB, $USER;
        $job = $DB->get_record(store::JOB, ['id' => $id]);
        if (!$job || !in_array($job->status, ['queued', 'running'], true)) {
            return;
        }
        store::lock((int)$job->courseid, static function () use ($DB, $id, $USER): void {
            $job = $DB->get_record(store::JOB, ['id' => $id]);
            if (!$job || !in_array($job->status, ['queued', 'running'], true)) {
                return;
            }
            $original = $USER;
            $actor = $DB->get_record('user', ['id' => $job->actorid, 'deleted' => 0, 'suspended' => 0]);
            $course = $DB->get_record('course', ['id' => $job->courseid]);
            if (!$actor || !$course || !scope::allowed($course, 'scan', (int)$actor->id)) {
                self::fail_job($id, 'revoked');
                return;
            }
            \core\session\manager::set_user($actor);
            try {
                $DB->set_field(store::JOB, 'status', 'running', ['id' => $id]);
                $changed = 0;
                $reused = 0;
                $partial = 0;
                $plan = [];
                foreach (sources::collect($course) as $source) {
                    $key = $source['sourcekey'];
                    if (isset($plan[$key])) {
                        continue;
                    }
                    if (count($plan) >= self::LIMIT) {
                        $partial = 1;
                        break;
                    }
                    $revision = self::revision($course, $source);
                    $plan[$key] = $revision;
                    $changed += $revision[2] ? 1 : 0;
                    $reused += $revision[2] ? 0 : 1;
                }
                store::transaction(static function () use ($DB, $id, $course, $plan, $changed, $reused, $partial): void {
                    $DB->delete_records(store::HIT, ['jobid' => $id]);
                    foreach ($plan as [$asset, $result]) {
                        $DB->insert_record(store::HIT, (object)['jobid' => $id, 'assetid' => $asset->id, 'revid' => $result->id]);
                        self::sync_findings((int)$asset->id, $result);
                    }
                    $DB->update_record(store::JOB, (object)[
                        'id' => $id, 'status' => 'complete', 'finished' => time(), 'changed' => $changed,
                        'reused' => $reused, 'truncated' => $partial, 'errorcode' => '',
                    ]);
                    self::event($id, (int)$course->id, 'complete');
                });
            } catch (\Throwable $error) {
                self::fail_job($id, 'scanfailed');
                throw $error;
            } finally {
                \core\session\manager::set_user($original);
            }
        });
    }

    /**
     * Load or save an immutable result for a specific content fingerprint.
     *
     * @param stdClass $course Moodle course
     * @param array $source Authorized source
     * @return array Asset, revision and cache miss indicator
     */
    private static function revision(stdClass $course, array $source): array {
        global $DB;
        $asset = $DB->get_record(store::ASSET, ['courseid' => $course->id, 'sourcekey' => $source['sourcekey']]);
        if (!$asset) {
            $asset = (object)array_intersect_key($source, array_flip(['sourcekey', 'cmid', 'sectionid', 'refid', 'kind', 'label']));
            $asset->courseid = $course->id;
            $asset->id = $DB->insert_record(store::ASSET, $asset);
        } else if ($asset->label !== $source['label']) {
            $asset->label = $source['label'];
            $DB->set_field(store::ASSET, 'label', $asset->label, ['id' => $asset->id]);
        }
        $digest = sources::digest($source);
        $cached = $DB->get_record(store::REV, ['assetid' => $asset->id, 'digest' => $digest]);
        if ($cached) {
            return [$asset, $cached, false];
        }
        $result = sources::analyze($source);
        $revision = (object)[
            'assetid' => $asset->id, 'digest' => $digest, 'checked' => (int)$result['checked'], 'reason' => $result['reason'],
            'issues' => json_encode($result['issues'], JSON_THROW_ON_ERROR), 'created' => time(),
        ];
        $revision->id = $DB->insert_record(store::REV, $revision);
        return [$asset, $revision, true];
    }

    /**
     * Reconcile current workflow with a completed analysis for one asset.
     *
     * @param int $assetid Source ID
     * @param stdClass $revision Cached result
     */
    private static function sync_findings(int $assetid, stdClass $revision): void {
        global $DB;
        $existing = $DB->get_records(store::FINDING, ['assetid' => $assetid]);
        $signatures = array_column($existing, null, 'signature');
        foreach (json_decode($revision->issues, true, 10, JSON_THROW_ON_ERROR) as $issue) {
            $signature = self::signature($issue);
            if (isset($signatures[$signature])) {
                $row = $signatures[$signature];
                $row->lastseen = time();
                $row->fact = $issue['fact'];
                if (!$row->present && $row->state === 'corrected') {
                    $row->state = 'pending';
                }
                $row->present = 1;
                $DB->update_record(store::FINDING, $row);
                unset($signatures[$signature]);
            } else {
                $DB->insert_record(store::FINDING, (object)($issue + [
                    'assetid' => $assetid, 'signature' => $signature, 'present' => 1, 'state' => 'pending',
                    'firstseen' => time(), 'lastseen' => time(),
                ]));
            }
        }
        if ($revision->checked) {
            foreach ($signatures as $row) {
                if ($row->present) {
                    $row->present = 0;
                    if ($row->state === 'pending') {
                        $row->state = 'corrected';
                    }
                    $DB->update_record(store::FINDING, $row);
                }
            }
        }
    }

    /**
     * Identify an issue at a given structural location.
     *
     * @param array $issue Rule and location
     * @return string
     */
    public static function signature(array $issue): string {
        return hash('sha256', $issue['ruleid'] . ':' . $issue['location']);
    }

    /**
     * Mark a job as unavailable or failed.
     *
     * @param int $id Job ID
     * @param string $code Failure reason
     */
    private static function fail_job(int $id, string $code): void {
        global $DB;
        $DB->update_record(store::JOB, (object)['id' => $id, 'status' => 'failed', 'finished' => time(), 'errorcode' => $code]);
    }

    /**
     * Emit a minimal Moodle event without any source content.
     *
     * @param int $jobid Job ID
     * @param int $courseid Moodle course
     * @param string $status Job status
     */
    private static function event(int $jobid, int $courseid, string $status): void {
        \report_skillupsaccessibility\event\audit_changed::create([
            'context' => \context_course::instance($courseid), 'objectid' => $jobid, 'other' => ['status' => $status],
        ])->trigger();
    }
}
