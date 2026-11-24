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

/**
 * Declare the standard activity restore task.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/restore_skillupsattendance_stepslib.php');

/**
 * Restore Attendance Pro via Moodle.
 *
 * @package mod_skillupsattendance
 * @copyright 2026 SkillUps Academy
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class restore_skillupsattendance_activity_task extends restore_activity_task {
    /**
     * Restore the standard activity settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Install the activity structure step.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_skillupsattendance_activity_structure_step('attendance_structure', 'skillupsattendance.xml'));
    }

    /**
     * Register fields that contain linked Moodle content.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [new restore_decode_content('skillupsattendance', ['intro'], 'skillupsattendance')];
    }

    /**
     * Declare link rules for the activity landing page.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [];
    }

    /**
     * Define the optional log conversion rules.
     *
     * @return array
     */
    public static function define_restore_log_rules(): array {
        return [];
    }

    /**
     * Define the optional course log conversion rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course(): array {
        return [];
    }
}
