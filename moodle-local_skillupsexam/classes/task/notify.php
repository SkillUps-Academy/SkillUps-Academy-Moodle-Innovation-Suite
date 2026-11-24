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

namespace local_skillupsexam\task;

/**
 * Deliver scheduled exam notices.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notify extends \core\task\scheduled_task {
    /**
     * Name this scheduled task in the user's language.
     *
     * @return string Localized name
     */
    public function get_name(): string {
        return \local_skillupsexam\scope::text('tasknotify');
    }

    /**
     * Process the notification queue.
     */
    public function execute(): void {
        \local_skillupsexam\notifications::run();
    }
}
