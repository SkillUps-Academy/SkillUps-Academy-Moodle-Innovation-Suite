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

namespace local_skillupscompetency\event;

use local_skillupscompetency\access;
use local_skillupscompetency\store;

/**
 * An assessment was submitted or reviewed.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assessment_changed extends \core\event\base {
    /**
     * Set event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = store::ASSESS;
    }

    /**
     * Return the localized Moodle name.
     *
     * @return string
     */
    public static function get_name(): string {
        return access::text('eventassessment');
    }

    /**
     * Describe the assessment state change for the Moodle logs.
     *
     * @return string
     */
    public function get_description(): string {
        return "Assessment {$this->objectid} changed to {$this->other['status']}.";
    }
}
