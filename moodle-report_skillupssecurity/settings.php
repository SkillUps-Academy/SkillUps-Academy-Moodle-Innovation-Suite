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
 * Site-level audit policy and report registration.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$component = 'report_skillupssecurity';
$ADMIN->add('reports', new admin_externalpage(
    'skillupssecurity',
    get_string('pluginname', $component),
    new moodle_url('/report/skillupssecurity/index.php'),
    'report/skillupssecurity:view'
));
if ($hassiteconfig) {
    $settings = new admin_settingpage('skillupssecuritypolicy', get_string('settings', $component));
    foreach (['inactivedays' => 90, 'accountlimit' => 1000, 'assignmentlimit' => 2000, 'retention' => 90] as $key => $value) {
        $settings->add(new admin_setting_configtext(
            $component . '/' . $key,
            get_string($key, $component),
            get_string($key . 'desc', $component),
            $value,
            PARAM_INT
        ));
    }
    $settings->add(new admin_setting_configcheckbox(
        $component . '/notifyenabled',
        get_string('notifyenabled', $component),
        get_string('notifyenableddesc', $component),
        0
    ));
    $settings->add(new admin_setting_configselect(
        $component . '/notifyseverity',
        get_string('notifyseverity', $component),
        get_string('notifyseveritydesc', $component),
        'high',
        ['high' => get_string('severity_high', $component), 'medium' => get_string('severity_medium', $component)]
    ));
    $status = \report_skillupssecurity\licensing\entitlements::status();
    $settings->add(new admin_setting_heading(
        $component . '/license',
        get_string('plan', $component, get_string('plan_' . $status['state'], $component)),
        $status['expires'] ? userdate($status['expires']) : ''
    ));
    $settings->add(new admin_setting_configtext(
        $component . '/recipients',
        get_string('recipients', $component),
        get_string('recipientsdesc', $component),
        '',
        PARAM_TEXT
    ));
}
