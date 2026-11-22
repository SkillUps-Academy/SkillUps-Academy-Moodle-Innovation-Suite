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

namespace report_skillupsquality\task;

use core\lock\lock_config;
use report_skillupsquality\scope;
use report_skillupsquality\store;

/**
 * Recover stale jobs and apply bounded historical retention.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maintain extends \core\task\scheduled_task {
    /**
     * Localized label.
     */
    public function get_name(): string {
        return scope::text('maintain');
    }

    /**
     * Release aged requests and purge old revisions in bounded batches.
     */
    public function execute(): void {
        global $DB;
        $now = time();
        $pending = $DB->get_records_select(
            store::RUN,
            "state IN ('queued', 'running') AND timemodified < :stale",
            ['stale' => $now - HOURSECS],
            'timemodified ASC',
            'id,courseid,state,timemodified,timecreated',
            0,
            100,
        );
        $factory = lock_config::get_lock_factory(scope::COMPONENT);
        foreach ($pending as $row) {
            $lock = $factory->get_lock('course_' . $row->courseid, 0);
            if (!$lock) {
                continue;
            }
            try {
                $current = $DB->get_record(store::RUN, ['id' => $row->id]);
                if (!$current || $current->state !== $row->state || $current->timemodified != $row->timemodified) {
                    continue;
                }
                if ($row->timecreated < $now - 2 * DAYSECS) {
                    store::change((int)$row->id, 'failed');
                } else {
                    store::queue_task((int)$row->id);
                    store::change((int)$row->id, 'queued');
                }
            } finally {
                $lock->release();
            }
        }
        $setting = get_config(scope::COMPONENT, 'retention');
        $retention = $setting === false ? 180 : max(30, min(730, (int)$setting));
        $old = $DB->get_records_select(
            store::RUN,
            'timemodified < :cutoff OR NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = courseid)',
            ['cutoff' => $now - $retention * DAYSECS],
            'id ASC',
            'id',
            0,
            200,
        );
        if ($old) {
            $ids = array_keys($old);
            $tx = $DB->start_delegated_transaction();
            $DB->delete_records_list(store::FINDING, 'runid', $ids);
            $DB->delete_records_list(store::RUN, 'id', $ids);
            $tx->allow_commit();
        }
        $plans = $DB->get_records_select(
            store::PLAN,
            'NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = courseid)',
            [],
            'id ASC',
            'id',
            0,
            200,
        );
        if ($plans) {
            $DB->delete_records_list(store::PLAN, 'id', array_keys($plans));
        }
    }
}
