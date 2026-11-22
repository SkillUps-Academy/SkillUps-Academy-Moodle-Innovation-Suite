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

/**
 * Remove derived reviews when the source activity or course is deleted.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Clean up a deleted module.
     */
    public static function module_deleted(\core\event\course_module_deleted $event): void {
        self::purge(['cmid' => $event->objectid]);
    }

    /**
     * Clean up any remaining reviews for a deleted course.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        self::purge(['courseid' => $event->objectid]);
    }

    /**
     * Delete job records and dependent decisions.
     */
    private static function purge(array $conditions): void {
        global $DB;
        foreach ($DB->get_records(workflow::JOB, $conditions, 'id ASC', 'id, cmid') as $row) {
            $lock = access::lock($row->cmid, 60);
            try {
                workflow::erase((int)$row->id);
            } finally {
                $lock->release();
            }
        }
    }
}
