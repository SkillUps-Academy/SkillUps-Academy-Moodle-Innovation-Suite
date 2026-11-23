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

namespace local_skillupscertify;

use core\lock\lock_config;
use core\task\manager;
use moodle_exception;
use stdClass;

/**
 * Queue course-scoped issuance batches and persist independent participant outcomes.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batches {
    /** @var int Maximum recipients in one batch. */
    public const LIMIT = 200;

    /**
     * Collect the selected group's active learners and enqueue one durable background operation.
     *
     * @param stdClass $course Course
     * @param int $templateid Template
     * @param int $groupid Selected group
     * @return int Batch ID
     */
    public static function queue(stdClass $course, int $templateid, int $groupid): int {
        global $DB, $USER;
        $context = \context_course::instance($course->id);
        require_capability(access::CAP . 'issue', $context);
        $groupid = access::group($course, $groupid);
        $template = store::scoped(store::TEMPLATE, $course->id, $templateid);
        if (!$template->enabled) {
            access::deny();
        }
        $revision = store::revision($template);
        [$enrolled, $params] = get_enrolled_sql($context, 'moodle/course:isincompletionreports', $groupid, true);
        $people = $DB->get_fieldset_sql(
            "SELECT u.id FROM {user} u JOIN ($enrolled) e ON u.id = e.id
              WHERE u.suspended = 0 AND u.deleted = 0 AND u.confirmed = 1 ORDER BY u.id",
            $params,
            0,
            self::LIMIT + 1
        );
        if (count($people) > self::LIMIT || !$people) {
            throw new moodle_exception('batchsize', access::C, '', self::LIMIT);
        }
        return store::atomic("batches:$course->id", static function () use (
            $DB,
            $USER,
            $course,
            $templateid,
            $groupid,
            $revision,
            $people
        ): int {
            $where = 'courseid = :courseid AND state = :state';
            if ($DB->count_records_select(store::BATCH, $where, ['courseid' => $course->id, 'state' => 'queued']) >= 3) {
                throw new moodle_exception('batchpending', access::C);
            }
            $now = time();
            $batch = $DB->insert_record(store::BATCH, [
                'courseid' => $course->id, 'templateid' => $templateid, 'revisionid' => $revision->id,
                'userid' => $USER->id, 'groupid' => $groupid, 'state' => 'queued',
                'timecreated' => $now, 'timemodified' => $now,
            ]);
            $rows = [];
            foreach ($people as $userid) {
                $rows[] = [
                    'batchid' => $batch, 'courseid' => $course->id, 'userid' => $userid,
                    'state' => 'queued', 'timeupdated' => $now,
                ];
            }
            $DB->insert_records(store::ITEM, $rows);
            $task = new task\issue_batch();
            $task->set_custom_data(['batchid' => $batch]);
            $task->set_userid($USER->id);
            manager::queue_adhoc_task($task, true);
            store::audit($course->id, 'batch', $batch);
            return (int)$batch;
        });
    }

    /**
     * Process pending rows under the original actor, rechecking capabilities each time.
     *
     * @param int $batchid Batch request
     */
    public static function process(int $batchid): void {
        $lock = lock_config::get_lock_factory(access::C)->get_lock("batch:$batchid", 5);
        if (!$lock) {
            throw new moodle_exception('busy', access::C);
        }
        try {
            self::work($batchid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Issue or skip outstanding recipients while the batch lock is held.
     *
     * @param int $batchid Batch request
     */
    private static function work(int $batchid): void {
        global $DB, $USER;
        $batch = $DB->get_record(store::BATCH, ['id' => $batchid, 'state' => 'queued']);
        if (!$batch || $batch->userid != $USER->id) {
            return;
        }
        $course = get_course($batch->courseid);
        $context = \context_course::instance($course->id);
        if (
            !has_capability(access::CAP . 'issue', $context) ||
            ($batch->groupid && !isset(access::groups($course)[$batch->groupid])) ||
            (!$batch->groupid && access::limited($course))
        ) {
            $DB->update_record(store::BATCH, ['id' => $batchid, 'state' => 'cancelled', 'timemodified' => time()]);
            store::audit($course->id, 'batchcancel', $batchid);
            return;
        }
        $rows = $DB->get_records(store::ITEM, ['batchid' => $batchid, 'state' => 'queued'], 'id ASC');
        foreach ($rows as $row) {
            if (!$DB->record_exists(store::BATCH, ['id' => $batchid, 'state' => 'queued'])) {
                return;
            }
            $result = access::sees($course, $row->userid, $batch->groupid)
                ? issuer::issue($course, $batch->templateid, $row->userid, $batch->groupid, $batch->revisionid, 0, $row->id)
                : null;
            $state = $result ? ($result[1] ? 'issued' : 'existing') : 'skipped';
            $DB->update_record(store::ITEM, [
                'id' => $row->id, 'state' => $state, 'issueid' => $result[0]->id ?? 0, 'timeupdated' => time(),
            ]);
        }
        $DB->update_record(store::BATCH, ['id' => $batchid, 'state' => 'finished', 'timemodified' => time()]);
        if ($DB->record_exists(store::BATCH, ['id' => $batchid, 'state' => 'finished'])) {
            store::audit($course->id, 'batchfinish', $batchid);
        }
    }

    /**
     * Determine whether a batch belongs to the reporting scope.
     *
     * @param stdClass $course Course
     * @param stdClass $batch Batch record
     * @return bool
     */
    public static function visible(stdClass $course, stdClass $batch): bool {
        $groups = access::groups($course);
        return $batch->groupid ? isset($groups[$batch->groupid]) : !access::limited($course);
    }
}
