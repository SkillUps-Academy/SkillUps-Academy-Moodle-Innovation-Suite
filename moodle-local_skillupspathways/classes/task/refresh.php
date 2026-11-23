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

namespace local_skillupspathways\task;

use local_skillupspathways\access;
use local_skillupspathways\progress;
use local_skillupspathways\store;

/**
 * Refresh a bounded batch of existing learner records.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class refresh extends \core\task\scheduled_task {
    /**
     * Return the configured task name.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('refreshtask');
    }

    /**
     * Reconcile the least recently checked pinned registrations.
     */
    public function execute(): void {
        global $DB;
        $progress = store::PROGRESS;
        $path = store::PATH;
        $rows = $DB->get_records_sql(
            "SELECT p.id, p.pathid, p.userid, g.courseid
               FROM {{$progress}} p JOIN {{$path}} g ON g.id = p.pathid
              WHERE g.enabled = 1 AND g.activeversion > 0 AND p.timechecked <= :due
           ORDER BY p.timechecked ASC, p.id ASC",
            ['due' => time() - 5 * MINSECS],
            0,
            50
        );
        foreach ($rows as $row) {
            try {
                progress::refresh((int)$row->pathid, (int)$row->courseid, (int)$row->userid);
            } catch (\moodle_exception $error) {
                if ($error->errorcode !== 'busy') {
                    throw $error;
                }
            }
        }
    }
}
