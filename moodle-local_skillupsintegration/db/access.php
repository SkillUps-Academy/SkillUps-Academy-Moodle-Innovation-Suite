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
 * Restrict integration setup, preview, approval and service ingestion.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/skillupsintegration:view' => ['riskbitmask' => RISK_PERSONAL, 'captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM],
    'local/skillupsintegration:manage' => ['riskbitmask' => RISK_CONFIG, 'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM],
    'local/skillupsintegration:preview' => ['riskbitmask' => RISK_PERSONAL, 'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM],
    'local/skillupsintegration:apply' => ['riskbitmask' => RISK_DATALOSS, 'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM],
    'local/skillupsintegration:ingest' => ['riskbitmask' => RISK_PERSONAL, 'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM],
];
