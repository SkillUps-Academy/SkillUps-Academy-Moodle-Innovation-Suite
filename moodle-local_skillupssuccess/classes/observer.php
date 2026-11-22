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

namespace local_skillupssuccess;

use local_skillupssuccess\privacy\deletion;
use local_skillupssuccess\privacy\provider;

/**
 * Remove derived academic data on Moodle lifecycle changes.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Purge the deleted course.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        deletion::by_course((int)$event->objectid);
    }

    /**
     * Erase an account from its retained course scopes.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        $userid = (int)$event->objectid;
        foreach (provider::courseids($userid) as $courseid) {
            deletion::by_course((int)$courseid, $userid);
        }
    }
}
