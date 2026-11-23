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
 * Configure the Smart Enrollment Manager site capacity.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_skillupsenrollment\support;
use tool_skillupsenrollment\ui;

$ADMIN->add('courses', new admin_externalpage(
    'skillupsenrollment',
    support::text('pluginname'),
    ui::url('index'),
    'tool/skillupsenrollment:operate'
));
if ($hassiteconfig) {
    $settings = new admin_settingpage('skillupsenrollmentsettings', support::text('settings'));
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configselect(
            support::C . '/maxbatch',
            support::text('maxbatch'),
            support::text('maxbatchdesc'),
            500,
            array_combine(support::SIZES, support::SIZES)
        ));
        $settings->add(new admin_setting_configtext(
            support::C . '/retention',
            support::text('retention'),
            support::text('retentiondesc'),
            180,
            PARAM_INT
        ));
        $license = \tool_skillupsenrollment\licensing\entitlements::status();
        $details = support::text('license_' . $license['state']);
        if ($license['expires']) {
            $details .= ' · ' . support::text('expires', userdate($license['expires']));
        }
        $site = \tool_skillupsenrollment\licensing\entitlements::site_id();
        $settings->add(new admin_setting_heading(
            support::C . '/license',
            support::text('license'),
            html_writer::tag('p', s($details)) . html_writer::tag('p', s(support::text('installationid', $site)))
        ));
    }
    $ADMIN->add('courses', $settings);
}
