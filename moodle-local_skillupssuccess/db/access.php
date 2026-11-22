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
 * Course capabilities.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [];
foreach (['learner', 'view', 'manage', 'configure', 'assess', 'aggregate'] as $name) {
    $roles = match ($name) {
        'learner' => ['student' => CAP_ALLOW],
        'view' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        default => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    };
    $capabilities['local/skillupssuccess:' . $name] = [
        'riskbitmask' => $name === 'learner' ? 0 : RISK_PERSONAL,
        'captype' => in_array($name, ['manage', 'configure', 'assess'], true) ? 'write' : 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => $roles,
    ];
}
