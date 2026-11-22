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
 * Site configuration and licences.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage(
    'skillupssuccessreports',
    get_string('reports', 'local_skillupssuccess'),
    new moodle_url('/local/skillupssuccess/reports.php'),
    'local/skillupssuccess:aggregate'
));
if ($hassiteconfig) {
    $component = 'local_skillupssuccess';
    $settings = new admin_settingpage($component, get_string('pluginname', $component));
    $settings->add(new admin_setting_configcheckbox(
        $component . '/modelenabled',
        get_string('modelenabled', $component),
        get_string('modeldesc', $component),
        0
    ));
    $choices = ['retention' => [180, PARAM_INT], 'mincell' => [10, PARAM_INT],
        'programfield' => ['insightprogram', PARAM_ALPHANUMEXT]];
    foreach ($choices as $setting => [$default, $type]) {
        $settings->add(new admin_setting_configtext(
            $component . '/' . $setting,
            get_string($setting, $component),
            get_string($setting . 'desc', $component),
            $default,
            $type
        ));
    }
    $license = \local_skillupssuccess\licensing\entitlements::status();
    $siteid = \local_skillupssuccess\licensing\entitlements::site_id();
    $summary = get_string('license' . $license['state'], $component);
    if ($license['expires']) {
        $summary .= ' · ' . get_string('licenseexpires', $component, userdate($license['expires']));
    }
    $status = html_writer::tag('p', s($summary)) . html_writer::tag('p', s(get_string('licenseinstallation', $component, $siteid)));
    $settings->add(new admin_setting_heading($component . '/license', get_string('licensestatus', $component), $status));
    $ADMIN->add('localplugins', $settings);
}
