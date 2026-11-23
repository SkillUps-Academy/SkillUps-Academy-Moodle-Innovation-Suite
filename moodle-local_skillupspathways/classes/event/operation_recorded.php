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

namespace local_skillupspathways\event;

use local_skillupspathways\access;
use local_skillupspathways\store;

/**
 * An auditable pathway administration event.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class operation_recorded extends \core\event\base {
    /**
     * Set Moodle event metadata.
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
     * Describe the action without copying academic data.
     *
     * @return string
     */
    public function get_description(): string {
        return "Pathway audit entry {$this->objectid} recorded '{$this->other['operation']}'.";
    }

    /**
     * Link authorized users to the pathway.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return access::url('index', (int)$this->courseid, ['pathid' => $this->other['pathid']]);
    }

    /**
     * Exclude audit IDs from course restore mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => store::AUDIT, 'restore' => self::NOT_MAPPED];
    }
}
