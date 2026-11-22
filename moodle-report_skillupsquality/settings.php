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
 * Configure the institutional audit policy and site report.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use report_skillupsquality\catalog;
use report_skillupsquality\licensing\entitlements;
use report_skillupsquality\scope;

$ADMIN->add('reports', new admin_externalpage(
    'skillupsquality',
    scope::text('pluginname'),
    new moodle_url('/report/skillupsquality/index.php'),
    'report/skillupsquality:batch'
));
if ($hassiteconfig) {
    $settings = new admin_settingpage('skillupsqualitysettings', scope::text('settings'));
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            scope::COMPONENT . '/retention',
            scope::text('retention'),
            scope::text('retentionhelp'),
            180,
            PARAM_INT
        ));
        $options = [];
        foreach (array_keys(catalog::WEIGHTS) as $severity) {
            $options[$severity] = scope::text('severity_' . $severity);
        }
        foreach (catalog::rules() as $code => $rule) {
            $metadata = $rule->definition();
            $settings->add(new admin_setting_configcheckbox(
                scope::COMPONENT . '/enable_' . $code,
                scope::text($metadata['description']),
                scope::text($metadata['condition']),
                1
            ));
            $settings->add(new admin_setting_configselect(
                scope::COMPONENT . '/severity_' . $code,
                scope::text('severityfor', scope::text($metadata['description'])),
                '',
                $metadata['severity'],
                $options
            ));
        }
        $license = entitlements::status();
        $status = scope::text('license_' . $license['state']);
        if ($license['expires']) {
            $status .= ' · ' . scope::text('licenseexpires', userdate($license['expires']));
        }
        $installation = scope::text('licenseinstallation', entitlements::site_id());
        $settings->add(new admin_setting_heading(
            scope::COMPONENT . '/licensing',
            scope::text('licensing'),
            html_writer::tag('p', s($status)) . html_writer::tag('p', s($installation))
        ));
    }
}
