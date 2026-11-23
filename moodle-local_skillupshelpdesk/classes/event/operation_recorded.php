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

namespace local_skillupshelpdesk\event;

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\storage;

/**
 * Moodle event for a recorded support operation.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class operation_recorded extends \core\event\base {
    /**
     * Initialize the event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = storage::AUDIT;
    }

    /**
     * Return the localized event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return access::text('eventoperation');
    }

    /**
     * Describe the operation without user-provided content.
     *
     * @return string
     */
    public function get_description(): string {
        return "Helpdesk recorded '{$this->other['operation']}' as audit entry {$this->objectid}.";
    }

    /**
     * Link to the protected portal.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return access::url();
    }

    /**
     * Exclude system ledger IDs from course backup restoration.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => storage::AUDIT, 'restore' => self::NOT_MAPPED];
    }
}
