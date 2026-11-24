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

namespace local_skillupsintegration;

/**
 * Require public DNS addresses while preserving Moodle's HTTP blocklists.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class public_security extends \core\files\curl_security_helper {
    /**
     * Activate core address checking and DNS pinning even on a fresh site.
     *
     * @return array
     */
    protected function get_blocked_hosts() {
        return array_merge(parent::get_blocked_hosts(), ['0.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16', '::/128']);
    }

    /**
     * Reject any non-global address in the set Moodle will pin.
     *
     * @param string $host Hostname
     * @return array
     */
    protected function get_host_list_by_name($host) {
        $addresses = parent::get_host_list_by_name($host);
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
                return [];
            }
        }
        return $addresses;
    }
}
