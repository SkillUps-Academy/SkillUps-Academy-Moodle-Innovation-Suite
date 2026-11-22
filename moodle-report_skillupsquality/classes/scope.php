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

namespace report_skillupsquality;

/**
 * Resolve course boundaries before accessing operational records.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /** Component name. */
    public const COMPONENT = 'report_skillupsquality';

    /**
     * Translate a plugin string.
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::COMPONENT, $value);
    }

    /**
     * A course audit concerns the full configuration, including hidden items.
     */
    public static function allowed(\stdClass $course, string $action = 'view', ?int $userid = null): bool {
        if ($course->id == SITEID || !\core_course_category::can_view_course_info($course, $userid)) {
            return false;
        }
        $context = \context_course::instance($course->id);
        return has_capability('moodle/course:update', $context, $userid)
            && has_capability('report/skillupsquality:view', $context, $userid)
            && has_capability('report/skillupsquality:' . $action, $context, $userid);
    }

    /**
     * Verify course membership in the permission scope.
     */
    public static function course(int $id, string $action = 'view'): \stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $id]);
        if (!$course || !self::allowed($course, $action)) {
            throw new \moodle_exception('scopefailure', self::COMPONENT);
        }
        return $course;
    }

    /**
     * Check the category separately before enumerating eligible courses.
     */
    public static function category(int $id): \core_course_category {
        $category = $id > 0 ? \core_course_category::get($id, IGNORE_MISSING) : null;
        if (!$category) {
            throw new \moodle_exception('scopefailure', self::COMPONENT);
        }
        require_capability('report/skillupsquality:batch', $category->get_context());
        return $category;
    }

    /**
     * Resolve authorized course IDs at submission time.
     */
    public static function batch(string $mode, int $categoryid, string $selected): array {
        global $DB;
        if ($categoryid) {
            $category = self::category($categoryid);
            $allowed = array_fill_keys(array_merge([$categoryid], $category->get_all_children_ids()), true);
        } else {
            require_capability('report/skillupsquality:batch', \context_system::instance());
            $category = null;
            $allowed = null;
        }
        if ($mode === 'category' && $category) {
            $ids = $category->get_courses(['recursive' => true, 'limit' => 51, 'idonly' => true]);
            if (count($ids) > 50) {
                throw new \moodle_exception('batchlimit', self::COMPONENT);
            }
            $courses = [];
            foreach ($DB->get_records_list('course', 'id', $ids) as $course) {
                if (isset($allowed[$course->category]) && self::allowed($course, 'run')) {
                    $courses[] = $course;
                }
            }
            return $courses;
        }
        if ($mode !== 'selected' || strlen($selected) > 700 || !preg_match('/^\s*\d+(?:[\s,]+\d+)*\s*$/D', $selected)) {
            throw new \moodle_exception('invalidbatch', self::COMPONENT);
        }
        $ids = array_values(array_unique(array_map('intval', preg_split('/[\s,]+/', trim($selected)))));
        if (count($ids) > 50) {
            throw new \moodle_exception('batchlimit', self::COMPONENT);
        }
        $courses = $DB->get_records_list('course', 'id', $ids);
        foreach ($ids as $id) {
            $course = $courses[$id] ?? null;
            if (!$course || ($allowed !== null && !isset($allowed[$course->category])) || !self::allowed($course, 'run')) {
                throw new \moodle_exception('scopefailure', self::COMPONENT);
            }
        }
        return $courses;
    }
}
