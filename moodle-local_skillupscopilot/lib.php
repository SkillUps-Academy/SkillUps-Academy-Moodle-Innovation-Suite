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
 * SkillUps AI Copilot lib.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Record administrative settings changes in Moodle's log.
 */
function local_skillupscopilot_config_updated(): void {
    \local_skillupscopilot\event\operation_recorded::record(context_system::instance(), 'settingschanged');
}

/**
 * Extend the course secondary navigation.
 */
function local_skillupscopilot_extend_navigation_course($navigation, $course, $context): void {
    global $USER;
    if ((int)$course->id === SITEID || !\local_skillupscopilot\access::allowed((int)$course->id, $USER)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'local_skillupscopilot'),
        new moodle_url('/local/skillupscopilot/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_skillupscopilot'
    );
}
