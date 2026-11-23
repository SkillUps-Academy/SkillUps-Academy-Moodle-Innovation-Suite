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

use stdClass;

/**
 * Native Moodle group membership and team scopes.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class team {
    /**
     * Return visible or trusted groupings in one course, depending on caller permission.
     *
     * @param int $courseid Scoped course
     * @return array
     */
    public static function groupings(int $courseid): array {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        return groups_get_all_groupings($courseid);
    }

    /**
     * Resolve a unique participant team in the configured grouping.
     *
     * @param stdClass $settings Course configuration
     * @param int $userid User ID
     * @return int Group ID or zero
     */
    public static function membership(stdClass $settings, int $userid): int {
        global $CFG;
        if (!$settings->groupingid) {
            return 0;
        }
        require_once($CFG->libdir . '/grouplib.php');
        $groups = groups_get_user_groups($settings->courseid, $userid);
        $matches = $groups[$settings->groupingid] ?? [];
        return count($matches) === 1 ? (int)reset($matches) : 0;
    }
}
