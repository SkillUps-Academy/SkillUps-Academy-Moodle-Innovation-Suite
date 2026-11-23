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

namespace local_skillupshelpdesk;

use core\hook\navigation\primary_extend;
use navigation_node;

/**
 * Primary entry point for logged-in requesters and support roles.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation {
    /**
     * Add a portal link using Moodle's primary navigation hook.
     *
     * @param primary_extend $hook Hook
     */
    public static function primary(primary_extend $hook): void {
        if (access::portal()) {
            $hook->get_primaryview()->add(
                access::text('pluginname'),
                access::url(),
                navigation_node::TYPE_CUSTOM,
                null,
                access::COMPONENT
            );
        }
    }
}
