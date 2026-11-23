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
 * Configure public verification and display commercial entitlement status.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupscertify\access;
use local_skillupscertify\licensing\entitlements;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(access::C, access::text('pluginname'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            access::C . '/publicverification',
            access::text('publicverification'),
            access::text('publicverificationdesc'),
            1
        ));
        foreach (['batchdays' => 90, 'auditdays' => 730] as $name => $default) {
            $settings->add(new admin_setting_configtext(
                access::C . '/' . $name,
                access::text($name),
                access::text($name . 'desc'),
                $default,
                PARAM_INT
            ));
        }
        $license = entitlements::status();
        $settings->add(new admin_setting_heading(
            access::C . '/licensestatus',
            access::text('licensestatus', access::text('license_' . $license['state'])),
            $license['expires'] ? access::text('licenseexpiry', userdate($license['expires'])) : ''
        ));
    }
}
