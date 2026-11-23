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
 * Add certificate navigation to native course menus.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupscertify\access;

/**
 * Expose the appropriate manager entry point or private certificate wallet.
 *
 * @param navigation_node $navigation Course navigation
 * @param stdClass $course Course record
 * @param context_course $context Course context
 */
function local_skillupscertify_extend_navigation_course($navigation, $course, $context): void {
    if ($course->id == SITEID || !isloggedin() || isguestuser()) {
        return;
    }
    $page = 'mine';
    foreach (['viewregistry' => 'index', 'manage' => 'templates', 'issue' => 'issue'] as $capability => $target) {
        if (has_capability(access::CAP . $capability, $context)) {
            $page = $target;
            break;
        }
    }
    $navigation->add(
        access::text($page === 'mine' ? 'mine' : 'pluginname'),
        access::url($page, $page === 'mine' ? [] : ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        access::C
    );
}
