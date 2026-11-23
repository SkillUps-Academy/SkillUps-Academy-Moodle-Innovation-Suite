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

namespace tool_skillupsenrollment\task;

use tool_skillupsenrollment\jobs;
use tool_skillupsenrollment\support;

/**
 * Purge aged operation metadata without deleting courses.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class purge extends \core\task\scheduled_task {
    /**
     * Localized task name.
     */
    public function get_name(): string {
        return support::text('purge');
    }

    /**
     * Remove batches of expired records, including unconfirmed drafts after seven days.
     */
    public function execute(): void {
        global $DB;
        $days = (int)get_config(support::C, 'retention') ?: 180;
        $rows = $DB->get_records_select(
            jobs::JOB,
            "(state = 'draft' AND timemodified < :draft) OR (state IN ('done', 'cancelled') AND timemodified < :history)",
            ['draft' => time() - 7 * DAYSECS, 'history' => time() - max(30, min(730, $days)) * DAYSECS],
            'id ASC',
            'id,state,timemodified',
            0,
            100
        );
        foreach ($rows as $row) {
            $lock = support::lock('control_' . $row->id);
            if (!$lock) {
                continue;
            }
            try {
                if (!$DB->record_exists(jobs::JOB, (array)$row)) {
                    continue;
                }
                $tx = $DB->start_delegated_transaction();
                $DB->delete_records(jobs::ROW, ['jobid' => $row->id]);
                $DB->delete_records(jobs::JOB, ['id' => $row->id]);
                $tx->allow_commit();
            } finally {
                $lock->release();
            }
        }
    }
}
