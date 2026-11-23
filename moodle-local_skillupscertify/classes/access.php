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

namespace local_skillupscertify;

use context_course;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Scope certificate operations to course contexts and Moodle groups.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Component. */
    public const C = 'local_skillupscertify';

    /** @var string Capability prefix. */
    public const CAP = 'local/skillupscertify:';

    /**
     * Resolve a real course before applying the page access policy.
     *
     * @param int $id Course identifier
     * @return stdClass
     */
    public static function course(int $id): stdClass {
        if ($id <= SITEID) {
            self::deny();
        }
        return get_course($id);
    }

    /**
     * Get groups the current operator can inspect.
     *
     * @param stdClass $course Course
     * @return array
     */
    public static function groups(stdClass $course): array {
        global $USER, $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        return groups_get_all_groups($course->id, self::limited($course) ? $USER->id : 0);
    }

    /**
     * Identify separate-group restrictions.
     *
     * @param stdClass $course Course
     * @return bool
     */
    public static function limited(stdClass $course): bool {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        return (int)groups_get_course_groupmode($course) === SEPARATEGROUPS &&
            !has_capability('moodle/site:accessallgroups', context_course::instance($course->id));
    }

    /**
     * Resolve group scope for reports and bulk actions.
     *
     * @param stdClass $course Course
     * @param int $requested Group identifier
     * @return int
     */
    public static function group(stdClass $course, int $requested): int {
        $groups = self::groups($course);
        if ($requested) {
            if (!isset($groups[$requested])) {
                self::deny();
            }
            return $requested;
        }
        if (!self::limited($course)) {
            return 0;
        }
        if (!$groups) {
            self::deny();
        }
        return (int)array_key_first($groups);
    }

    /**
     * Verify access to a specific person's record in this course.
     *
     * @param stdClass $course Course
     * @param int $userid Subject
     * @param int $groupid Selected group, if any
     * @return bool
     */
    public static function sees(stdClass $course, int $userid, int $groupid = 0): bool {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        if ($groupid) {
            return isset(self::groups($course)[$groupid]) && groups_is_member($groupid, $userid);
        }
        if (!self::limited($course)) {
            return true;
        }
        return (bool)array_intersect_key(self::groups($course), groups_get_all_groups($course->id, $userid));
    }

    /**
     * Fetch an eligible enrolled learner using the native completion audience.
     *
     * @param stdClass $course Course
     * @param int $userid Subject
     * @return stdClass|null
     */
    public static function participant(stdClass $course, int $userid): ?stdClass {
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || $user->suspended || isguestuser($user) || !$user->confirmed) {
            return null;
        }
        $context = context_course::instance($course->id);
        return is_enrolled($context, $user, 'moodle/course:isincompletionreports', true) ? $user : null;
    }

    /**
     * Build a plugin URL.
     *
     * @param string $page Endpoint name
     * @param array $params Parameter values
     * @return moodle_url
     */
    public static function url(string $page, array $params = []): moodle_url {
        return new moodle_url('/local/skillupscertify/' . $page . '.php', $params);
    }

    /**
     * Read localized text.
     *
     * @param string $key String identifier
     * @param mixed $value Interpolated value
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Reject unauthorized or missing data uniformly.
     */
    public static function deny(): never {
        throw new moodle_exception('unavailable', self::C);
    }
}
