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
 * Add SkillUps Gamification Pro to the course menu.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupsgamification\access;
use local_skillupsgamification\store;

/**
 * Expose gamification decisions in the native course navigation.
 *
 * @param navigation_node $navigation Course navigation
 * @param stdClass $course Course
 * @param context_course $context Context
 */
function local_skillupsgamification_extend_navigation_course($navigation, $course, $context): void {
    if ($course->id == SITEID || !has_capability(access::CAP . 'view', $context)) {
        return;
    }
    if (!store::configuration((int)$course->id)->enabled && !has_capability(access::CAP . 'manage', $context)) {
        return;
    }
    $navigation->add(
        access::text('pluginname'),
        access::url('index', (int)$course->id),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_skillupsgamification'
    );
}
