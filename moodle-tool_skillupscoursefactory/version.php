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
 * Course Factory plugin version.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$plugin->component = 'tool_skillupscoursefactory';
$plugin->version = 2026112300;
$plugin->requires = 2025041400;
$plugin->release = '0.9.0-beta.1';
$plugin->maturity = MATURITY_BETA;
$plugin->dependencies = ['enrol_manual' => ANY_VERSION, 'dataformat_csv' => ANY_VERSION, 'dataformat_excel' => ANY_VERSION];
