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
 * Declare the standard activity backup task.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/backup_skillupsattendance_stepslib.php');

/**
 * Moodle backup task for Attendance Pro.
 *
 * @package mod_skillupsattendance
 * @copyright 2026 SkillUps Academy
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_skillupsattendance_activity_task extends backup_activity_task {
    /**
     * Use the standard activity settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Install the activity structure step.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_skillupsattendance_activity_structure_step('attendance_structure', 'skillupsattendance.xml'));
    }

    /**
     * Preserve ordinary content links.
     *
     * @param string $content Text content
     * @return string
     */
    public static function encode_content_links($content): string {
        return $content;
    }
}
