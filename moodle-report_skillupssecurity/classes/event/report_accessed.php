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

namespace report_skillupssecurity\event;

use report_skillupssecurity\access;
use report_skillupssecurity\store;

/**
 * Record access to Security Center evidence.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_accessed extends \core\event\base {
    /**
     * Configure native event fields.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = store::ACTIVITY;
    }

    /**
     * Get the localized name.
     *
     * @return string
     */
    public static function get_name(): string {
        return access::text('eventaccessed');
    }

    /**
     * Write a minimal core log description.
     *
     * @return string
     */
    public function get_description(): string {
        return "Security report operation '{$this->other['operation']}' recorded for actor {$this->userid}.";
    }

    /**
     * Link to the report.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return access::url();
    }
}
