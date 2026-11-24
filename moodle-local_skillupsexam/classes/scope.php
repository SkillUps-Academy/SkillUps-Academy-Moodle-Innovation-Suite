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

namespace local_skillupsexam;

use stdClass;

/**
 * Enforce Moodle context, module, group and assigned-supervisor boundaries.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /** @var string Component. */
    public const C = 'local_skillupsexam';
    /** @var string Capability prefix. */
    public const CAP = 'local/skillupsexam:';

    /**
     * Return a translated label.
     *
     * @param string $key Language key
     * @param mixed $value Optional substitution
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Raise a localized error.
     *
     * @param string $key Language key
     */
    public static function fail(string $key = 'unavailable'): never {
        throw new \moodle_exception($key, self::C);
    }

    /**
     * Build a plugin route.
     *
     * @param string $page Entry point
     * @param array $params Route parameters
     * @return \moodle_url
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupsexam/' . $page . '.php', $params);
    }

    /**
     * Check current course access together with a plugin action.
     *
     * @param stdClass $course Moodle course
     * @param string $action Capability suffix
     * @param int $userid Optional account
     * @return bool
     */
    public static function course(stdClass $course, string $action = 'view', int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int)$USER->id;
        if ($course->id == SITEID || !$userid) {
            return false;
        }
        $context = \context_course::instance($course->id);
        return has_capability(self::CAP . $action, $context, $userid)
            && has_capability(self::CAP . 'view', $context, $userid)
            && ($course->visible || has_capability('moodle/course:viewhiddencourses', $context, $userid))
            && (is_enrolled($context, $userid, '', true) || has_capability('moodle/course:view', $context, $userid));
    }

    /**
     * Check if the category contains the course.
     *
     * @param stdClass $course Moodle course
     * @param int $categoryid Institutional category
     * @return bool
     */
    public static function inside(stdClass $course, int $categoryid): bool {
        $context = \context_coursecat::instance($categoryid, IGNORE_MISSING);
        return $context && str_starts_with(\context_course::instance($course->id)->path . '/', $context->path . '/');
    }

    /**
     * Resolve the linked Quiz or Assign as a visible native activity.
     *
     * @param stdClass $course Moodle course
     * @param int $cmid Optional activity ID
     * @param int $userid Optional account
     * @return \cm_info|null
     */
    public static function module(stdClass $course, int $cmid, int $userid = 0): ?\cm_info {
        if (!$cmid) {
            return null;
        }
        $cm = get_fast_modinfo($course, $userid)->get_cms()[$cmid] ?? null;
        if (
            !$cm || !$cm->uservisible || !in_array($cm->modname, ['quiz', 'assign'], true) ||
            !has_capability('mod/' . $cm->modname . ':view', $cm->context, $userid ?: null)
        ) {
            return null;
        }
        return $cm;
    }

    /**
     * Resolve permitted group IDs, or null when groups impose no restriction.
     *
     * @param stdClass $course Moodle course
     * @param \cm_info|null $cm Linked activity
     * @param int $userid Optional account
     * @return array|null
     */
    public static function groups(stdClass $course, ?\cm_info $cm, int $userid = 0): ?array {
        global $USER;
        $userid = $userid ?: (int)$USER->id;
        $context = $cm ? $cm->context : \context_course::instance($course->id);
        $mode = $cm ? (int)groups_get_activity_groupmode($cm) : (int)groups_get_course_groupmode($course);
        if ($mode !== SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context, $userid)) {
            return null;
        }
        return $cm ? groups_get_activity_allowed_groups($cm, $userid) : groups_get_all_groups($course->id, $userid);
    }

    /**
     * Check visibility and the operation in the exam's current native scope.
     *
     * @param stdClass $exam Convocation record
     * @param stdClass $course Moodle course
     * @param string $action Capability suffix
     * @return bool
     */
    public static function exam(stdClass $exam, stdClass $course, string $action = 'view'): bool {
        global $DB, $USER;
        if ($exam->courseid != $course->id || !self::course($course, $action)) {
            return false;
        }
        if ($action === 'supervise' && $exam->supervisor != $USER->id && !self::course($course, 'edit')) {
            return false;
        }
        // An unlinked or subsequently deleted activity must be recoverable by a coordinator.
        $cm = self::module($course, (int)$exam->cmid);
        if (
            $exam->cmid && !$cm &&
                ($DB->record_exists('course_modules', ['id' => $exam->cmid]) || !self::course($course, 'edit'))
        ) {
            return false;
        }
        $groups = self::groups($course, $cm);
        if ($exam->groupid && !$DB->record_exists('groups', ['id' => $exam->groupid, 'courseid' => $course->id])) {
            return in_array($action, ['view', 'edit'], true) && self::course($course, 'edit');
        }
        return !$exam->groupid || $groups === null || isset($groups[$exam->groupid]);
    }

    /**
     * Set up the native report layout.
     *
     * @param string $page Entry point
     * @param \context $context Moodle context
     * @param array $params Route parameters
     */
    public static function page(string $page, \context $context, array $params = []): void {
        global $PAGE, $SITE;
        $PAGE->set_context($context);
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('report');
        $PAGE->set_show_course_index(false);
        $PAGE->set_title(self::text('pluginname'));
        $PAGE->set_heading(format_string($SITE->fullname));
        $PAGE->set_cacheable(false);
    }
}
