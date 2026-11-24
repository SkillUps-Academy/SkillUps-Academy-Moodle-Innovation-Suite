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
 * Separate planning, viewing, supervision, result and export permissions.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/skillupsexam:catalog' => ['captype' => 'write', 'contextlevel' => CONTEXT_COURSECAT],
    'local/skillupsexam:plan' => ['captype' => 'write', 'contextlevel' => CONTEXT_COURSE],
    'local/skillupsexam:edit' => ['captype' => 'write', 'contextlevel' => CONTEXT_COURSE, 'riskbitmask' => RISK_SPAM],
    'local/skillupsexam:view' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
    'local/skillupsexam:supervise' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_COURSE, 'riskbitmask' => RISK_PERSONAL,
    ],
    'local/skillupsexam:results' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSE, 'riskbitmask' => RISK_PERSONAL,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
    'local/skillupsexam:export' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSE, 'riskbitmask' => RISK_PERSONAL,
    ],
];
