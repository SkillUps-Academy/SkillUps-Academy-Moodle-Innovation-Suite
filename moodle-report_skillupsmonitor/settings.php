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
 * Configure the reports and operational monitoring policy.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$component = 'report_skillupsmonitor';
$ADMIN->add('reports', new admin_externalpage(
    'skillupsmonitor',
    get_string('pluginname', $component),
    new moodle_url('/report/skillupsmonitor/index.php'),
    'report/skillupsmonitor:view'
));
if ($hassiteconfig) {
    $settings = new admin_settingpage('skillupsmonitorsettings', get_string('configuration', $component));
    $numbers = [
        'failures' => 2, 'recoveries' => 2, 'cronminutes' => 5, 'latencyms' => 500,
        'dueminutes' => 5, 'queuewarn' => 20, 'stale' => 15, 'retention' => 90,
    ];
    foreach ($numbers as $key => $default) {
        $settings->add(new admin_setting_configtext(
            $component . '/' . $key,
            get_string($key, $component),
            get_string($key . 'desc', $component),
            $default,
            PARAM_INT
        ));
    }
    foreach (['monitorcomponents' => 1, 'notifyenabled' => 0, 'scrapeenabled' => 0] as $key => $default) {
        $settings->add(new admin_setting_configcheckbox(
            $component . '/' . $key,
            get_string($key, $component),
            get_string($key . 'desc', $component),
            $default
        ));
    }
    $settings->add(new admin_setting_configtext(
        $component . '/recipients',
        get_string('recipients', $component),
        get_string('recipientsdesc', $component),
        '',
        PARAM_TEXT
    ));
    $checks = [];
    foreach (array_merge(\report_skillupsmonitor\collector::CHECKS, ['coverage']) as $key) {
        $checks[$key] = get_string('check_' . $key, $component);
    }
    $settings->add(new admin_setting_configmulticheckbox(
        $component . '/alertchecks',
        get_string('alertchecks', $component),
        get_string('alertchecksdesc', $component),
        array_fill_keys(array_keys($checks), 1),
        $checks
    ));
    $license = \report_skillupsmonitor\licensing\entitlements::status();
    $info = get_string('license' . $license['state'], $component);
    if ($license['expires']) {
        $info .= ' · ' . get_string('licenseexpires', $component, userdate($license['expires']));
    }
    $site = \report_skillupsmonitor\licensing\entitlements::site_id();
    $details = html_writer::tag('p', s($info)) . html_writer::tag('p', s(get_string('licenseinstallation', $component, $site)));
    $settings->add(new admin_setting_heading($component . '/license', get_string('licensestatus', $component), $details));
}
