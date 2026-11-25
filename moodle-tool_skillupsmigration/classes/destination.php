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
 * Restore a verified native course into a uniquely tracked hidden destination.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class destination {
    /**
     * Validate the chosen explicit local accounts and non-privileged course role options.
     *
     * @param array $mappings User and role mappings
     * @return array Resolved account/role pairs
     */
    public static function resolve_staff(array $mappings): array {
        global $DB;
        $result = [];
        foreach ($mappings as $mapping) {
            $user = \core_user::get_user_by_username($mapping['targetuser']);
            $role = $DB->get_record('role', ['shortname' => $mapping['targetrole']]);
            if (
                !$user || $user->deleted || $user->suspended || !$user->confirmed || $user->auth === 'nologin' ||
                !$role || !in_array($role->archetype, ['student', 'teacher', 'editingteacher'], true) ||
                !in_array(CONTEXT_COURSE, get_role_contextlevels($role->id))
            ) {
                support::fail('usersmap');
            }
            if (isset($result[$user->id])) {
                support::fail('usersmap');
            }
            $result[$user->id] = (int)$role->id;
        }
        return $result;
    }

    /**
     * Return the unique safely allocated destination or create its hidden shell.
     *
     * @param \stdClass $job Authorized migration
     * @param \stdClass $item Row
     * @return \stdClass Destination
     */
    public static function allocate(\stdClass $job, \stdClass $item): \stdClass {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        if ($item->courseid) {
            return self::tracked($job, $item);
        }
        return support::lock('destination_names', static function () use ($DB, $job, $item): \stdClass {
            return support::transaction(static function () use ($DB, $job, $item): \stdClass {
                if ($DB->record_exists('course', ['shortname' => $item->shortname])) {
                    support::fail('duplicate');
                }
                $data = support::decode($item->inventory);
                $opts = support::decode($item->options);
                $fields = [
                    'category' => $job->categoryid, 'fullname' => $data['name'], 'shortname' => $item->shortname,
                    'format' => $data['format'], 'visible' => 0, 'summary' => '', 'summaryformat' => FORMAT_HTML,
                    'newsitems' => 0,
                    'startdate' => $opts['dates'] ? (int)$data['startdate'] : time(),
                    'enddate' => $opts['dates'] ? (int)$data['enddate'] : 0,
                ];
                if ($opts['configuration']) {
                    $fields['enablecompletion'] = $data['completion'];
                    $fields['groupmode'] = $opts['groups'] ? $data['groupmode'] : NOGROUPS;
                }
                $course = create_course((object)$fields);
                $item->courseid = $course->id;
                $DB->update_record(support::ITEM, $item);
                support::audit((int)$job->id, (int)$item->id, 0, 'allocated');
                return $course;
            });
        });
    }

    /**
     * Verify that the destination has not moved or been made public.
     *
     * @param \stdClass $job Migration with category mapping
     * @param \stdClass $item Row
     * @return \stdClass Moodle course
     */
    private static function tracked(\stdClass $job, \stdClass $item): \stdClass {
        global $DB;
        $target = $DB->get_record('course', ['id' => $item->courseid]);
        if (!$target || $target->visible || $target->category != $job->categoryid || $target->shortname !== $item->shortname) {
            support::fail('targetchanged');
        }
        $context = \context_course::instance($target->id);
        if (!has_capability('moodle/restore:restorecourse', $context, $job->actor)) {
            support::fail('unavailable');
        }
        return $target;
    }

    /**
     * Perform Moodle's live precheck and then journal the irreversible restore boundary.
     *
     * @param \stdClass $job Migration
     * @param \stdClass $item Row
     * @param string $path Verified archive
     */
    public static function restore(\stdClass $job, \stdClass $item, string $path): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $course = self::allocate($job, $item);
        self::tracked($job, $item);
        if (get_fast_modinfo($course)->get_cms()) {
            support::fail('targetchanged');
        }
        $key = \restore_controller::get_tempdir_name((int)$course->id, (int)$job->actor);
        $controller = null;
        try {
            archive::extract($path, $key);
            $controller = new \restore_controller(
                $key,
                (int)$course->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                (int)$job->actor,
                \backup::TARGET_CURRENT_ADDING
            );
            $plan = $controller->get_plan();
            foreach (
                ['users' => false, 'role_assignments' => false, 'permissions' => false,
                'overwrite_conf' => false, 'enrolments' => \backup::ENROL_NEVER,
                'course_startdate' => (int)$course->startdate] as $setting => $value
            ) {
                source::setting($plan, $setting, $value, $setting === 'users');
            }
            $controller->execute_precheck();
            $check = $controller->get_precheck_results();
            if (!empty($check['errors']) || !empty($check['warnings'])) {
                $report = support::decode($item->report);
                $report['precheck'] = [
                    'errors' => count($check['errors'] ?? []),
                    'warnings' => count($check['warnings'] ?? []),
                    'messages' => array_map(
                        static fn($message) => \core_text::substr(trim(strip_tags($message)), 0, 300),
                        array_slice(array_merge($check['errors'] ?? [], $check['warnings'] ?? []), 0, 10)
                    ),
                ];
                $item->report = support::encode($report);
                $DB->update_record(support::ITEM, $item);
                support::fail('nativeprecheck');
            }
            self::tracked($job, $item);
            $item->state = 'restoring';
            $item->updated = time();
            $DB->update_record(support::ITEM, $item);
            support::audit((int)$job->id, (int)$item->id, 0, 'restoring');
            $controller->execute_plan();
            if ($controller->get_status() !== \backup::STATUS_FINISHED_OK) {
                support::fail('restoreerror');
            }
            $item->state = 'verify';
            $DB->update_record(support::ITEM, $item);
            support::audit((int)$job->id, (int)$item->id, 0, 'restored');
        } finally {
            if ($controller) {
                $controller->destroy();
            }
            \backup_helper::delete_backup_dir($key);
        }
    }

    /**
     * Compare final activities, sections, groups and dates, then apply explicit enrolment mappings.
     *
     * @param \stdClass $job Approved migration
     * @param \stdClass $item Restored row
     * @return array Post-restore comparison
     */
    public static function verify(\stdClass $job, \stdClass $item): array {
        $course = self::tracked($job, $item);
        $expected = support::decode($item->inventory);
        $options = support::decode($item->options);
        $modinfo = get_fast_modinfo($course);
        $actualsections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $actualsections[] = (int)$section->section;
        }
        $expectedsections = array_column($expected['sections'], 'number');
        $sectionsbyid = array_column($expected['sections'], 'number', 'id');
        $sourcecount = [];
        foreach ($expected['modules'] as $module) {
            $key = ($sectionsbyid[$module['sectionid']] ?? '?') . ' / ' . $module['type'] . ' / ' . $module['name'];
            $sourcecount[$key] = ($sourcecount[$key] ?? 0) + 1;
        }
        $targetcount = [];
        foreach ($modinfo->get_cms() as $module) {
            $key = $module->sectionnum . ' / ' . $module->modname . ' / ' . $module->name;
            $targetcount[$key] = ($targetcount[$key] ?? 0) + 1;
        }
        $different = [];
        foreach (array_unique(array_merge(array_keys($sourcecount), array_keys($targetcount))) as $key) {
            if (($sourcecount[$key] ?? 0) !== ($targetcount[$key] ?? 0)) {
                $different[] = $key;
            }
        }
        $groups = count(groups_get_all_groups($course->id));
        $missingsections = array_values(array_diff($expectedsections, $actualsections));
        $extrasections = array_values(array_diff($actualsections, $expectedsections));
        $datediff = $options['dates'] && ((int)$course->startdate !== $expected['startdate'] ||
            (int)$course->enddate !== $expected['enddate']);
        $report = [
            'missingsections' => $missingsections, 'extrasections' => $extrasections,
            'activities' => array_slice($different, 0, 30),
            'expectedgroups' => $expected['groups'], 'actualgroups' => $groups, 'datedifference' => $datediff,
            'mapped' => 0, 'match' => !$missingsections && !$different && $groups === $expected['groups'] && !$datediff,
        ];
        if ($report['match'] && $options['staff']) {
            $report['mapped'] = self::enrol($course, (int)$job->actor, $options['staff']);
        }
        return $report;
    }

    /**
     * Use the native Manual enrolment plugin and explicitly assignable roles only.
     *
     * @param \stdClass $course Hidden restored course
     * @param int $actor Original author
     * @param array $mappings Explicit mappings
     * @return int Applied mappings
     */
    private static function enrol(\stdClass $course, int $actor, array $mappings): int {
        $resolved = self::resolve_staff($mappings);
        $context = \context_course::instance($course->id);
        $plugin = enrol_get_plugin('manual');
        $list = array_filter(enrol_get_instances($course->id, true), static fn($instance) => $instance->enrol === 'manual');
        $assignable = get_assignable_roles($context, ROLENAME_ORIGINAL, false, $actor);
        $instance = reset($list);
        if (
            !$plugin || count($list) !== 1 || !$plugin->allow_enrol($instance) ||
            !has_capability('enrol/manual:enrol', $context, $actor) || array_diff($resolved, array_keys($assignable))
        ) {
            support::fail('usersmap');
        }
        support::transaction(static function () use ($resolved, $plugin, $instance): void {
            foreach ($resolved as $userid => $role) {
                $plugin->enrol_user($instance, $userid, $role, 0, 0, ENROL_USER_ACTIVE);
            }
        });
        return count($resolved);
    }
}
