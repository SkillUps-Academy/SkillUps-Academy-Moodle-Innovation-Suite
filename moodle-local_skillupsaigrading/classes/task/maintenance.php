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

namespace local_skillupsaigrading\task;

use local_skillupsaigrading\access;
use local_skillupsaigrading\workflow;

/**
 * Clear interrupted jobs and expire old review records.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maintenance extends \core\task\scheduled_task {
    /**
     * Moodle scheduled task label.
     */
    public function get_name(): string {
        return get_string('maintenance', access::C);
    }

    /**
     * Fail abandoned jobs without replaying a potentially billed transfer.
     */
    public function execute(): void {
        global $DB;
        $now = time();
        $retention = max(1, min(365, (int)(get_config(access::C, 'retention') ?: 30)));
        $old = $now - $retention * DAYSECS;
        $stuck = $now - 6 * HOURSECS;
        $records = $DB->get_records_select(
            workflow::JOB,
            "timecreated < ? OR (state IN ('queued','working') AND timemodified < ?)",
            [$old, $stuck],
            'id ASC',
            'id, cmid',
            0,
            300
        );
        foreach ($records as $record) {
            try {
                $lock = access::lock($record->cmid, 0);
            } catch (\moodle_exception) {
                continue;
            }
            try {
                $job = $DB->get_record(workflow::JOB, ['id' => $record->id]);
                if ($job && $job->timecreated < $old) {
                    workflow::erase((int)$job->id);
                } else if ($job && in_array($job->state, ['queued', 'working'], true) && $job->timemodified < $stuck) {
                    $DB->update_record(workflow::JOB, [
                        'id' => $job->id, 'state' => 'failed', 'excerpt' => null, 'timemodified' => $now,
                    ]);
                }
            } finally {
                $lock->release();
            }
        }
    }
}
