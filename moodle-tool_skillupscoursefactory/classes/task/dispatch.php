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

namespace tool_skillupscoursefactory\task;

use tool_skillupscoursefactory\jobs;
use tool_skillupscoursefactory\support;

/**
 * Queue the oldest due operation and recover interrupted workers.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dispatch extends \core\task\scheduled_task {
    /**
     * Localized task name.
     */
    public function get_name(): string {
        return support::text('dispatch');
    }

    /**
     * Maintain a bounded native queue.
     */
    public function execute(): void {
        global $DB;
        $rows = $DB->get_records_select(
            jobs::JOB,
            'state = :queued AND (runat <= :now OR cancelled = 1)',
            ['queued' => 'queued', 'now' => time()],
            'id ASC',
            'id',
            0,
            4
        );
        foreach ($rows as $job) {
            $task = new process_job();
            $task->set_custom_data(['jobid' => (int)$job->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }
}
