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

namespace local_skillupsgamification;

use core\task\manager;
use core\event\course_completed;
use core\event\course_deleted;
use core\event\course_module_completion_updated;

/**
 * Dispatch Moodle completion events to the award verifier.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Queue a persisted non-overridden successful activity event.
     *
     * @param course_module_completion_updated $event Completion event
     */
    public static function activity(course_module_completion_updated $event): void {
        global $CFG;
        if (!store::configuration((int)$event->courseid)->enabled) {
            return;
        }
        require_once($CFG->libdir . '/completionlib.php');
        if (
            !empty($event->other['overrideby']) ||
            !in_array((int)($event->other['completionstate'] ?? 0), [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)
        ) {
            return;
        }
        $record = $event->get_record_snapshot('course_modules_completion', $event->objectid);
        if ($record && $record->userid == $event->relateduserid && $record->coursemoduleid == $event->contextinstanceid) {
            self::queue(
                (int)$event->courseid,
                (int)$event->relateduserid,
                'activity',
                (int)$event->contextinstanceid,
                (int)$event->objectid,
                (int)$record->timemodified
            );
        }
    }

    /**
     * Queue the authoritative native course completion record.
     *
     * @param course_completed $event Course event
     */
    public static function course(course_completed $event): void {
        if (!store::configuration((int)$event->courseid)->enabled) {
            return;
        }
        $record = $event->get_record_snapshot('course_completions', $event->objectid);
        if ($record && $record->userid == $event->relateduserid && $record->course == $event->courseid && $record->timecompleted) {
            self::queue(
                (int)$event->courseid,
                (int)$event->relateduserid,
                'course',
                (int)$event->courseid,
                (int)$event->objectid,
                (int)$record->timecompleted
            );
        }
    }

    /**
     * Remove the deleted course's local data.
     *
     * @param course_deleted $event Course deletion
     */
    public static function deleted(course_deleted $event): void {
        store::delete_course((int)$event->objectid);
    }

    /**
     * Schedule Moodle's native background job without copying learner content.
     *
     * @param int $courseid Course ID
     * @param int $userid User whose completion is being checked
     * @param string $kind Source kind
     * @param int $sourceid Source identity
     * @param int $recordid Native record identity
     * @param int $occurred Timestamp
     */
    private static function queue(int $courseid, int $userid, string $kind, int $sourceid, int $recordid, int $occurred): void {
        if ($courseid <= 0 || $userid <= 0 || $recordid <= 0 || $occurred <= 0) {
            return;
        }
        $task = new task\award_completion();
        $task->set_userid($userid);
        $task->set_custom_data(compact('courseid', 'kind', 'sourceid', 'recordid', 'occurred'));
        manager::queue_adhoc_task($task, true);
    }
}
