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

namespace report_skillupsinsight;

/**
 * Access boundary for category, course, program and export queries.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /**
     * Resolve one category or course, or require institution-wide authorization.
     */
    public static function context(int $category, int $course, string $action = 'view'): \context {
        if ($course > 0) {
            $record = get_course($course);
            if ($category || !self::can_course($record, $action)) {
                throw new \moodle_exception('outofscope', util::C);
            }
            return \context_course::instance($course);
        }
        if ($category > 0) {
            $entity = \core_course_category::get($category, IGNORE_MISSING);
            if (!$entity || !has_capability(util::CAP . ':view', $entity->get_context())) {
                throw new \moodle_exception('outofscope', util::C);
            }
            $context = $entity->get_context();
        } else {
            $context = \context_system::instance();
        }
        require_capability(util::CAP . ':view', $context);
        require_capability(util::CAP . ':' . $action, $context);
        return $context;
    }

    /**
     * Guard full-course analytics, including grades, completion and restricted groups.
     */
    public static function can_course(\stdClass $course, string $action): bool {
        if ($course->id == SITEID) {
            return false;
        }
        $context = \context_course::instance($course->id);
        if (
            !has_all_capabilities([
            util::CAP . ':view', util::CAP . ':' . $action, 'moodle/site:accessallgroups',
            'moodle/grade:viewall', 'report/completion:view',
            ], $context)
        ) {
            return false;
        }
        $category = \core_course_category::get($course->category, IGNORE_MISSING);
        return $category && can_access_course($course, null, '', true);
    }

    /**
     * Discover authorized course IDs before reading snapshots or program metadata.
     */
    public static function courses(int $category, int $course, string $action = 'view'): array {
        global $DB;
        self::context($category, $course, $action);
        if ($course !== 0) {
            $record = get_course($course);
            return [$record->id => $record];
        }
        $params = ['site' => SITEID];
        $where = 'c.id <> :site';
        if ($category) {
            $path = \core_course_category::get($category)->path;
            $where .= ' AND (cat.id = :category OR ' . $DB->sql_like('cat.path', ':path') . ')';
            $params += ['category' => $category, 'path' => $DB->sql_like_escape($path . '/') . '%'];
        }
        $records = $DB->get_recordset_sql("SELECT c.id, c.category, c.fullname, c.visible
            FROM {course} c JOIN {course_categories} cat ON cat.id = c.category WHERE $where ORDER BY c.id", $params);
        $courses = [];
        try {
            foreach ($records as $record) {
                if (
                    has_capability(util::CAP . ':view', \context_coursecat::instance($record->category)) &&
                        self::can_course($record, $action)
                ) {
                    $courses[$record->id] = $record;
                }
            }
        } finally {
            $records->close();
        }
        return $courses;
    }

    /**
     * Read the program field for authorized courses, in bounded database batches.
     */
    public static function programs(array $courses): array {
        $shortname = (string)(get_config(util::C, 'programfield') ?: 'insightprogram');
        $handler = \core_course\customfield\course_handler::create();
        $programs = [];
        foreach ($handler->get_fields() as $field) {
            if ($field->get('shortname') !== $shortname || $field->get('type') !== 'text') {
                continue;
            }
            $visible = array_filter(array_keys($courses), static fn($id) => $handler->can_view($field, (int)$id));
            $fieldid = $field->get('id');
            foreach (array_chunk($visible, 200) as $ids) {
                $values = \core_customfield\api::get_instances_fields_data([$fieldid => $field], $ids);
                foreach ($values as $id => $data) {
                    $value = clean_param($data[$fieldid]->export_value() ?? '', PARAM_TEXT);
                    $programs[$id] = \core_text::substr(trim($value), 0, 120);
                }
            }
            break;
        }
        return $programs;
    }
}
