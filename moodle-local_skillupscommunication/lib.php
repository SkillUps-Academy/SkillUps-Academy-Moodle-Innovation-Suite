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
 * Expose the communication dashboard to authorized course staff.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a link for course users who can view the owning category's communications.
 *
 * @param navigation_node $navigation Course navigation node
 * @param stdClass $course Moodle course
 * @param context_course $context Course context
 */
function local_skillupscommunication_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if ($course->id == SITEID) {
        return;
    }
    $scope = context_coursecat::instance($course->category, IGNORE_MISSING);
    if ($scope && \local_skillupscommunication\access::can($scope, 'view')) {
        $navigation->add(
            get_string('pluginname', 'local_skillupscommunication'),
            new moodle_url('/local/skillupscommunication/index.php', ['scopeid' => $scope->id])
        );
    }
}
