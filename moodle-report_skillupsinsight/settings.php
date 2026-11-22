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
 * Insight site settings.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage(
    'skillupsinsight',
    get_string('pluginname', 'report_skillupsinsight'),
    new moodle_url('/report/skillupsinsight/index.php'),
    'report/skillupsinsight:view'
));
if ($hassiteconfig) {
    $component = 'report_skillupsinsight';
    $settings = new admin_settingpage('skillupsinsightsettings', get_string('configuration', $component));
    $settings->add(new admin_setting_configtext(
        $component . '/programfield',
        get_string('programfield', $component),
        get_string('programfielddesc', $component),
        'insightprogram',
        PARAM_ALPHANUMEXT
    ));
    $settings->add(new admin_setting_configtext(
        $component . '/smallcell',
        get_string('smallcell', $component),
        get_string('smallcelldesc', $component),
        5,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        $component . '/retention',
        get_string('retention', $component),
        get_string('retentiondesc', $component),
        90,
        PARAM_INT
    ));
    $license = \report_skillupsinsight\licensing\entitlements::status();
    $status = html_writer::tag('p', s(get_string('license' . $license['state'], $component)));
    if ($license['expires']) {
        $status .= html_writer::tag('p', s(get_string('licenseexpires', $component, userdate($license['expires']))));
    }
    $installation = \report_skillupsinsight\licensing\entitlements::site_id();
    $status .= html_writer::tag('p', s(get_string('licenseinstallation', $component, $installation)));
    $settings->add(new admin_setting_heading($component . '/licensestatus', get_string('licensestatus', $component), $status));
}
