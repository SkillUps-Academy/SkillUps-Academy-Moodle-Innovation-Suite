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

namespace local_skillupscertify\event;

use local_skillupscertify\access;
use local_skillupscertify\store;
use moodle_url;

/**
 * Log sensitive credential actions without exposing tokens or names in event data.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class operation_recorded extends \core\event\base {
    /**
     * Set standard event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = store::AUDIT;
    }

    /**
     * Get the human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return access::text('eventrecorded');
    }

    /**
     * Get the safe technical log message.
     *
     * @return string
     */
    public function get_description(): string {
        return "Certificate operation '{$this->other['action']}' recorded with reference {$this->other['reference']}.";
    }

    /**
     * Link to the scoped registry.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return access::url('index', ['courseid' => $this->courseid]);
    }
}
