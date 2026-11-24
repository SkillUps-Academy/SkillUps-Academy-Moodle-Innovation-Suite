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
 * Administration entry and notification preference.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('localplugins', new admin_externalpage(
    'local_skillupsexam',
    get_string('pluginname', 'local_skillupsexam'),
    new moodle_url('/local/skillupsexam/index.php'),
    'local/skillupsexam:catalog',
));
if ($hassiteconfig) {
    $page = new admin_settingpage('local_skillupsexam_settings', get_string('settings', 'local_skillupsexam'));
    $page->add(new admin_setting_configcheckbox(
        'local_skillupsexam/notifications',
        get_string('notifications', 'local_skillupsexam'),
        get_string('notificationhelp', 'local_skillupsexam'),
        1,
    ));
    $ADMIN->add('localplugins', $page);
}
