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
 * Category-scoped library capabilities.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/skillupsrepository:view' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => ['manager' => CAP_ALLOW, 'coursecreator' => CAP_ALLOW],
    ],
    'local/skillupsrepository:download' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => ['manager' => CAP_ALLOW, 'coursecreator' => CAP_ALLOW],
    ],
    'local/skillupsrepository:contribute' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_COURSECAT, 'riskbitmask' => RISK_SPAM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/skillupsrepository:moderate' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_COURSECAT, 'riskbitmask' => RISK_PERSONAL,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/skillupsrepository:reuse' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_COURSECAT, 'riskbitmask' => RISK_PERSONAL,
        'archetypes' => ['manager' => CAP_ALLOW, 'coursecreator' => CAP_ALLOW],
    ],
];
