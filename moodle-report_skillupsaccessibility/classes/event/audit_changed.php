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

namespace report_skillupsaccessibility\event;

use report_skillupsaccessibility\scope;
use report_skillupsaccessibility\store;

/**
 * Log an accessibility audit lifecycle event.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audit_changed extends \core\event\base {
    /**
     * Define the audit event category.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = store::JOB;
    }

    /**
     * Return the localized Moodle name.
     *
     * @return string
     */
    public static function get_name(): string {
        return scope::text('eventaudit');
    }

    /**
     * Describe the audit transition for Moodle logs.
     *
     * @return string
     */
    public function get_description(): string {
        return "Accessibility audit {$this->objectid} reached {$this->other['status']}.";
    }
}
