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
 * Configure capacity, enrollment roles and factory access.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_skillupscoursefactory\support;
use tool_skillupscoursefactory\ui;

$ADMIN->add('courses', new admin_externalpage(
    'skillupscoursefactory',
    support::text('pluginname'),
    ui::url('index'),
    'tool/skillupscoursefactory:operate'
));
if ($hassiteconfig) {
    $settings = new admin_settingpage('skillupscoursefactorysettings', support::text('settings'));
    if ($ADMIN->fulltree) {
        $options = array_combine(support::SIZES, support::SIZES);
        $settings->add(new admin_setting_configselect(
            support::C . '/maxbatch',
            support::text('maxbatch'),
            support::text('maxbatchdesc'),
            50,
            $options
        ));
        $settings->add(new admin_setting_configtext(
            support::C . '/retention',
            support::text('retention'),
            support::text('retentiondesc'),
            180,
            PARAM_INT
        ));
        $roles = [0 => support::text('roledefault')];
        foreach (get_all_roles() as $role) {
            if (in_array(CONTEXT_COURSE, get_role_contextlevels($role->id))) {
                $roles[$role->id] = format_string($role->name ?: $role->shortname);
            }
        }
        foreach (['teacherrole', 'coordinatorrole'] as $field) {
            $settings->add(new admin_setting_configselect(support::C . '/' . $field, support::text($field), '', 0, $roles));
        }
        $settings->add(new admin_setting_configtext(
            support::C . '/periodfield',
            support::text('periodfield'),
            support::text('periodfielddesc'),
            '',
            PARAM_ALPHANUMEXT
        ));
        $status = \tool_skillupscoursefactory\licensing\entitlements::status();
        $details = support::text('license_' . $status['state']);
        if ($status['expires']) {
            $details .= ' · ' . support::text('expires', userdate($status['expires']));
        }
        $installation = \tool_skillupscoursefactory\licensing\entitlements::site_id();
        $settings->add(new admin_setting_heading(
            support::C . '/license',
            support::text('license'),
            html_writer::tag('p', s($details)) . html_writer::tag('p', s(support::text('installationid', $installation)))
        ));
    }
    $ADMIN->add('courses', $settings);
}
