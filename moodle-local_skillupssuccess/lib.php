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
 * Moodle navigation callbacks.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add course support navigation based on native visibility.
 */
function local_skillupssuccess_extend_navigation_course($navigation, $course, $context): void {
    if (\local_skillupssuccess\scope::allowed($course, 'view')) {
        $navigation->add(
            get_string('pluginname', 'local_skillupssuccess'),
            new moodle_url('/local/skillupssuccess/index.php', ['course' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_skillupssuccess'
        );
    } else if (\local_skillupssuccess\scope::allowed($course, 'assess')) {
        $navigation->add(
            get_string('assess', 'local_skillupssuccess'),
            new moodle_url('/local/skillupssuccess/assess.php', ['course' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_skillupssuccess_assess'
        );
    }
}

/**
 * Add authorized category summaries.
 */
function local_skillupssuccess_extend_navigation_category_settings($navigation, $context): void {
    if (has_capability('local/skillupssuccess:aggregate', $context)) {
        $navigation->add(
            get_string('reports', 'local_skillupssuccess'),
            new moodle_url('/local/skillupssuccess/reports.php', ['category' => $context->instanceid]),
            navigation_node::TYPE_SETTING
        );
    }
}
