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
 * Course and system authorization shared by pages and workers.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * Resolve a real course or the system chat.
     */
    public static function context(int $courseid): \context {
        return $courseid ? \context_course::instance($courseid) : \context_system::instance();
    }

    /**
     * Check active access for the given account.
     */
    public static function allowed(int $courseid, \stdClass $user): bool {
        if (isguestuser($user) || empty($user->confirmed) || !empty($user->deleted) || !empty($user->suspended)) {
            return false;
        }
        if (!$courseid) {
            return has_capability('local/skillupscopilot:sitechat', self::context(0), $user);
        }
        $course = get_course($courseid);
        $context = self::context($courseid);
        return (int)$course->id !== SITEID &&
            ($course->visible || has_capability('moodle/course:viewhiddencourses', $context, $user)) &&
            has_capability('local/skillupscopilot:use', $context, $user) &&
            (is_enrolled($context, $user, '', true) || has_capability('moodle/course:view', $context, $user));
    }

    /**
     * Enforce the plugin's account rules after Moodle login.
     */
    public static function require_chat(int $courseid): \context {
        global $USER;
        $context = self::context($courseid);
        require_capability($courseid ? 'local/skillupscopilot:use' : 'local/skillupscopilot:sitechat', $context);
        if (!self::allowed($courseid, $USER)) {
            throw new \moodle_exception('accessdenied', 'local_skillupscopilot');
        }
        return $context;
    }

    /**
     * Available generation modes for this account.
     */
    public static function modes(int $courseid, \stdClass $user): array {
        $modes = $courseid ? ['chat', 'explain', 'summary', 'guide', 'resource'] : ['chat', 'admin'];
        if ($courseid && has_capability('local/skillupscopilot:author', self::context($courseid), $user)) {
            array_push($modes, 'activity', 'questions');
        }
        return $modes;
    }
}
