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

namespace tool_skillupsenrollment;

/**
 * Enforce site, course, participant and group boundaries.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class support {
    /** Moodle component. */
    public const C = 'tool_skillupsenrollment';
    /** Institutional limits for a submitted batch. */
    public const SIZES = [100, 500, 1000];
    /** Native Moodle methods exposed by the supported operations. */
    public const METHODS = ['manual', 'self'];

    /**
     * Return a localized string.
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Return available batch limits.
     */
    public static function sizes(): array {
        $max = (int)get_config(self::C, 'maxbatch') ?: 500;
        return array_values(array_filter(self::SIZES, static fn($value) => $value <= max(100, $max)));
    }

    /**
     * Confirm a signed-in active site operator.
     */
    public static function can_operate(int $userid): bool {
        $user = $userid ? \core_user::get_user($userid) : null;
        return $user && !$user->deleted && !$user->suspended && $user->confirmed && !isguestuser($user)
            && has_capability('tool/skillupsenrollment:operate', \context_system::instance(), $userid);
    }

    /**
     * Check access to participant information for the actual course.
     */
    public static function can_report(int $courseid, int $userid, int $targetid = 0): bool {
        global $DB;
        if ($courseid <= SITEID) {
            return false;
        }
        $course = $DB->get_record('course', ['id' => $courseid], 'id,category,visible,groupmode');
        if (!$course || !\core_course_category::can_view_course_info($course, $userid)) {
            return false;
        }
        $context = \context_course::instance($courseid);
        if (!has_capability('moodle/course:viewparticipants', $context, $userid)) {
            return false;
        }
        foreach (self::METHODS as $method) {
            if (
                has_capability('enrol/' . $method . ':manage', $context, $userid)
                    || ($method === 'manual' && has_capability('enrol/manual:enrol', $context, $userid))
            ) {
                // In separate groups, show another person's row only to an operator who shares their group.
                if ($targetid && self::limited_groups($course, $context, $userid)) {
                    return self::share_group($courseid, $userid, $targetid);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Respect native separate-group restrictions for an editing operator.
     */
    public static function limited_groups(\stdClass $course, \context_course $context, int $actor): bool {
        return (int)$course->groupmode === SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context, $actor);
    }

    /**
     * Check shared group membership without assuming the cron user's visibility.
     */
    public static function share_group(int $course, int $actor, int $target): bool {
        global $DB;
        return $DB->record_exists_sql(
            'SELECT 1
               FROM {groups} g
               JOIN {groups_members} a ON a.groupid = g.id AND a.userid = :actor
               JOIN {groups_members} t ON t.groupid = g.id AND t.userid = :target
              WHERE g.courseid = :course',
            ['actor' => $actor, 'target' => $target, 'course' => $course]
        );
    }

    /**
     * Get a portable lock for the current execution stage.
     */
    public static function lock(string $name, int $timeout = 0): ?\core\lock\lock {
        return \core\lock\lock_config::get_lock_factory(self::C)->get_lock($name, $timeout) ?: null;
    }

    /**
     * Encode plugin-owned immutable operational evidence.
     */
    public static function json(array $value): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
