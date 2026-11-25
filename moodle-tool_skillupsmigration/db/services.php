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
 * Register an explicitly authorized Moodle external service for source operations.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_skillupsmigration_source' => [
        'classname' => '\tool_skillupsmigration\external\source_api',
        'methodname' => 'execute',
        'description' => 'Inventory and authenticated bounded course backup transfers for an authorized migration.',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'tool/skillupsmigration:export,moodle/backup:backupcourse',
    ],
];

$services = [
    'SkillUps Course Migration origin' => [
        'shortname' => 'tool_skillupsmigration_origin',
        'functions' => ['core_webservice_get_site_info', 'tool_skillupsmigration_source'],
        'restrictedusers' => 1,
        'enabled' => 0,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
