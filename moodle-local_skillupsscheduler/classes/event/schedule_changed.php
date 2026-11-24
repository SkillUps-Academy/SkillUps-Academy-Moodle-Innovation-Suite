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

namespace local_skillupsscheduler\event;

use local_skillupsscheduler\scope;
use local_skillupsscheduler\store;

/**
 * Audit a schedule state change in Moodle logging.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schedule_changed extends \core\event\base {
    /**
     * Set the Moodle event type and record metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = store::SLOT;
    }

    /**
     * Return the localized display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return scope::text('eventchanged');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description(): string {
        return "Schedule revision {$this->other['revision']} ({$this->other['operation']}) for session {$this->objectid}.";
    }
}
