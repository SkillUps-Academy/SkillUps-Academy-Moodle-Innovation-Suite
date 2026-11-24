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

namespace local_skillupscompetency;

use core_competency\api;
use core_competency\competency;
use core_competency\user_competency;
use stdClass;

/**
 * Enforce course, category, group and activity boundaries.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Moodle component. */
    public const C = 'local_skillupscompetency';
    /** @var string Capability prefix. */
    public const CAP = 'local/skillupscompetency:';

    /**
     * Return a localized plugin label.
     *
     * @param string $key Language key or unique record fields
     * @param mixed $value Value to format
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Raise a localized scope or validation error.
     *
     * @param string $key Language key or unique record fields
     */
    public static function fail(string $key = 'notavailable'): never {
        throw new \moodle_exception($key, self::C);
    }

    /**
     * Build a plugin URL.
     *
     * @param string $page Plugin endpoint
     * @param array $params Route parameters
     * @return \moodle_url
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupscompetency/' . $page . '.php', $params);
    }

    /**
     * Initialize the Moodle page.
     *
     * @param string $page Plugin endpoint
     * @param \context $context Authorized Moodle context
     * @param array $params Route parameters
     */
    public static function setup(string $page, \context $context, array $params = []): void {
        global $PAGE, $SITE;
        api::require_enabled();
        $PAGE->set_context($context);
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_heading(format_string($SITE->fullname));
        $PAGE->set_title(self::text('pluginname'));
        $PAGE->set_cacheable(false);
    }

    /**
     * Load a visible native competency in scope.
     *
     * @param int $id Record ID
     * @param \context|null $scope Permitted ancestry
     * @return competency
     */
    public static function competency(int $id, ?\context $scope = null): competency {
        if (!$id || !competency::record_exists($id)) {
            self::fail();
        }
        $item = api::read_competency($id);
        $context = $item->get_context();
        if ($scope && (!$item->get_framework()->get('visible') || !self::within($scope, $context))) {
            self::fail();
        }
        return $item;
    }

    /**
     * Check that the Moodle context descends from the permitted scope.
     *
     * @param \context $child Target context
     * @param \context $parent Ancestor context
     * @return bool
     */
    public static function within(\context $child, \context $parent): bool {
        return str_starts_with($child->path . '/', $parent->path . '/');
    }

    /**
     * Check native visibility and course access.
     *
     * @param stdClass $course Moodle course record
     * @return bool
     */
    public static function course_visible(stdClass $course): bool {
        $context = \context_course::instance($course->id);
        return $course->id != SITEID && ($course->visible || has_capability('moodle/course:viewhiddencourses', $context)) &&
            (is_enrolled($context, null, '', true) || has_capability('moodle/course:view', $context));
    }

    /**
     * Check the learner and observer against native course and group permissions.
     *
     * @param stdClass $course Moodle course record
     * @param int $userid Learner ID
     * @param int $cmid Optional course activity ID
     * @return bool
     */
    public static function can_see_subject(stdClass $course, int $userid, int $cmid = 0): bool {
        global $DB, $USER;
        $context = \context_course::instance($course->id);
        if (
            !self::course_visible($course) || !is_enrolled($context, $userid, 'moodle/competency:coursecompetencygradable', true) ||
            !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])
        ) {
            return false;
        }
        $actor = (int)$USER->id;
        $cm = null;
        if ($cmid) {
            $info = get_fast_modinfo($course, $actor);
            $cm = $info->get_cms()[$cmid] ?? null;
            if (!$cm || !$cm->uservisible) {
                return false;
            }
        }
        if ($userid === $actor) {
            return has_capability(self::CAP . 'view', $context);
        }
        if (
            !has_any_capability([self::CAP . 'assess', self::CAP . 'review', self::CAP . 'reports'], $context) ||
            !user_competency::can_read_user_in_course($userid, $course->id)
        ) {
            return false;
        }
        if (has_capability('moodle/site:accessallgroups', $context)) {
            return true;
        }
        $separate = (int)groups_get_course_groupmode($course) === SEPARATEGROUPS;
        if ($cm && (int)groups_get_activity_groupmode($cm) === SEPARATEGROUPS) {
            return (bool)array_intersect_key(groups_get_activity_allowed_groups($cm), groups_get_all_groups($course->id, $userid));
        }
        return !$separate || (bool)array_intersect_key(
            groups_get_all_groups($course->id, $actor),
            groups_get_all_groups($course->id, $userid),
        );
    }

    /**
     * Require a native association to the requested course or activity.
     *
     * @param stdClass $course Moodle course record
     * @param int $competencyid Native competency ID
     * @param int $cmid Optional course activity ID
     * @return competency
     */
    public static function linked(stdClass $course, int $competencyid, int $cmid = 0): competency {
        $item = self::competency($competencyid, \context_course::instance($course->id));
        foreach (self::links($course, $cmid) as $link) {
            if ((int)$link->get('id') === $competencyid) {
                return $item;
            }
        }
        self::fail('notlinked');
    }

    /**
     * Return competency persistents from the native association API.
     *
     * @param stdClass $course Moodle course
     * @param int $cmid Optional activity ID
     * @return competency[]
     */
    public static function links(stdClass $course, int $cmid = 0): array {
        $records = $cmid ? api::list_course_module_competencies($cmid) : api::list_course_competencies($course);
        return array_column($records, 'competency');
    }

    /**
     * Check access to an assessment and its private details.
     *
     * @param stdClass $record Assessment record
     * @return bool
     */
    public static function read_assessment(stdClass $record): bool {
        global $DB, $USER;
        $course = $DB->get_record('course', ['id' => $record->courseid]);
        if (!$course || !self::can_see_subject($course, (int)$record->userid, (int)$record->cmid)) {
            return false;
        }
        try {
            self::linked($course, (int)$record->competencyid);
        } catch (\moodle_exception $error) {
            return false;
        }
        if ($record->userid == $USER->id) {
            return $record->kind === 'self' || $record->status === 'approved';
        }
        return has_any_capability([self::CAP . 'assess', self::CAP . 'review'], \context_course::instance($course->id));
    }
}
