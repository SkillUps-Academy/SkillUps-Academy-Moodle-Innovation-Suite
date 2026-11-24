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
 * Capabilities for institutional and scoped scheduling.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/skillupsscheduler:admin' => ['captype' => 'write', 'riskbitmask' => RISK_PERSONAL, 'contextlevel' => CONTEXT_SYSTEM],
    'local/skillupsscheduler:plan' => ['captype' => 'write', 'contextlevel' => CONTEXT_COURSECAT],
    'local/skillupsscheduler:publish' => ['captype' => 'write', 'riskbitmask' => RISK_SPAM, 'contextlevel' => CONTEXT_COURSECAT],
    'local/skillupsscheduler:audit' => ['captype' => 'read', 'riskbitmask' => RISK_PERSONAL, 'contextlevel' => CONTEXT_COURSECAT],
    'local/skillupsscheduler:consult' => ['captype' => 'read', 'contextlevel' => CONTEXT_COURSECAT],
    'local/skillupsscheduler:teach' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
    ],
    'local/skillupsscheduler:view' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'student' => CAP_ALLOW],
    ],
];
