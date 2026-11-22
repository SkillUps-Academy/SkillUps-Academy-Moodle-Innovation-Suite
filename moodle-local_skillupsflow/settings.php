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
 * SkillUps Flow Automation settings.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$component = 'local_skillupsflow';
$category = new admin_category($component, get_string('pluginname', $component));
$ADMIN->add('localplugins', $category);
$ADMIN->add($component, new admin_externalpage(
    $component . '_dashboard',
    get_string('dashboard', $component),
    new moodle_url('/local/skillupsflow/index.php'),
    'local/skillupsflow:manage'
));
$ADMIN->add($component, new admin_externalpage(
    $component . '_approvals',
    get_string('approvals', $component),
    new moodle_url('/local/skillupsflow/approvals.php'),
    'local/skillupsflow:approve'
));
$page = new admin_settingpage($component . '_settings', get_string('settings', $component), 'local/skillupsflow:manage');
if ($ADMIN->fulltree) {
    $page->add(new admin_setting_configtext(
        $component . '/retentiondays',
        get_string('retentiondays', $component),
        get_string('retentiondaysdesc', $component),
        90,
        PARAM_INT
    ));
    $page->add(new admin_setting_configtext(
        $component . '/dailymax',
        get_string('dailymax', $component),
        get_string('dailymaxdesc', $component),
        250,
        PARAM_INT
    ));
}
$ADMIN->add($component, $page);
