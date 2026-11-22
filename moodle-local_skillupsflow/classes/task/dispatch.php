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

use local_skillupsflow\store;

/**
 * Admit due schedules and recover jobs whose dispatch lease expired.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dispatch extends \core\task\scheduled_task {
    /**
     * Localized scheduled task label.
     */
    public function get_name(): string {
        return get_string('taskdispatch', 'local_skillupsflow');
    }

    /**
     * Bound work per cron invocation.
     */
    public function execute(): void {
        global $DB;
        $now = time();
        $flows = $DB->get_records_select(
            'local_skillupsflow_flow',
            'active > 0 AND nextdue > 0 AND nextdue <= ?',
            [$now],
            'nextdue, id',
            '*',
            0,
            100
        );
        foreach ($flows as $flow) {
            try {
                store::admit($flow->id, $flow->triggertype, (string)$flow->nextdue);
            } catch (\Throwable $exception) {
                mtrace('SkillUps Flow timer deferred: ' . $flow->id);
            }
        }
        $sql = "SELECT r.id, r.flowid
                  FROM {local_skillupsflow_run} r
                  JOIN {local_skillupsflow_flow} f ON f.id = r.flowid
                 WHERE f.active > 0
                       AND r.status IN (?, ?, ?) AND r.wake > 0 AND r.wake <= ?
              ORDER BY r.wake, r.id";
        $runs = $DB->get_records_sql($sql, [...store::WORK, $now], 0, 100);
        foreach ($runs as $run) {
            try {
                self::recover($run, $now);
            } catch (\Throwable $exception) {
                mtrace('SkillUps Flow recovery deferred: ' . $run->id);
            }
        }
    }

    /**
     * Refresh the dispatch lease and the queue in a single commit.
     */
    private static function recover(\stdClass $run, int $now): void {
        global $DB;
        $lock = store::lock($run->flowid, 1);
        try {
            $current = $DB->get_record('local_skillupsflow_run', ['id' => $run->id]);
            if (!$current || !in_array($current->status, store::WORK, true) || !$current->wake || $current->wake > $now) {
                return;
            }
            $tx = $DB->start_delegated_transaction();
            try {
                $DB->set_field('local_skillupsflow_run', 'wake', $now + 90, ['id' => $run->id]);
                store::queue($run->id, $now);
                $tx->allow_commit();
            } catch (\Throwable $exception) {
                $tx->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }
}
