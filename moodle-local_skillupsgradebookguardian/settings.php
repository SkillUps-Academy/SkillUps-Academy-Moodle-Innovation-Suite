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

/** Site configuration.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupsgradebookguardian\licensing\entitlements;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $component = 'local_skillupsgradebookguardian';
    $settings = new admin_settingpage($component, get_string('pluginname', $component));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $license = entitlements::status();
        $status = get_string('licensestate_' . $license['state'], $component);
        $description = $license['expires'] ? get_string('licenseexpires', $component, userdate($license['expires'])) : '';
        $settings->add(new admin_setting_heading(
            "$component/licensestatus",
            get_string('licensestatus', $component, $status),
            $description
        ));
    }
    $settings->add(new admin_setting_configselect(
        "$component/retention",
        get_string('retention', $component),
        get_string('retention_help', $component),
        180,
        [30 => 30, 90 => 90, 180 => 180, 365 => 365]
    ));
    $settings->add(new admin_setting_configselect(
        "$component/scanlimit",
        get_string('scanlimit', $component),
        get_string('scanlimit_help', $component),
        5000,
        [1000 => 1000, 2500 => 2500, 5000 => 5000, 10000 => 10000]
    ));
    $settings->add(new admin_setting_configcheckbox(
        "$component/repairenabled",
        get_string('repairenabled', $component),
        get_string('repairenabled_help', $component),
        1
    ));
}
