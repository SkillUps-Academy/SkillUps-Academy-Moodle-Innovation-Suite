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

namespace local_skillupssuccess\task;

use local_skillupssuccess\privacy\deletion;
use local_skillupssuccess\store;
use local_skillupssuccess\util;

/**
 * Limit case, observation and assessment retention.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class retention extends \core\task\scheduled_task {
    /**
     * Local task title.
     */
    public function get_name(): string {
        return util::str('taskretention');
    }

    /**
     * Remove expired closed or inactive cases, old observations and evaluation samples.
     */
    public function execute(): void {
        global $DB;
        $days = max(45, min(730, (int)(get_config(util::C, 'retention') ?: 180)));
        $cutoff = time() - $days * DAYSECS;
        $cases = store::CASES;
        $observations = store::OBS;
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.courseid FROM {{$cases}} c JOIN {{$observations}} o ON o.id = c.observationid
            WHERE o.captured < :inactive OR (c.state = :state AND c.timemodified < :closed) ORDER BY c.id",
            ['inactive' => $cutoff, 'state' => 'resolved', 'closed' => $cutoff],
            0,
            250
        );
        foreach ($rows as $row) {
            $lock = util::lock($row->courseid, 0);
            if (!$lock) {
                continue;
            }
            try {
                $case = $DB->get_record(store::CASES, ['id' => $row->id]);
                if ($case) {
                    $captured = $DB->get_field(store::OBS, 'captured', ['id' => $case->observationid]);
                    if ($captured < $cutoff || ($case->state === 'resolved' && $case->timemodified < $cutoff)) {
                        deletion::case_record($row->id);
                    }
                }
            } finally {
                $lock->release();
            }
        }
        $DB->delete_records_select(
            store::OBS,
            "captured < :cutoff AND id NOT IN (SELECT observationid FROM {{$cases}})",
            ['cutoff' => $cutoff]
        );
        $DB->delete_records_select(store::SAMPLE, 'captured < :cutoff', ['cutoff' => $cutoff]);
    }
}
