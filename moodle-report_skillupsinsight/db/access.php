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
 * Analytics capabilities.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [];
foreach (['view', 'export', 'manage', 'personalise', 'schedule', 'learner', 'educator'] as $name) {
    $roles = match ($name) {
        'learner' => ['student' => CAP_ALLOW],
        'educator' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        'manage' => ['manager' => CAP_ALLOW],
        default => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    };
    $capabilities['report/skillupsinsight:' . $name] = [
        'captype' => in_array($name, ['manage', 'personalise', 'schedule']) ? 'write' : 'read',
        'contextlevel' => match ($name) {
            'manage' => CONTEXT_SYSTEM,
            'learner', 'educator' => CONTEXT_COURSE,
            default => CONTEXT_COURSECAT,
        },
        'riskbitmask' => in_array($name, ['learner', 'educator']) ? 0 : RISK_PERSONAL,
        'archetypes' => $roles,
    ];
}
