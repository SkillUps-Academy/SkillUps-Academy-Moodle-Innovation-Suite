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

namespace local_skillupsflow\condition;

use local_skillupsflow\security;

/**
 * Built-in AND/OR predicates. Missing targets evaluate false.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class predicates implements \local_skillupsflow\contract\condition {
    /**
     * Evaluate the selected predicates over accessible Moodle records.
     */
    public function evaluate(array $node, \stdClass $run, \stdClass $actor): bool {
        global $DB;
        [$userid, $courseid] = security::targets($node, $run);
        $user = null;
        $course = null;
        $context = null;
        if (array_intersect($node['tests'], ['hasuser', 'activeuser', 'enrolled']) && $userid) {
            require_capability('moodle/user:viewdetails', \context_user::instance($userid, IGNORE_MISSING) ?:
                \context_system::instance(), $actor);
            $user = \core_user::get_user($userid, 'id,confirmed,suspended,deleted');
            if ($user && (!empty($user->deleted) || isguestuser($user))) {
                $user = null;
            }
        }
        if (array_intersect($node['tests'], ['hascourse', 'visiblecourse', 'enrolled']) && $courseid && $courseid !== SITEID) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if ($context) {
                require_capability('moodle/course:view', $context, $actor);
                $course = $DB->get_record('course', ['id' => $courseid], 'id,visible');
            }
        }
        $matches = [];
        foreach ($node['tests'] as $test) {
            $matches[] = match ($test) {
                'hasuser' => (bool)$user, 'activeuser' => $user && $user->confirmed && !$user->suspended,
                'hascourse' => (bool)$course, 'visiblecourse' => $course && (bool)$course->visible,
                'enrolled' => $user && $course && is_enrolled($context, $userid, '', true),
            };
        }
        return $node['operator'] === 'and' ? !in_array(false, $matches, true) : in_array(true, $matches, true);
    }
}
