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

namespace local_skillupsquestionstudio;

/**
 * Course scoping and durable author authorization.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * Language component.
     */
    public const C = 'local_skillupsquestionstudio';

    /**
     * Resolve a real course context.
     */
    public static function context(int $id): \context_course {
        if ($id <= 0 || $id === SITEID) {
            throw new \moodle_exception('invalidcourse', self::C);
        }
        return \context_course::instance($id);
    }

    /**
     * Load a project by both identifier and course.
     */
    public static function project(int $id, int $courseid): \stdClass {
        global $DB;
        return $DB->get_record(self::C . '_project', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
    }

    /**
     * Enforce access again just before deferred operations.
     */
    public static function actor(int $id, int $courseid, string $cap = 'manage'): \stdClass {
        $context = self::context($courseid);
        $user = \core_user::get_user($id);
        if (!$user || $user->deleted || $user->suspended || !$user->confirmed || isguestuser($user)) {
            throw new \moodle_exception('accessdenied', self::C);
        }
        require_capability('local/skillupsquestionstudio:' . $cap, $context, $user);
        if (!is_enrolled($context, $user, '', true) && !has_capability('moodle/course:view', $context, $user)) {
            throw new \moodle_exception('accessdenied', self::C);
        }
        $course = get_course($courseid);
        if (!$course->visible) {
            require_capability('moodle/course:viewhiddencourses', $context, $user);
        }
        return $user;
    }

    /**
     * Serialize project state independently of other courses.
     */
    public static function lock(int $projectid, int $seconds = 5): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory(self::C);
        if (!$lock = $factory->get_lock('project_' . $projectid, $seconds)) {
            throw new \moodle_exception('busy', self::C);
        }
        return $lock;
    }

    /**
     * Restrict task and route errors to localized messages.
     */
    public static function fail(string $code): never {
        throw new \moodle_exception($code, self::C);
    }
}
