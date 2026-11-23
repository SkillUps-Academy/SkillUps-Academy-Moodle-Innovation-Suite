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

namespace local_skillupsgamification\event;

use local_skillupsgamification\access;
use local_skillupsgamification\store;

/**
 * Audit event for course gamification administration.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class operation_recorded extends \core\event\base {
    /**
     * Describe basic event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = store::AUDIT;
    }

    /**
     * Return the event label.
     *
     * @return string
     */
    public static function get_name(): string {
        return access::text('operationrecorded');
    }

    /**
     * Return an operational description.
     *
     * @return string
     */
    public function get_description(): string {
        return "Gamification stored operation '{$this->other['action']}' in entry {$this->objectid}.";
    }

    /**
     * Link to the applicable course dashboard.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return access::url('index', (int)$this->courseid);
    }

    /**
     * Details of the event object mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => store::AUDIT, 'restore' => self::NOT_MAPPED];
    }
}
