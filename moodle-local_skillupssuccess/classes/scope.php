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

namespace local_skillupssuccess;

/**
 * Resolve staff, subject and course authorization before querying sensitive records.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /**
     * Course staff must have course-wide native visibility over the data sources.
     */
    public static function allowed(\stdClass $course, string $capability, ?int $userid = null): bool {
        if ($course->id == SITEID) {
            return false;
        }
        $context = \context_course::instance($course->id);
        if (
            !has_all_capabilities([
            util::CAP . ':' . $capability, 'moodle/site:accessallgroups', 'moodle/grade:viewall', 'report/log:view',
            ], $context, $userid) || !can_access_course($course, $userid, '', true)
        ) {
            return false;
        }
        foreach (get_fast_modinfo($course, $userid ?? 0)->get_instances_of('assign') as $cm) {
            if (
                $cm->visible && (!$cm->uservisible ||
                    !has_all_capabilities(['mod/assign:grade', 'moodle/site:accessallgroups'], $cm->context, $userid))
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Protect each course-backed route, including closed cases and exported views.
     */
    public static function course(int $id, string $capability = 'view'): \stdClass {
        $course = get_course($id);
        if (!self::allowed($course, $capability)) {
            util::fail('outofscope');
        }
        return $course;
    }

    /**
     * Check the original learner's current enrollment before exposing an identified record.
     */
    public static function learner(\stdClass $course, int $userid): bool {
        $user = \core_user::get_user($userid);
        $context = \context_course::instance($course->id);
        return $user && self::active($user) && is_enrolled($context, $userid, util::CAP . ':learner', true);
    }

    /**
     * Check the chosen account before notifying, viewing or using its authorization.
     */
    public static function active(\stdClass $user): bool {
        return !$user->deleted && !$user->suspended && $user->confirmed && !isguestuser($user);
    }

    /**
     * Query the active student population with a compact native enrolment subquery.
     */
    public static function population(\stdClass $course): array {
        [$sql, $params] = get_enrolled_sql(\context_course::instance($course->id), util::CAP . ':learner', 0, true);
        return ["SELECT u.id FROM {user} u JOIN ($sql) cohort ON cohort.id = u.id
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1", $params];
    }

    /**
     * List authorized course rows before consulting course reports under an institution/category scope.
     */
    public static function reports(int $category = 0): array {
        global $DB;
        if ($category) {
            $entity = \core_course_category::get($category, IGNORE_MISSING);
            if (!$entity) {
                util::fail('outofscope');
            }
            require_capability(util::CAP . ':aggregate', $entity->get_context());
            $suffix = 'AND (a.id = :category OR ' . $DB->sql_like('a.path', ':path') . ')';
            $params = ['category' => $category, 'path' => $DB->sql_like_escape($entity->path . '/') . '%'];
        } else {
            require_capability(util::CAP . ':aggregate', \context_system::instance());
            $suffix = '';
            $params = [];
        }
        $policies = store::POLICY;
        $rows = $DB->get_recordset_sql("SELECT c.* FROM {course} c JOIN {course_categories} a ON a.id = c.category
            JOIN {{$policies}} p ON p.courseid = c.id WHERE c.id <> :site $suffix ORDER BY c.id", $params + ['site' => SITEID]);
        $courses = [];
        try {
            foreach ($rows as $course) {
                $cat = \core_course_category::get($course->category, IGNORE_MISSING);
                if ($cat && has_capability(util::CAP . ':aggregate', $cat->get_context()) && self::allowed($course, 'aggregate')) {
                    $courses[$course->id] = $course;
                }
            }
        } finally {
            $rows->close();
        }
        return $courses;
    }
}
