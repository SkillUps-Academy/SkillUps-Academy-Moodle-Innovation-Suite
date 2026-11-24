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
 * Institutional, course and learner capabilities.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/skillupscompetency:manage' => ['captype' => 'write', 'contextlevel' => CONTEXT_COURSECAT],
    'local/skillupscompetency:catalog' => ['captype' => 'write', 'contextlevel' => CONTEXT_COURSECAT],
    'local/skillupscompetency:reports' => [
        'captype' => 'read', 'riskbitmask' => RISK_PERSONAL, 'contextlevel' => CONTEXT_COURSECAT,
    ],
    'local/skillupscompetency:review' => ['captype' => 'write', 'riskbitmask' => RISK_PERSONAL, 'contextlevel' => CONTEXT_COURSE],
    'local/skillupscompetency:assess' => [
        'captype' => 'write', 'riskbitmask' => RISK_PERSONAL, 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
    'local/skillupscompetency:self' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_COURSE, 'archetypes' => ['student' => CAP_ALLOW],
    ],
    'local/skillupscompetency:view' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
];
