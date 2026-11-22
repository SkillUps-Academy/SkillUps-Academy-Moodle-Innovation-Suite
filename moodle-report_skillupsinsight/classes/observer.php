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

namespace report_skillupsinsight;

/**
 * Delete snapshot rows whose course has been removed.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Cleanup when Moodle deletes a course.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records(warehouse::DATA, ['courseid' => $event->objectid]);
        \cache::make(util::C, 'rollups')->purge();
    }

    /**
     * Remove private report definitions and watches owned by a deleted account.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        \report_skillupsinsight\privacy\provider::erase((int)$event->objectid);
    }
}
