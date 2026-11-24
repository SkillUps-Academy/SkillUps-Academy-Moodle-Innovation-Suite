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

/**
 * Register report navigation and restrict review evidence downloads.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the report to an authorized course.
 *
 * @param navigation_node $navigation Reports node
 * @param stdClass $course Course record
 * @param context_course $context Native course context
 */
function report_skillupsaccessibility_extend_navigation_course($navigation, $course, $context): void {
    if (\report_skillupsaccessibility\scope::allowed($course)) {
        $navigation->add(
            \report_skillupsaccessibility\scope::text('pluginname'),
            \report_skillupsaccessibility\scope::url('course', ['id' => $course->id]),
            navigation_node::TYPE_SETTING,
        );
    }
}

/**
 * Add bulk audits to an authorized category.
 *
 * @param settings_navigation $navigation Category settings
 * @param context_coursecat $context Native category context
 */
function report_skillupsaccessibility_extend_navigation_category_settings($navigation, $context): void {
    if (has_capability('report/skillupsaccessibility:bulk', $context)) {
        $navigation->add(
            \report_skillupsaccessibility\scope::text('pluginname'),
            \report_skillupsaccessibility\scope::url('index', ['category' => $context->instanceid]),
            navigation_node::TYPE_SETTING,
        );
    }
}

/**
 * Serve protected evidence after validating the current finding and source.
 *
 * @param stdClass $course Moodle course
 * @param stdClass|null $cm Moodle module if applicable
 * @param context $context Evidence course context
 * @param string $filearea File area
 * @param array $args Record ID and filename
 * @param bool $forcedownload Moodle request
 * @param array $options Additional file options
 * @return bool
 */
function report_skillupsaccessibility_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = [],
): bool {
    global $DB;
    $component = 'report_skillupsaccessibility';
    if (
        $context->contextlevel !== CONTEXT_COURSE || $filearea !== 'evidence' ||
            count($args) !== 2 || !ctype_digit((string)$args[0])
    ) {
        return false;
    }
    require_login($course);
    $entry = $DB->get_record(\report_skillupsaccessibility\store::REVIEW, [
        'id' => (int)$args[0], 'courseid' => $context->instanceid,
    ]);
    if (!$entry) {
        return false;
    }
    try {
        \report_skillupsaccessibility\review::open((int)$entry->findingid);
    } catch (moodle_exception $error) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, $component, $filearea, $entry->id, '/', $args[1]);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, ['cacheability' => 'private'] + $options);
    return true;
}
