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

namespace local_skillupspathways;

use context_course;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Entry-course permissions, native enrollment and group scope.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Plugin component. */
    public const C = 'local_skillupspathways';

    /** @var string Capability prefix. */
    public const CAP = 'local/skillupspathways:';

    /**
     * Resolve a real course context before the caller invokes Moodle login.
     *
     * @param int $courseid Course ID
     * @return array
     */
    public static function course(int $courseid): array {
        if ($courseid < 1 || $courseid == SITEID) {
            self::deny();
        }
        return [get_course($courseid), context_course::instance($courseid)];
    }

    /**
     * Return an active enrolled account with Moodle access to the course.
     *
     * @param stdClass $course Course record
     * @param int $userid Learner
     * @param bool $entry Require the itinerary capability in the entry course
     * @return bool
     */
    public static function enrolled(stdClass $course, int $userid, bool $entry = false): bool {
        $user = $userid > 0 ? \core_user::get_user($userid) : null;
        if (!$user || $user->deleted || $user->suspended || isguestuser($user)) {
            return false;
        }
        $context = context_course::instance($course->id);
        return is_enrolled($context, $user, '', true) && can_access_course($course, $user, '', true) &&
            (!$entry || has_capability(self::CAP . 'view', $context, $user));
    }

    /**
     * Authorize a manager on the entry course.
     *
     * @param int $courseid Entry course
     */
    public static function manage(int $courseid): void {
        require_capability(self::CAP . 'manage', context_course::instance($courseid));
    }

    /**
     * Determine groups the actor can use in reports.
     *
     * @param stdClass $course Entry course
     * @return array Moodle group records
     */
    public static function groups(stdClass $course): array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/grouplib.php');
        $own = self::limited($course) ? (int)$USER->id : 0;
        return groups_get_all_groups($course->id, $own);
    }

    /**
     * Check whether separate-groups mode restricts this report actor.
     *
     * @param stdClass $course Course record
     * @return bool
     */
    public static function limited(stdClass $course): bool {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        return (int)groups_get_course_groupmode($course) === SEPARATEGROUPS &&
            !has_capability('moodle/site:accessallgroups', context_course::instance($course->id));
    }

    /**
     * Ensure the report group is permitted, selecting a permitted default if required.
     *
     * @param stdClass $course Entry course
     * @param int $groupid Requested group
     * @return int
     */
    public static function report_group(stdClass $course, int $groupid): int {
        require_capability(self::CAP . 'report', context_course::instance($course->id));
        $visible = self::groups($course);
        if ($groupid && !isset($visible[$groupid])) {
            self::deny();
        }
        if (!$groupid && self::limited($course)) {
            if (!$visible) {
                self::deny();
            }
            return (int)array_key_first($visible);
        }
        return $groupid;
    }

    /**
     * Generate a course-scoped endpoint URL.
     *
     * @param string $page Page basename
     * @param int $courseid Entry course
     * @param array $params Additional parameters
     * @return moodle_url
     */
    public static function url(string $page, int $courseid, array $params = []): moodle_url {
        return new moodle_url('/local/skillupspathways/' . $page . '.php', ['courseid' => $courseid] + $params);
    }

    /**
     * Read a localized plugin string.
     *
     * @param string $key String ID
     * @param mixed $value Optional interpolation
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Deny the operation consistently for missing and unauthorized data.
     */
    public static function deny(): never {
        throw new moodle_exception('notavailable', self::C);
    }
}
