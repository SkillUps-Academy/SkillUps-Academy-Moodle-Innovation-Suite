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

namespace local_skillupsprivacy\event;

use local_skillupsprivacy\access;
use local_skillupsprivacy\store;

/**
 * Emit minimal operational references to the Moodle event log.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class case_activity extends \core\event\base {
    /**
     * Map the local audit row.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = store::AUDIT;
    }

    /**
     * Get a localized event title.
     *
     * @return string
     */
    public static function get_name(): string {
        return access::text('eventcaseactivity');
    }

    /**
     * Give the native log a minimal description.
     *
     * @return string
     */
    public function get_description(): string {
        return "Privacy Manager operation '{$this->other['operation']}' logged for actor {$this->userid}.";
    }

    /**
     * Open the authorized dashboard.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return access::url();
    }
}
