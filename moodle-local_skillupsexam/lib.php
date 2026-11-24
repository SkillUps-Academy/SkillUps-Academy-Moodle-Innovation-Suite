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
 * Course navigation hook.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the exam dashboard for authorized course users.
 *
 * @param navigation_node $navigation Root node
 * @param stdClass $course Moodle course
 * @param context_course $context Course context
 */
function local_skillupsexam_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (\local_skillupsexam\scope::course($course)) {
        $navigation->add(
            \local_skillupsexam\scope::text('pluginname'),
            \local_skillupsexam\scope::url('index', ['courseid' => $course->id])
        );
    }
}
