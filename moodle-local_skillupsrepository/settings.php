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
 * Site administration links and upload limits.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$name = get_string('pluginname', 'local_skillupsrepository');
$ADMIN->add('localplugins', new admin_externalpage(
    'local_skillupsrepository',
    $name,
    new moodle_url('/local/skillupsrepository/index.php')
));
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_skillupsrepository_settings', get_string('settings', 'local_skillupsrepository'));
    $settings->add(new admin_setting_configtext(
        'local_skillupsrepository/maxbytes',
        get_string('maxbytes', 'local_skillupsrepository'),
        get_string('maxbytes_help', 'local_skillupsrepository'),
        52428800,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_skillupsrepository/allowpublic',
        get_string('allowpublic', 'local_skillupsrepository'),
        get_string('allowpublic_help', 'local_skillupsrepository'),
        0
    ));
    $ADMIN->add('localplugins', $settings);
    $settings = null;
}
