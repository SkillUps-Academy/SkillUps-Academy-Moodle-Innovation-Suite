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

namespace tool_skillupsenrollment\event;

/**
 * Moodle audit trail for Smart Enrollment Manager operations.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrollment_operation extends \core\event\base {
    /**
     * Declare event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return a localized label.
     */
    public static function get_name(): string {
        return get_string('eventoperation', 'tool_skillupsenrollment');
    }

    /**
     * Summarize the operation without staff or course contents.
     */
    public function get_description(): string {
        return "Smart Enrollment Manager job {$this->other['jobid']} operation '{$this->other['operation']}'.";
    }
}
