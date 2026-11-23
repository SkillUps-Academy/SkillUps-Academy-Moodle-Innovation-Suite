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

namespace local_skillupscertify;

/**
 * Course deletion and bounded housekeeping of administrative records.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class retention {
    /**
     * Clean up metadata of a deleted course; Moodle removes course-context files.
     *
     * @param \core\event\course_deleted $event Course deletion event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $courseid = (int)$event->objectid;
        store::atomic("templates:$courseid", static function () use ($DB, $courseid): void {
            $templates = $DB->get_fieldset_select(store::TEMPLATE, 'id', 'courseid = :course', ['course' => $courseid]);
            foreach ($templates as $id) {
                $DB->delete_records(store::REVISION, ['templateid' => $id]);
            }
            foreach ([store::ITEM, store::BATCH, store::ISSUE, store::TEMPLATE, store::AUDIT] as $table) {
                $DB->delete_records($table, ['courseid' => $courseid]);
            }
        });
    }

    /**
     * Remove closed batch details and old audit rows. Issued certificates remain untouched.
     */
    public static function run(): void {
        global $DB;
        $now = time();
        $days = (int)(get_config(access::C, 'batchdays') ?: 90);
        $backlog = $DB->get_records_select(
            store::BATCH,
            'state <> :queued AND timemodified < :old',
            ['queued' => 'queued', 'old' => $now - max(7, $days) * DAYSECS],
            'id ASC',
            'id',
            0,
            200
        );
        foreach ($backlog as $batch) {
            $DB->delete_records(store::ITEM, ['batchid' => $batch->id]);
            $DB->delete_records(store::BATCH, ['id' => $batch->id]);
        }
        $days = (int)(get_config(access::C, 'auditdays') ?: 730);
        $DB->delete_records_select(store::AUDIT, 'timecreated < :old', ['old' => $now - max(30, $days) * DAYSECS]);
        $table = store::BATCH;
        $stalled = $DB->get_records_sql(
            "SELECT b.id, b.courseid FROM {{$table}} b LEFT JOIN {user} u ON u.id = b.userid
              WHERE b.state = :state AND b.timemodified < :time
                AND (u.id IS NULL OR u.deleted = 1 OR u.suspended = 1)
           ORDER BY b.id",
            ['state' => 'queued', 'time' => $now - DAYSECS],
            0,
            200
        );
        foreach ($stalled as $batch) {
            $DB->update_record(store::BATCH, ['id' => $batch->id, 'state' => 'cancelled', 'timemodified' => time()]);
            store::audit($batch->courseid, 'batchcancel', $batch->id);
        }
    }
}
