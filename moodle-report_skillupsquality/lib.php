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
 * Course navigation for the quality report.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the quality report to the course report menu.
 *
 * @param navigation_node $navigation Reports node
 * @param stdClass $course Course record
 * @param context_course $context Course context
 */
function report_skillupsquality_extend_navigation_course($navigation, $course, $context): void {
    if (!\report_skillupsquality\scope::allowed($course)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'report_skillupsquality'),
        new moodle_url('/report/skillupsquality/index.php', ['course' => $course->id]),
        navigation_node::TYPE_SETTING
    );
}

/**
 * Add batch auditing to a category's settings menu.
 *
 * @param settings_navigation $navigation Category settings node
 * @param context_coursecat $context Category context
 */
function report_skillupsquality_extend_navigation_category_settings($navigation, $context): void {
    if (has_capability('report/skillupsquality:batch', $context)) {
        $navigation->add(
            get_string('pluginname', 'report_skillupsquality'),
            new moodle_url('/report/skillupsquality/index.php', ['category' => $context->instanceid]),
            navigation_node::TYPE_SETTING
        );
    }
}
