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
 * Site-wide configuration and provider setup.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $component = 'local_skillupsaigrading';
    $settings = new admin_settingpage($component, get_string('pluginname', $component));
    foreach (['enableai', 'allowexternal'] as $setting) {
        $settings->add(new admin_setting_configcheckbox(
            $component . '/' . $setting,
            get_string($setting, $component),
            get_string($setting . '_desc', $component),
            0
        ));
    }
    foreach (['dailylimit' => 50, 'retention' => 30] as $setting => $default) {
        $settings->add(new admin_setting_configtext(
            $component . '/' . $setting,
            get_string($setting, $component),
            get_string($setting . '_desc', $component),
            $default,
            PARAM_INT
        ));
    }
    foreach (\local_skillupsaigrading\ai\registry::IDS as $provider) {
        $prefix = $component . '/' . $provider . '_';
        $settings->add(new admin_setting_heading(
            $prefix . 'heading',
            get_string('provider' . $provider, $component),
            get_string('providerconf', $component)
        ));
        $settings->add(new admin_setting_configcheckbox($prefix . 'enabled', get_string('provider_enabled', $component), '', 0));
        foreach (
            ['model' => ['', PARAM_TEXT], 'modelversion' => ['', PARAM_TEXT], 'temperature' => ['0.2', PARAM_FLOAT],
                'output' => [3072, PARAM_INT]] as $name => [$default, $type]
        ) {
            $title = get_string('provider_' . $name, $component);
            $settings->add(new admin_setting_configtext($prefix . $name, $title, '', $default, $type));
        }
    }
    $license = \local_skillupsaigrading\licensing\entitlements::status();
    $installation = \local_skillupsaigrading\licensing\entitlements::site_id();
    $status = get_string('license' . $license['state'], $component);
    if ($license['expires']) {
        $status .= ' · ' . get_string('licenseexpires', $component, userdate($license['expires']));
    }
    $settings->add(new admin_setting_heading(
        $component . '/licensestatus',
        get_string('licensestatus', $component),
        html_writer::tag('p', s($status)) . html_writer::tag('p', s(get_string('licenseinstallation', $component, $installation)))
    ));
    $ADMIN->add('localplugins', $settings);
}
