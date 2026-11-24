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
 * Observe an explicit list of Moodle administrative events.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    ['eventname' => '\core\event\role_assigned', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_unassigned', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_created', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_deleted', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_updated', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_capabilities_updated', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_allow_assign_updated', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_allow_override_updated', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_allow_switch_updated', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\role_allow_view_updated', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\config_log_created', 'callback' => '\report_skillupssecurity\observer::capture'],
    ['eventname' => '\core\event\user_loggedinas', 'callback' => '\report_skillupssecurity\observer::capture'],
];
