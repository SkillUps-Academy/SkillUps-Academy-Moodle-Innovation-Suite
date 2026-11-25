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
 * Site configuration and administrative entry for Communication Center.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$component = 'local_skillupscommunication';
$ADMIN->add('localplugins', new admin_externalpage(
    $component,
    get_string('pluginname', $component),
    new moodle_url('/local/skillupscommunication/index.php')
));
if ($hassiteconfig) {
    $settings = new admin_settingpage($component . '_settings', get_string('settings', $component));
    foreach (['maxrecipients' => 2000, 'batchsize' => 40] as $key => $default) {
        $settings->add(new admin_setting_configtext(
            $component . '/' . $key,
            get_string($key, $component),
            get_string($key . '_help', $component),
            $default,
            PARAM_INT
        ));
    }
    $settings->add(new admin_setting_configtextarea(
        $component . '/connectors',
        get_string('connectors', $component),
        get_string('connectors_help', $component),
        ''
    ));
    $ADMIN->add('localplugins', $settings);
    $settings = null;
}
