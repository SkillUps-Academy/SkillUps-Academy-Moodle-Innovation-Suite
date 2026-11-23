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

use context_course;
use moodle_exception;
use moodle_url;

/**
 * Course and participant authorization.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Plugin component. */
    public const C = 'local_skillupsgamification';

    /** @var string Capability prefix. */
    public const CAP = 'local/skillupsgamification:';

    /**
     * Open a Moodle page in one real course.
     *
     * @param int $courseid Requested course
     * @return array Course and context
     */
    public static function course(int $courseid): array {
        if ($courseid <= 0 || $courseid == SITEID) {
            throw new moodle_exception('invalidcourse', self::C);
        }
        $course = get_course($courseid);
        return [$course, context_course::instance($courseid)];
    }

    /**
     * Confirm a real participant with active enrollment and award permission.
     *
     * @param context_course $context Course context
     * @param int $userid Participant
     * @return bool
     */
    public static function participant(context_course $context, int $userid): bool {
        if ($userid <= 0) {
            return false;
        }
        $user = \core_user::get_user($userid);
        return $user && !$user->suspended && !$user->deleted && !isguestuser($user) &&
            is_enrolled($context, $user, self::CAP . 'earn', true) && has_capability(self::CAP . 'view', $context, $user);
    }

    /**
     * Require the course management capability.
     *
     * @param int $courseid Course ID
     */
    public static function manager(int $courseid): void {
        require_capability(self::CAP . 'manage', context_course::instance($courseid));
    }

    /**
     * Create a course-bound URL.
     *
     * @param string $page Plugin page
     * @param int $courseid Course ID
     * @param array $params Optional parameters
     * @return moodle_url
     */
    public static function url(string $page, int $courseid, array $params = []): moodle_url {
        return new moodle_url('/local/skillupsgamification/' . $page . '.php', ['courseid' => $courseid] + $params);
    }

    /**
     * Localized string accessor.
     *
     * @param string $key String identifier
     * @param mixed $value Optional argument
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Deny an unavailable action without exposing details of another user.
     */
    public static function deny(): never {
        throw new moodle_exception('notavailable', self::C);
    }
}
