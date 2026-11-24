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

namespace local_skillupsscheduler;

/**
 * Evaluate Moodle capability, campus grant and group membership.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /** @var string Moodle component. */
    public const C = 'local_skillupsscheduler';
    /** @var string Capability prefix. */
    public const CAP = 'local/skillupsscheduler:';

    /**
     * Resolve a translated Scheduler string.
     *
     * @param string $key Language string key
     * @param mixed $a String parameters
     * @return string
     */
    public static function text(string $key, mixed $a = null): string {
        return get_string($key, self::C, $a);
    }

    /**
     * Raise a localized Scheduler exception.
     *
     * @param string $key Language string key
     * @param mixed $a String parameters
     */
    public static function fail(string $key = 'unavailable', mixed $a = null): never {
        throw new \moodle_exception($key, self::C, '', $a);
    }

    /**
     * Build a Scheduler URL.
     *
     * @param string $page Page
     * @param array $params Params
     * @return \moodle_url
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupsscheduler/' . $page . '.php', $params);
    }

    /**
     * Admin.
     *
     * @param int $userid Moodle user ID
     * @return bool
     */
    public static function admin(int $userid = 0): bool {
        return has_capability(self::CAP . 'admin', \context_system::instance(), $userid ?: null);
    }

    /**
     * Verify the category capability and campus grant.
     *
     * @param \stdClass $site Campus record
     * @param string $action Capability suffix
     * @param int $userid Moodle user ID
     * @return bool
     */
    public static function staff(\stdClass $site, string $action, int $userid = 0): bool {
        global $DB, $USER;
        $userid = $userid ?: (int)$USER->id;
        if (self::admin($userid)) {
            return true;
        }
        $context = \context_coursecat::instance($site->categoryid, IGNORE_MISSING);
        return $context && has_capability(self::CAP . $action, $context, $userid) &&
            $DB->record_exists(store::GRANT, ['siteid' => $site->id, 'userid' => $userid]);
    }

    /**
     * Require staff.
     *
     * @param \stdClass $site Campus record
     * @param string $action Capability suffix
     */
    public static function require_staff(\stdClass $site, string $action): void {
        if (!self::staff($site, $action)) {
            self::fail();
        }
    }

    /**
     * Course in site.
     *
     * @param \stdClass $site Campus record
     * @param \stdClass $course Moodle course record
     * @return bool
     */
    public static function course_in_site(\stdClass $site, \stdClass $course): bool {
        if ($course->id == SITEID) {
            return false;
        }
        $category = \context_coursecat::instance($site->categoryid, IGNORE_MISSING);
        $context = \context_course::instance($course->id, IGNORE_MISSING);
        return $category && $context && str_starts_with($context->path . '/', $category->path . '/');
    }

    /**
     * Participant.
     *
     * @param \stdClass $course Moodle course record
     * @param int $groupid Moodle group ID or zero
     * @param int $userid Moodle user ID
     * @return bool
     */
    public static function participant(\stdClass $course, int $groupid, int $userid): bool {
        global $DB;
        $context = \context_course::instance($course->id);
        if (
            !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]) ||
            !is_enrolled($context, $userid, self::CAP . 'view', true) ||
            (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $userid))
        ) {
            return false;
        }
        return !$groupid || groups_is_member($groupid, $userid) ||
            has_capability('moodle/site:accessallgroups', $context, $userid);
    }

    /**
     * Verify session read access.
     *
     * @param \stdClass $slot Session data
     * @param int $userid Moodle user ID
     * @return bool
     */
    public static function visible(\stdClass $slot, int $userid = 0): bool {
        global $DB, $USER;
        $userid = $userid ?: (int)$USER->id;
        $site = $DB->get_record(store::SITE, ['id' => $slot->siteid]);
        $course = $DB->get_record('course', ['id' => $slot->courseid]);
        if (!$site || !$course || !self::course_in_site($site, $course)) {
            return false;
        }
        if (self::staff($site, 'plan', $userid)) {
            return true;
        }
        if ($slot->status !== 'published') {
            return false;
        }
        if ($slot->groupid && !$DB->record_exists('groups', ['id' => $slot->groupid, 'courseid' => $course->id])) {
            return false;
        }
        return self::staff($site, 'consult', $userid) || self::attends($slot, $course, $userid);
    }

    /**
     * Check the enrolled audience and assigned teacher.
     *
     * @param \stdClass $slot Session data
     * @param \stdClass $course Moodle course record
     * @param int $userid Moodle user ID
     * @return bool
     */
    public static function attends(\stdClass $slot, \stdClass $course, int $userid): bool {
        if ($slot->teacherid == $userid && self::teacher((int)$course->id, $userid)) {
            return $course->visible || has_capability(
                'moodle/course:viewhiddencourses',
                \context_course::instance($course->id),
                $userid,
            );
        }
        return self::participant($course, (int)$slot->groupid, $userid);
    }

    /**
     * Teacher.
     *
     * @param int $courseid Moodle course ID
     * @param int $userid Moodle user ID
     * @return bool
     */
    public static function teacher(int $courseid, int $userid): bool {
        global $DB;
        return $userid > 0 && $DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]) &&
            is_enrolled(\context_course::instance($courseid), $userid, self::CAP . 'teach', true);
    }

    /**
     * Initialize a Scheduler page in Moodle.
     *
     * @param string $page Page
     * @param string $title Title
     * @param array $params Params
     */
    public static function setup_page(string $page, string $title, array $params = []): void {
        global $PAGE, $SITE;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('standard');
        $PAGE->set_heading(format_string($SITE->fullname));
        $PAGE->set_title($title);
        $PAGE->set_cacheable(false);
    }
}
