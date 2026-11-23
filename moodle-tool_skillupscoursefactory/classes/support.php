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

namespace tool_skillupscoursefactory;

/**
 * Shared authorization, configuration and locking helpers.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class support {
    /** Moodle component. */
    public const C = 'tool_skillupscoursefactory';
    /** Supported sizes for a submitted batch. */
    public const SIZES = [10, 50, 100, 200, 500];

    /**
     * Translate an interface string.
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Resolve an option from the configured maximum batch size.
     */
    public static function sizes(): array {
        $max = (int)get_config(self::C, 'maxbatch') ?: 50;
        return array_values(array_filter(self::SIZES, static fn($number) => $number <= max(10, $max)));
    }

    /**
     * Resolve the current identity and reject disabled or unconfirmed accounts.
     */
    public static function can_operate(int $userid): bool {
        $user = $userid ? \core_user::get_user($userid) : null;
        return $user && !$user->deleted && !$user->suspended && $user->confirmed && !isguestuser($user)
            && has_capability('tool/skillupscoursefactory:operate', \context_system::instance(), $userid);
    }

    /**
     * Authorize a specific current backup source.
     */
    public static function source(int $id, int $userid): bool {
        global $DB;
        $course = $id && $id !== SITEID ? $DB->get_record('course', ['id' => $id]) : null;
        if (!$course || !\core_course_category::can_view_course_info($course, $userid)) {
            return false;
        }
        $context = \context_course::instance($id);
        return has_capability('moodle/backup:backupcourse', $context, $userid)
            && has_capability('moodle/backup:backuptargetimport', $context, $userid);
    }

    /**
     * Check permission against the active category.
     */
    public static function destination(int $id, int $userid, bool $restore): ?\context_coursecat {
        $category = $id > 0 ? \core_course_category::get($id, IGNORE_MISSING, false, $userid) : null;
        if (!$category) {
            return null;
        }
        $context = $category->get_context();
        return has_capability('moodle/course:create', $context, $userid)
            && has_capability('moodle/course:update', $context, $userid)
            && (!$restore || has_capability('moodle/restore:restorecourse', $context, $userid)) ? $context : null;
    }

    /**
     * Obtain one cluster-safe component lock.
     */
    public static function lock(string $name, int $timeout = 0): ?\core\lock\lock {
        return \core\lock\lock_config::get_lock_factory(self::C)->get_lock($name, $timeout) ?: null;
    }

    /**
     * Serialize a validated payload in a stable encoding.
     */
    public static function json(array $fields): string {
        return json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
