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
 * Configure scheduled auditing and provide a site report entry.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage(
    'report_skillupsaccessibility',
    get_string('pluginname', 'report_skillupsaccessibility'),
    new moodle_url('/report/skillupsaccessibility/index.php'),
    'report/skillupsaccessibility:bulk',
));
if ($hassiteconfig) {
    $page = new admin_settingpage('report_skillupsaccessibility_settings', get_string('settings', 'report_skillupsaccessibility'));
    $page->add(new admin_setting_configtext(
        'report_skillupsaccessibility/scheduleuserid',
        get_string('scheduleuserid', 'report_skillupsaccessibility'),
        get_string('scheduleuserhelp', 'report_skillupsaccessibility'),
        0,
        PARAM_INT,
    ));
    $page->add(new admin_setting_configtext(
        'report_skillupsaccessibility/intervaldays',
        get_string('intervaldays', 'report_skillupsaccessibility'),
        get_string('intervalhelp', 'report_skillupsaccessibility'),
        7,
        PARAM_INT,
    ));
    $ADMIN->add('reports', $page);
}
