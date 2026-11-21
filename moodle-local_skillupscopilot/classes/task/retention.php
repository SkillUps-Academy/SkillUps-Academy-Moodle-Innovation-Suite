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

namespace local_skillupscopilot\task;

/**
 * Remove expired records and close jobs that never completed.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class retention extends \core\task\scheduled_task {
    /**
     * Get the localized task name.
     */
    public function get_name(): string {
        return get_string('taskretention', 'local_skillupscopilot');
    }

    /**
     * Apply bounded batch cleanup and configured retention.
     */
    public function execute(): void {
        global $DB;
        $now = time();
        $turn = 'local_skillupscopilot_turn';
        $meter = 'local_skillupscopilot_meter';
        $DB->set_field_select(
            $turn,
            'status',
            'failed',
            '(status = :running AND timemodified < :runningcutoff) OR
            (status = :pending AND timemodified < :pendingcutoff)',
            ['running' => 'running', 'runningcutoff' => $now - HOURSECS,
            'pending' => 'pending',
            'pendingcutoff' => $now - DAYSECS]
        );
        $DB->set_field_select(
            $meter,
            'status',
            'failed',
            '(status = :running AND timemodified < :runningcutoff) OR
            (status = :pending AND timemodified < :pendingcutoff)',
            ['running' => 'running', 'runningcutoff' => $now - HOURSECS,
            'pending' => 'pending',
            'pendingcutoff' => $now - DAYSECS]
        );

        $days = max(1, min(365, (int)get_config('local_skillupscopilot', 'historydays')));
        $cutoff = $now - $days * DAYSECS;
        $factory = \core\lock\lock_config::get_lock_factory('local_skillupscopilot');
        $lock = $factory->get_lock('quota', 10);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_skillupscopilot');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $DB->delete_records_select($turn, 'timemodified < :cutoff', ['cutoff' => $cutoff]);
                $DB->delete_records_select(
                    'local_skillupscopilot_thread',
                    "timemodified < :cutoff AND id NOT IN (SELECT threadid FROM {{$turn}})",
                    ['cutoff' => $cutoff]
                );
                $meterdays = max(1, min(365, (int)get_config('local_skillupscopilot', 'meterdays')));
                $DB->delete_records_select($meter, 'timecreated < :cutoff', ['cutoff' => $now - $meterdays * DAYSECS]);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }
}
