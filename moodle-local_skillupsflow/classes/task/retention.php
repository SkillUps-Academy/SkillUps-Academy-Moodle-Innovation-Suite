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
 * Expire finished execution data using bounded transactions.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class retention extends \core\task\scheduled_task {
    /**
     * Localized scheduled task label.
     */
    public function get_name(): string {
        return get_string('taskretention', 'local_skillupsflow');
    }
    /**
     * Remove up to 1000 finished runs and their logs.
     */
    public function execute(): void {
        global $DB;
        $days = max(2, min(365, (int)(get_config('local_skillupsflow', 'retentiondays') ?: 90)));
        $runs = $DB->get_records_select(
            'local_skillupsflow_run',
            'timemodified < ? AND status IN (?, ?, ?)',
            [time() - $days * DAYSECS, 'completed', 'failed', 'cancelled'],
            'timemodified',
            'id',
            0,
            1000
        );
        if (!$runs) {
            return;
        }
        $ids = array_keys($runs);
        $tx = $DB->start_delegated_transaction();
        $DB->delete_records_list('local_skillupsflow_log', 'runid', $ids);
        $DB->delete_records_list('local_skillupsflow_run', 'id', $ids);
        $tx->allow_commit();
    }
}
