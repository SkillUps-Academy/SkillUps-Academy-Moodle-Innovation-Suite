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

namespace report_skillupssecurity\task;

use report_skillupssecurity\access;
use report_skillupssecurity\alerts;
use report_skillupssecurity\store;

/**
 * Notify authorized recipients and age out retained audit data.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maintain extends \core\task\scheduled_task {
    /**
     * Return the scheduled task name.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('taskmaintain');
    }

    /**
     * Maintain audit retention and unresolved notification attempts.
     */
    public function execute(): void {
        alerts::dispatch();
        store::atomic(static function (): void {
            global $DB;
            $cutoff = time() - max(7, min(730, (int)(get_config(access::C, 'retention') ?: 90))) * DAYSECS;
            $latest = store::latest();
            $stale = $DB->get_fieldset_select(store::SCAN, 'id', 'id <> :latest AND timecreated < :cutoff', [
                'latest' => $latest->id ?? 0, 'cutoff' => $cutoff,
            ]);
            foreach (array_chunk($stale, 200) as $group) {
                $DB->delete_records_list(store::PRIVILEGE, 'scanid', $group);
                $DB->delete_records_list(store::OUTBOX, 'scanid', $group);
                $DB->delete_records_list(store::SCAN, 'id', $group);
            }
            $finished = $DB->get_fieldset_select(store::FINDING, 'id', 'present = 0 AND lastseen < :cutoff', ['cutoff' => $cutoff]);
            foreach (array_chunk($finished, 200) as $group) {
                $DB->delete_records_list(store::HISTORY, 'findingid', $group);
                $DB->delete_records_list(store::FINDING, 'id', $group);
            }
            $DB->delete_records_select(store::ACTIVITY, 'timecreated < :cutoff', ['cutoff' => $cutoff]);
            $DB->set_field_select(store::OUTBOX, 'state', 'unknown', 'state = :sending AND timesent < :old', [
                'sending' => 'sending', 'old' => time() - HOURSECS,
            ]);
        });
    }
}
