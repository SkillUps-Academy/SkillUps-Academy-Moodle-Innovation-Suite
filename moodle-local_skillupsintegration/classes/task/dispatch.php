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

namespace local_skillupsintegration\task;

use local_skillupsintegration\access;
use local_skillupsintegration\problem;
use local_skillupsintegration\runs;
use local_skillupsintegration\store;

/**
 * Recover unqueued work and enforce retention on staged data.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dispatch extends \core\task\scheduled_task {
    /**
     * Return the scheduled task name.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('taskdispatch');
    }

    /**
     * Recover runnable work and purge bounded sets of expired source payloads.
     */
    public function execute(): void {
        global $DB;
        $now = time();
        $work = "state IN ('queued', 'applying') AND nextat <= :now";
        $ready = $DB->get_records_select(store::RUN, $work, ['now' => $now], 'nextat ASC, id ASC', 'id', 0, 50);
        foreach ($ready as $row) {
            runs::enqueue((int)$row->id);
        }
        $cutoff = $now - runs::lifetime();
        $where = 'timecreated <= :cutoff AND sealed <> -1';
        $olds = $DB->get_records_select(store::RUN, $where, ['cutoff' => $cutoff], 'timecreated, id', 'id,sourceid', 0, 25);
        foreach ($olds as $row) {
            try {
                store::locked("source:$row->sourceid", static function () use ($DB, $row): void {
                    $run = runs::get($row->id);
                    store::transaction(static function () use ($DB, $run): void {
                        $DB->delete_records(store::ITEM, ['runid' => $run->id]);
                        $state = in_array($run->state, ['queued', 'ready', 'applying'], true) ? 'expired' : $run->state;
                        store::change($run->id, ['state' => $state, 'continuation' => null, 'sealed' => -1]);
                    });
                });
            } catch (problem $failure) {
                if ($failure->reason !== 'busy') {
                    throw $failure;
                }
            }
        }
        $days = max(30, min(730, (int)(get_config(access::C, 'auditdays') ?: 365)));
        $params = ['cutoff' => $now - $days * DAYSECS];
        $DB->delete_records_select(store::AUDIT, 'timecreated < :cutoff', $params);
        $DB->delete_records_select(store::RUN, 'sealed = -1 AND timecreated < :cutoff', $params);
    }
}
