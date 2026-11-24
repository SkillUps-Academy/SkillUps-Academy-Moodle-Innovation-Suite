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
 * Register a restricted, disabled-by-default ingestion service.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_skillupsintegration_status' => [
        'classname' => \local_skillupsintegration\external\status::class,
        'description' => 'Get the status of a submitted integration run.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/skillupsintegration:ingest',
    ],
    'local_skillupsintegration_submit' => [
        'classname' => \local_skillupsintegration\external\submit::class,
        'description' => 'Submit an integration event for preview.',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'local/skillupsintegration:ingest',
    ],
];

$services = [
    'SkillUps Integration Hub ingestion' => [
        'functions' => ['local_skillupsintegration_submit', 'local_skillupsintegration_status'],
        'requiredcapability' => 'local/skillupsintegration:ingest',
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'local_skillupsintegration_ingest',
    ],
];
