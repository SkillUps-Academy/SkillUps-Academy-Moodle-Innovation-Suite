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
 * SkillUps AI Copilot settings.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/lib.php');

$capability = 'local/skillupscopilot:manage';
if (has_capability($capability, context_system::instance())) {
    $component = 'local_skillupscopilot';
    $category = new admin_category($component, get_string('pluginname', $component));
    $ADMIN->add('localplugins', $category);
    $page = new admin_settingpage($component . '_settings', get_string('settings', $component), $capability);
    if ($ADMIN->fulltree) {
        $settingslist = [];
        $settingslist[] = new admin_setting_configcheckbox(
            $component . '/allowexternal',
            get_string('allowexternal', $component),
            get_string('allowexternal_desc', $component),
            0
        );
        $defaults = ['userquota' => 30, 'coursequota' => 500, 'sitequota' => 2000, 'historydays' => 30, 'meterdays' => 90];
        foreach ($defaults as $key => $default) {
            $settingslist[] = new admin_setting_configtext(
                $component . '/' . $key,
                get_string($key, $component),
                get_string($key . '_desc', $component),
                $default,
                PARAM_INT
            );
        }
        $temperatures = ['0.0', '0.2', '0.4', '0.6', '0.8', '1.0', '1.5', '2.0'];
        $tokens = [256, 512, 1024, 2048, 4096];
        foreach (\local_skillupscopilot\ai\registry::IDS as $provider) {
            $settingslist[] = new admin_setting_heading(
                $component . '/' . $provider . '_heading',
                get_string($provider, $component),
                get_string($provider . '_desc', $component)
            );
            $settingslist[] = new admin_setting_configcheckbox(
                $component . '/' . $provider . '_enabled',
                get_string('enabled', $component),
                '',
                0
            );
            $settingslist[] = new admin_setting_configtext_with_maxlength(
                $component . '/' . $provider . '_model',
                get_string('model', $component),
                get_string('model_desc', $component),
                '',
                PARAM_RAW_TRIMMED,
                45,
                200
            );
            $settingslist[] = new admin_setting_configselect(
                $component . '/' . $provider . '_temperature',
                get_string('temperature', $component),
                '',
                '0.4',
                array_combine($temperatures, $temperatures)
            );
            $settingslist[] = new admin_setting_configselect(
                $component . '/' . $provider . '_output',
                get_string('outputlimit', $component),
                '',
                '1024',
                array_combine($tokens, $tokens)
            );
        }
        foreach ($settingslist as $setting) {
            if (!$setting instanceof admin_setting_heading) {
                $setting->set_updatedcallback('local_skillupscopilot_config_updated');
            }
            $page->add($setting);
        }
    }
    $ADMIN->add($component, $page);
    $dashboard = new admin_externalpage(
        $component . '_dashboard',
        get_string('dashboard', $component),
        new moodle_url('/local/skillupscopilot/admin.php'),
        $capability
    );
    $ADMIN->add($component, $dashboard);
}
