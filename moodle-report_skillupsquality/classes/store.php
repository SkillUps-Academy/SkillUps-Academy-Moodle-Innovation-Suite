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

namespace report_skillupsquality;

use core\lock\lock_config;
use core\task\manager;
use report_skillupsquality\task\inspect_course;

/**
 * Persist authorized queued work, results and course remediation plans.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** Audit table. */
    public const RUN = 'report_skillupsquality_run';
    /** Per-audit evidence table. */
    public const FINDING = 'report_skillupsquality_finding';
    /** Per-course correction tracking table. */
    public const PLAN = 'report_skillupsquality_plan';
    /** Operator-controlled states. */
    public const PLANSTATES = ['planned', 'working', 'addressed', 'accepted'];

    /**
     * Queue course audits independently within a shared submission.
     */
    public static function enqueue(array $courses): array {
        global $USER;
        $policy = catalog::policy();
        if (!$policy['rules']) {
            throw new \moodle_exception('norules', scope::COMPONENT);
        }
        if (!$courses) {
            throw new \moodle_exception('nocourses', scope::COMPONENT);
        }
        $batch = bin2hex(random_bytes(16));
        $ids = [];
        foreach ($courses as $course) {
            if (!scope::allowed($course, 'run', (int)$USER->id)) {
                throw new \moodle_exception('scopefailure', scope::COMPONENT);
            }
        }
        foreach ($courses as $course) {
            $id = self::queue_course($course, (int)$USER->id, $batch, $policy);
            if ($id) {
                $ids[] = $id;
            }
        }
        return ['batch' => $batch, 'ids' => $ids, 'skipped' => count($courses) - count($ids)];
    }

    /**
     * Atomically create one run and its native queue entry.
     */
    private static function queue_course(\stdClass $course, int $userid, string $batch, array $policy): int {
        global $DB;
        $lock = lock_config::get_lock_factory(scope::COMPONENT)->get_lock('course_' . $course->id, 0);
        if (!$lock) {
            return 0;
        }
        try {
            $now = time();
            $active = $DB->record_exists_select(
                self::RUN,
                "courseid = :course AND (state IN ('queued', 'running') OR timecreated > :limit)",
                ['course' => $course->id, 'limit' => $now - MINSECS],
            );
            if ($active) {
                return 0;
            }
            $tx = $DB->start_delegated_transaction();
            $id = $DB->insert_record(self::RUN, [
                'courseid' => $course->id, 'userid' => $userid, 'batch' => $batch, 'state' => 'queued',
                'policy' => json_encode($policy, JSON_THROW_ON_ERROR), 'timecreated' => $now, 'timemodified' => $now,
            ]);
            self::queue_task($id);
            self::event('queued', $course->id, $id);
            $tx->allow_commit();
            return $id;
        } finally {
            $lock->release();
        }
    }

    /**
     * Ask Moodle to queue an audit idempotently.
     */
    public static function queue_task(int $runid): void {
        $task = new inspect_course();
        $task->set_custom_data(['runid' => $runid]);
        manager::queue_adhoc_task($task, true);
    }

    /**
     * Publish results in one transaction after rechecking the requesting operator.
     */
    public static function execute(int $runid): void {
        global $DB;
        $run = $DB->get_record(self::RUN, ['id' => $runid]);
        if (!$run || !in_array($run->state, ['queued', 'running'], true)) {
            return;
        }
        $lock = lock_config::get_lock_factory(scope::COMPONENT)->get_lock('course_' . $run->courseid, 5);
        if (!$lock) {
            throw new \moodle_exception('busy', scope::COMPONENT);
        }
        try {
            $run = $DB->get_record(self::RUN, ['id' => $runid]);
            if (!$run || !in_array($run->state, ['queued', 'running'], true)) {
                return;
            }
            $course = $DB->get_record('course', ['id' => $run->courseid]);
            $actor = $run->userid ? \core_user::get_user($run->userid) : null;
            if (!$course || !$actor || $actor->deleted || $actor->suspended || !scope::allowed($course, 'run', (int)$actor->id)) {
                self::change($runid, 'denied');
                return;
            }
            self::change($runid, 'running');
            try {
                $result = engine::audit($course, self::decode($run->policy));
                if (!scope::allowed($course, 'run', (int)$actor->id)) {
                    self::change($runid, 'denied');
                    return;
                }
                self::publish($run, $result);
            } catch (\Throwable $failure) {
                self::change($runid, 'failed');
                self::event('failed', (int)$run->courseid, $runid);
                throw $failure;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Replace any incomplete artifacts and publish the complete revision.
     */
    private static function publish(\stdClass $run, array $result): void {
        global $DB;
        $policy = self::decode($run->policy) + ['unverified' => $result['unverified']];
        $tx = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::FINDING, ['runid' => $run->id]);
            foreach (array_chunk($result['findings'], 250) as $slice) {
                $rows = [];
                foreach ($slice as $finding) {
                    $rows[] = $finding + ['runid' => $run->id];
                }
                $DB->insert_records(self::FINDING, $rows);
            }
            $DB->update_record(self::RUN, [
                'id' => $run->id, 'state' => 'done', 'timemodified' => time(), 'score' => $result['score'],
                'objective' => $result['objective'], 'advisory' => $result['advisory'],
                'policy' => json_encode($policy, JSON_THROW_ON_ERROR),
            ]);
            self::event('completed', (int)$run->courseid, (int)$run->id);
            $tx->allow_commit();
        } catch (\Exception $exception) {
            $tx->rollback($exception);
        }
    }

    /**
     * Update the job state without editing the course.
     */
    public static function change(int $id, string $state): void {
        global $DB;
        $DB->update_record(self::RUN, ['id' => $id, 'state' => $state, 'timemodified' => time()]);
    }

    /**
     * Resolve a run only in an authorized course context.
     */
    public static function run(int $id, string $action = 'view'): \stdClass {
        global $DB;
        $run = $DB->get_record(self::RUN, ['id' => $id]);
        if (!$run) {
            throw new \moodle_exception('scopefailure', scope::COMPONENT);
        }
        scope::course((int)$run->courseid, $action);
        return $run;
    }

    /**
     * Resolve a finding through its authorized parent audit.
     */
    public static function finding(int $id, string $action = 'view'): array {
        global $DB;
        $finding = $DB->get_record(self::FINDING, ['id' => $id]);
        if (!$finding) {
            throw new \moodle_exception('scopefailure', scope::COMPONENT);
        }
        $run = self::run((int)$finding->runid, $action);
        if ($run->state !== 'done') {
            throw new \moodle_exception('scopefailure', scope::COMPONENT);
        }
        return [$run, $finding];
    }

    /**
     * Update a plan scoped to the finding's verified course.
     */
    public static function save_plan(int $findingid, string $state, string $notes, int $due, int $planid = 0): void {
        global $DB, $USER;
        if ($planid) {
            $finding = self::plan($planid, 'plan');
            $courseid = (int)$finding->courseid;
        } else {
            [$run, $finding] = self::finding($findingid, 'plan');
            $courseid = (int)$run->courseid;
        }
        if (!in_array($state, self::PLANSTATES, true) || \core_text::strlen($notes) > 4000 || $due < 0) {
            throw new \moodle_exception('invalidplan', scope::COMPONENT);
        }
        $lock = lock_config::get_lock_factory(scope::COMPONENT)->get_lock('plan_' . $courseid, 5);
        if (!$lock) {
            throw new \moodle_exception('busy', scope::COMPONENT);
        }
        try {
            $values = ['courseid' => $courseid, 'fingerprint' => $finding->fingerprint];
            $existing = $DB->get_record(self::PLAN, $values);
            $values += [
                'userid' => $USER->id, 'rulecode' => $finding->rulecode, 'targettype' => $finding->targettype,
                'targetid' => $finding->targetid, 'state' => $state, 'notes' => $notes, 'due' => $due, 'timemodified' => time(),
            ];
            if ($existing) {
                $values['id'] = $existing->id;
                $DB->update_record(self::PLAN, $values);
                $saved = (int)$existing->id;
            } else {
                $saved = $DB->insert_record(self::PLAN, $values);
            }
            self::event('planned', $courseid, isset($run) ? (int)$run->id : 0, $saved);
        } finally {
            $lock->release();
        }
    }

    /**
     * Verify scope for a persistent plan.
     */
    public static function plan(int $id, string $action = 'view'): \stdClass {
        global $DB;
        $row = $DB->get_record(self::PLAN, ['id' => $id]);
        if (!$row) {
            throw new \moodle_exception('scopefailure', scope::COMPONENT);
        }
        scope::course((int)$row->courseid, $action);
        return $row;
    }

    /**
     * Trigger a Moodle audit event with stable operational metadata.
     */
    public static function event(string $operation, int $courseid, int $runid, int $planid = 0): void {
        \report_skillupsquality\event\audit_operation::create([
            'context' => \context_course::instance($courseid),
            'other' => ['operation' => $operation, 'runid' => $runid, 'planid' => $planid],
        ])->trigger();
    }

    /**
     * Decode internally generated snapshots only.
     */
    public static function decode(string $json): array {
        return json_decode($json, true, 12, JSON_THROW_ON_ERROR);
    }
}
