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

namespace local_skillupsquestionstudio\task;

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\work;

/**
 * Reconcile queued work and purge expired document text and temporary files.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maintenance extends \core\task\scheduled_task {
    /**
     * Localized task label.
     */
    public function get_name(): string {
        return get_string('taskmaintenance', access::C);
    }

    /**
     * Process a bounded page of records per table.
     */
    public function execute(): void {
        global $DB;
        $now = time();
        $days = max(1, min(365, (int)(get_config(access::C, 'retention') ?: 90)));
        foreach (['job', 'source'] as $type) {
            $table = access::C . '_' . $type;
            $params = ['queued', 'working', $now - 20 * MINSECS];
            $where = 'state IN (?, ?) AND timemodified < ?';
            if ($type === 'source') {
                $where = "($where) OR (state = ? AND timemodified < ?)";
                array_push($params, 'ready', $now - $days * DAYSECS);
            }
            foreach ($DB->get_records_select($table, $where, $params, 'timemodified', '*', 0, 100) as $entry) {
                try {
                    $lock = access::lock($entry->projectid, 0);
                } catch (\moodle_exception $exception) {
                    continue;
                }
                try {
                    $current = $DB->get_record($table, ['id' => $entry->id]);
                    if (!$current || $current->state !== $entry->state || $current->timemodified !== $entry->timemodified) {
                        continue;
                    }
                    if ($current->state === 'queued' && $current->timecreated > $now - DAYSECS) {
                        work::queue($type, $current->id, true);
                        continue;
                    }
                    if ($type === 'source') {
                        $course = $DB->get_field(access::C . '_project', 'courseid', ['id' => $current->projectid]);
                        if ($course) {
                            work::delete_upload((int)$course, $current->id);
                        }
                        $DB->update_record($table, (object)[
                            'id' => $current->id, 'state' => $current->state === 'ready' ? 'deleted' : 'failed',
                            'content' => null, 'name' => '', 'hash' => '', 'timemodified' => $now,
                        ]);
                    } else {
                        $DB->set_field($table, 'state', 'failed', ['id' => $current->id]);
                    }
                } finally {
                    $lock->release();
                }
            }
        }
    }
}
