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

namespace tool_skillupsmigration;

/**
 * Inventory an authorized source course and normalize content choices.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inventory {
    /**
     * Validate source export permissions for a dedicated Moodle account.
     *
     * @param int $id Course ID
     * @param int $actor Credential owner
     * @return \stdClass Course
     */
    public static function course(int $id, int $actor): \stdClass {
        global $DB;
        support::active($actor);
        $course = $id !== SITEID ? $DB->get_record('course', ['id' => $id]) : false;
        if (!$course) {
            support::fail('unavailable');
        }
        $context = \context_course::instance($id);
        if (
            !has_capability(support::CAP . 'export', $context, $actor) ||
            !has_capability('moodle/backup:backupcourse', $context, $actor) ||
            (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $actor))
        ) {
            support::fail('unavailable');
        }
        return $course;
    }

    /**
     * List authorized courses in bounded cursor pages.
     *
     * @param int $actor Credential owner
     * @param int $after Starting course ID
     * @param int $category Moodle category to list, or zero
     * @return array Page and cursor
     */
    public static function browse(int $actor, int $after, int $category = 0): array {
        global $DB;
        $params = ['after' => max(0, $after), 'site' => SITEID];
        $where = 'id > :after AND id <> :site';
        if ($category) {
            $where .= ' AND category = :category';
            $params['category'] = $category;
        }
        $ids = $DB->get_records_select('course', $where, $params, 'id ASC', 'id', 0, 200);
        $items = [];
        $cursor = 0;
        foreach ($ids as $row) {
            $cursor = (int)$row->id;
            try {
                $course = self::course($cursor, $actor);
            } catch (\moodle_exception $error) {
                continue;
            }
            $cat = \core_course_category::get($course->category, MUST_EXIST, true);
            $items[] = ['id' => $cursor, 'name' => $course->fullname, 'categoryid' => (int)$cat->id, 'category' => $cat->name];
        }
        return ['rows' => $items, 'next' => count($ids) === 200 ? $cursor : 0];
    }

    /**
     * Normalize content selection, flags and optional individual mapping.
     *
     * @param array $data Options payload
     * @return array Canonical options
     */
    public static function options(array $data): array {
        $out = [];
        foreach (['sections' => 500, 'activities' => 5000] as $key => $bound) {
            $items = $data[$key] ?? [];
            if (!is_array($items) || count($items) > $bound) {
                support::fail('selection');
            }
            $values = array_values(array_unique(array_map('intval', $items)));
            if ($values && min($values) < 1) {
                support::fail('selection');
            }
            sort($values, SORT_NUMERIC);
            $out[$key] = $values;
        }
        foreach (['groups', 'files', 'dates', 'configuration'] as $key) {
            $out[$key] = !empty($data[$key]);
        }
        $out['staff'] = [];
        if (!empty($data['staff'])) {
            if (!is_array($data['staff']) || count($data['staff']) > 30) {
                support::fail('usersmap');
            }
            foreach ($data['staff'] as $row) {
                $mapping = [];
                foreach (['sourceuser', 'targetuser', 'sourcerole', 'targetrole'] as $key) {
                    $value = trim((string)($row[$key] ?? ''));
                    if ($value === '' || \core_text::strlen($value) > 100 || preg_match('/[\x00-\x20,;]/u', $value)) {
                        support::fail('usersmap');
                    }
                    $mapping[$key] = $value;
                }
                $out['staff'][] = $mapping;
            }
        }
        return $out;
    }

    /**
     * Inspect source topology with exact selected section and module IDs.
     *
     * @param int $id Source ID
     * @param int $actor Credential owner
     * @param array $options Canonical options
     * @return array Source inventory without learner records
     */
    public static function read(int $id, int $actor, array $options): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course = self::course($id, $actor);
        $modinfo = get_fast_modinfo($course, $actor);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$options['sections'] || in_array((int)$section->id, $options['sections'], true)) {
                $sections[$section->id] = [
                    'id' => (int)$section->id, 'number' => (int)$section->section,
                    'name' => get_section_name($course, $section),
                ];
            }
        }
        $modules = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (
                !$cm->deletioninprogress && (!$options['activities'] || in_array((int)$cm->id, $options['activities'], true)) &&
                isset($sections[$cm->section])
            ) {
                $component = 'mod_' . $cm->modname;
                $plugin = \core_plugin_manager::instance()->get_plugin_info($component);
                $modules[$cm->id] = [
                    'id' => (int)$cm->id, 'type' => $cm->modname, 'sectionid' => (int)$cm->section, 'name' => $cm->name,
                    'backup' => (bool)plugin_supports('mod', $cm->modname, FEATURE_BACKUP_MOODLE2, false),
                    'version' => (int)($plugin->versiondb ?? 0),
                ];
            }
        }
        if (
            count($sections) > 500 || count($modules) > 5000 ||
            array_diff($options['sections'], array_keys($sections)) || array_diff($options['activities'], array_keys($modules))
        ) {
            support::fail('selection');
        }
        self::verify_staff($course, $options['staff']);
        $cat = \core_course_category::get($course->category, MUST_EXIST, true);
        return [
            'id' => (int)$course->id, 'name' => $course->fullname, 'oldshort' => $course->shortname,
            'categoryid' => (int)$cat->id, 'category' => $cat->name, 'format' => $course->format,
            'startdate' => (int)$course->startdate, 'enddate' => (int)$course->enddate,
            'completion' => (int)$course->enablecompletion, 'groupmode' => (int)$course->groupmode,
            'groups' => $options['groups'] ? count(groups_get_all_groups($course->id)) : 0,
            'version' => (int)$CFG->version, 'release' => (string)$CFG->release,
            'sections' => array_values($sections), 'modules' => array_values($modules),
        ];
    }

    /**
     * Verify explicitly named source account/role pairs against active native enrollments.
     *
     * @param \stdClass $course Source course
     * @param array $staff Confirmed mapping selectors
     */
    private static function verify_staff(\stdClass $course, array $staff): void {
        $context = \context_course::instance($course->id);
        foreach ($staff as $entry) {
            $user = \core_user::get_user_by_username($entry['sourceuser']);
            if (!$user || $user->deleted || $user->suspended || !is_enrolled($context, $user, '', true)) {
                support::fail('usersmap');
            }
            $roles = array_column(get_user_roles($context, $user->id, false), 'shortname');
            if (!in_array($entry['sourcerole'], $roles, true)) {
                support::fail('usersmap');
            }
        }
    }
}
