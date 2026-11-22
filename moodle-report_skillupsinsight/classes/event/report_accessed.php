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

namespace report_skillupsinsight\event;

/**
 * Audit snapshot queries, exports and scheduling, without result values.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_accessed extends \core\event\base {
    /**
     * Initialize the observation metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Localized name in Moodle logs.
     */
    public static function get_name(): string {
        return get_string('eventreport', 'report_skillupsinsight');
    }

    /**
     * Limit logs to the operation and period reference.
     */
    public function get_description(): string {
        return "User {$this->userid} ran {$this->other['action']} for period {$this->other['period']}.";
    }
}
