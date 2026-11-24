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

namespace local_skillupsscheduler;

/**
 * Remove orphan schedule and audit records when Moodle deletes a course.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Course deleted.
     *
     * @param \core\event\course_deleted $event Event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $courseid = $event->objectid;
        store::atomic(static function () use ($DB, $courseid): void {
            $slots = $DB->get_fieldset_select(store::SLOT, 'id', 'courseid = ?', [$courseid]);
            foreach (array_chunk($slots, 400) as $chunk) {
                $DB->delete_records_list(store::QUEUE, 'slotid', $chunk);
            }
            $DB->delete_records(store::AUDIT, ['courseid' => $courseid]);
            $DB->delete_records(store::SLOT, ['courseid' => $courseid]);
        });
    }
}
