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

namespace report_skillupsmonitor\event;

/**
 * Audit sensitive dashboard and lifecycle activity.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class monitor_operation extends \core\event\base {
    /**
     * Moodle log metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localized event name.
     */
    public static function get_name(): string {
        return get_string('eventmonitor', 'report_skillupsmonitor');
    }

    /**
     * Describe the operation without diagnostic details or credentials.
     */
    public function get_description(): string {
        return "Monitor operation '{$this->other['operation']}' referred to record {$this->other['reference']}.";
    }
}
