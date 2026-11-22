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

namespace local_skillupsflow\task;

/**
 * Ad hoc entry point for a durable run.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class execute_run extends \core\task\adhoc_task {
    /**
     * Database recovery, rather than Moodle task replay, governs retry timing.
     */
    public function retry_until_success(): bool {
        return false;
    }
    /**
     * Run the eligible transition.
     */
    public function execute(): void {
        \local_skillupsflow\engine::execute((int)$this->get_custom_data()->runid);
    }
}
