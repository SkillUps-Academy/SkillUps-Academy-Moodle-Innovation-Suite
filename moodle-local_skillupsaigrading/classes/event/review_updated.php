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

namespace local_skillupsaigrading\event;

/**
 * Audit a review transition in Moodle logs without storing source text.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review_updated extends \core\event\base {
    /**
     * Event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_skillupsaigrading_job';
    }

    /**
     * Event label.
     */
    public static function get_name(): string {
        return get_string('eventreviewupdated', 'local_skillupsaigrading');
    }

    /**
     * Fixed audit fields only.
     */
    public function get_description(): string {
        return "User {$this->userid} recorded '{$this->other['action']}' for review {$this->objectid}.";
    }

    /**
     * Job IDs are local audit records and cannot be mapped on restore.
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_skillupsaigrading_job', 'restore' => self::NOT_MAPPED];
    }
}
