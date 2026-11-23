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
 * Site configuration for working hours, retention and escalation.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\admin\validated_textarea;
use local_skillupshelpdesk\calendar;
use local_skillupshelpdesk\licensing\entitlements;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $component = access::COMPONENT;
    $settings = new admin_settingpage($component, access::text('pluginname'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $license = entitlements::status();
        $status = access::text('license_' . $license['state']);
        $settings->add(new admin_setting_heading(
            "$component/licensestatus",
            access::text('licensestatus', $status),
            $license['expires'] ? access::text('licenseexpiry', userdate($license['expires'])) : ''
        ));
    }
    $settings->add(new admin_setting_configselect(
        "$component/timezone",
        access::text('timezone'),
        access::text('timezone_desc'),
        'UTC',
        core_date::get_list_of_timezones('UTC')
    ));
    $settings->add(new validated_textarea(
        "$component/hours",
        access::text('hours'),
        access::text('hours_desc'),
        calendar::DEFAULT_HOURS,
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new validated_textarea(
        "$component/holidays",
        access::text('holidays'),
        access::text('holidays_desc'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configtext(
        "$component/escalationuserid",
        access::text('escalationuserid'),
        access::text('escalationuserid_desc'),
        0,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configcheckbox(
        "$component/notifications",
        access::text('notifications'),
        access::text('notifications_desc'),
        1
    ));
    $settings->add(new admin_setting_configselect(
        "$component/retention",
        access::text('retention'),
        access::text('retention_desc'),
        365,
        [30 => 30, 90 => 90, 180 => 180, 365 => 365, 730 => 730]
    ));
}
