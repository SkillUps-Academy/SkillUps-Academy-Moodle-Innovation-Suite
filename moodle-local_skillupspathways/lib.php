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
 * Register the native Moodle course navigation entry.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupspathways\access;

/**
 * Add the course pathway portal.
 *
 * @param navigation_node $navigation Navigation node
 * @param stdClass $course Course record
 * @param context_course $context Course context
 */
function local_skillupspathways_extend_navigation_course($navigation, $course, $context): void {
    if ($course->id != SITEID && has_capability(access::CAP . 'view', $context)) {
        $navigation->add(
            access::text('pluginname'),
            access::url('index', (int)$course->id),
            navigation_node::TYPE_CUSTOM,
            null,
            access::C
        );
    }
}
