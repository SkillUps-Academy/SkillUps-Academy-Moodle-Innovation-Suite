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

namespace report_skillupsmonitor\task;

use report_skillupsmonitor\store;
use report_skillupsmonitor\util;

/**
 * Delete expired monitoring observations and closed incidents in bounded batches.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class purge extends \core\task\scheduled_task {
    /**
     * Task label.
     */
    public function get_name(): string {
        return util::str('taskpurge');
    }

    /**
     * Preserve open incidents while removing records beyond the retention window.
     */
    public function execute(): void {
        global $DB;
        $sampling = util::lock('sampling');
        if (!$sampling) {
            return;
        }
        $delivery = null;
        try {
            $delivery = util::lock('delivery');
            if (!$delivery) {
                return;
            }
            $cutoff = time() - util::integer('retention', 90, 30, 365) * DAYSECS;
            $samples = array_keys($DB->get_records_select(store::SAMPLE, 'slot < ?', [$cutoff], 'slot ASC', 'id', 0, 5000));
            foreach (array_chunk($samples, 500) as $batch) {
                $DB->delete_records_list(store::SAMPLE, 'id', $batch);
            }
            $incidents = $DB->get_records_select(store::INCIDENT, 'closed > 0 AND closed < ?', [$cutoff], 'id ASC', 'id', 0, 250);
            $ids = array_keys($incidents);
            if ($ids) {
                $tx = $DB->start_delegated_transaction();
                $DB->delete_records_list(store::NOTICE, 'incidentid', $ids);
                $DB->delete_records_list(store::INCIDENT, 'id', $ids);
                $tx->allow_commit();
            }
            $expired = $DB->get_records_select(store::NOTICE, 'timecreated < ?', [$cutoff], 'id ASC', 'id', 0, 1000);
            if ($expired) {
                $DB->delete_records_list(store::NOTICE, 'id', array_keys($expired));
            }
        } finally {
            $delivery?->release();
            $sampling->release();
        }
    }
}
