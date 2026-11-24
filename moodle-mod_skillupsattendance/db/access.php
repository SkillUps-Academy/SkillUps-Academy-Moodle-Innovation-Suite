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
 * Define activity capabilities for learners, instructors, coordinators and administrators.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$staff = ['editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'manager' => CAP_ALLOW];
$capabilities = [
    'mod/skillupsattendance:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],
    'mod/skillupsattendance:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => $staff + ['student' => CAP_ALLOW],
    ],
    'mod/skillupsattendance:recordown' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['student' => CAP_ALLOW],
    ],
];
foreach (['sessions', 'mark', 'review', 'report', 'export', 'audit', 'publish'] as $action) {
    $capabilities['mod/skillupsattendance:' . $action] = [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => in_array($action, ['report', 'audit', 'export'], true) ? 'read' : 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => $staff,
    ];
}
