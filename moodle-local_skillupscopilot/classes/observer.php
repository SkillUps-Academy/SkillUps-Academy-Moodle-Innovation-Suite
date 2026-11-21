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

namespace local_skillupscopilot;

/**
 * Moodle lifecycle observers.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Remove data that belonged to a deleted course.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $scope = ['courseid' => $event->courseid];
        chat::delete($scope);
        $DB->delete_records('local_skillupscopilot_source', $scope);
        $DB->delete_records('local_skillupscopilot_course', $scope);
        $DB->delete_records('local_skillupscopilot_meter', $scope);
        // Moodle removes file areas when it deletes the course context.
    }
}
