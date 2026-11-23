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

namespace local_skillupsgradebookguardian\task;

use local_skillupsgradebookguardian\access;
use local_skillupsgradebookguardian\ledger;

/**
 * Retain audit evidence for the configured number of days.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class purge extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('purgetask');
    }

    /**
     * Delete old plugin records in bounded SQL batches.
     */
    public function execute(): void {
        global $DB;
        $retention = max(30, min(365, (int)(get_config(access::COMPONENT, 'retention') ?: 180)));
        $cutoff = time() - $retention * DAYSECS;
        for ($batch = 0; $batch < 20; $batch++) {
            $rows = $DB->get_records_select(
                ledger::TABLE,
                'timecreated < :cutoff',
                ['cutoff' => $cutoff],
                'timecreated ASC',
                'id',
                0,
                400
            );
            $ids = array_keys($rows);
            if (!$ids) {
                return;
            }
            $DB->delete_records_list(ledger::TABLE, 'id', $ids);
            if (count($ids) < 400) {
                return;
            }
        }
    }
}
