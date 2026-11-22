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
 * Site and course navigation callbacks.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Link to the course-scoped dashboard.
 */
function report_skillupsinsight_extend_navigation_course($navigation, $course, $context): void {
    if (\report_skillupsinsight\scope::can_course($course, 'view')) {
        $navigation->add(
            get_string('pluginname', 'report_skillupsinsight'),
            new moodle_url('/report/skillupsinsight/index.php', ['course' => $course->id]),
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * Link to the category-scoped dashboard.
 */
function report_skillupsinsight_extend_navigation_category_settings($navigation, $context): void {
    if (has_capability('report/skillupsinsight:view', $context)) {
        $navigation->add(
            get_string('pluginname', 'report_skillupsinsight'),
            new moodle_url('/report/skillupsinsight/index.php', ['category' => $context->instanceid]),
            navigation_node::TYPE_SETTING
        );
    }
}
