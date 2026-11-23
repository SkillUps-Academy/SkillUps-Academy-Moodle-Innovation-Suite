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

namespace local_skillupshelpdesk\admin;

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\calendar;

/**
 * Validate labor schedule syntax before persisting the site setting.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validated_textarea extends \admin_setting_configtextarea {
    /**
     * Validate the corresponding local business-time setting.
     *
     * @param string $data Submitted value
     * @return bool|string
     */
    public function validate($data) {
        try {
            if ($this->name === 'hours') {
                calendar::parse_hours($data);
            } else {
                calendar::parse_holidays($data);
            }
            return true;
        } catch (\InvalidArgumentException $failure) {
            return access::text('invalid' . $this->name);
        }
    }
}
