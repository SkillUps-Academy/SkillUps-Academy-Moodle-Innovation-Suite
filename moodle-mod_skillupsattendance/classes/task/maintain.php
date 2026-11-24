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

namespace mod_skillupsattendance\task;

use mod_skillupsattendance\activity;
use mod_skillupsattendance\alerts;
use mod_skillupsattendance\marks;
use mod_skillupsattendance\store;

/**
 * Finalize attendance and evaluate modified activity policies in bounded batches.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maintain extends \core\task\scheduled_task {
    /**
     * Return the task title.
     *
     * @return string
     */
    public function get_name(): string {
        return activity::text('taskmaintain');
    }

    /**
     * Close due sessions, deliver risk notifications and remove expired counters.
     */
    public function execute(): void {
        global $DB;
        $where = 'state = :status AND ends + grace * 60 <= :now';
        $params = ['status' => 'scheduled', 'now' => time()];
        $due = $DB->get_records_select(store::SESSION, $where, $params, 'ends ASC', 'id,attendanceid', 0, 25);
        foreach ($due as $row) {
            try {
                marks::finalize(activity::by_instance($row->attendanceid), $row->id, true);
            } catch (\Throwable $failure) {
                mtrace('Attendance finalization deferred for session ' . (int)$row->id);
            }
        }
        $table = store::ACTIVITY;
        $dirty = $DB->get_records_sql(
            "SELECT a.id FROM {{$table}} a
              JOIN {course} c ON c.id = a.course
              JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = c.id
              JOIN {modules} m ON m.id = cm.module AND m.name = :component
             WHERE a.alerts = 1 AND a.alertdirty = 1 AND c.visible = 1 AND cm.visible = 1 AND cm.deletioninprogress = 0
          ORDER BY a.id ASC",
            ['component' => 'skillupsattendance'],
            0,
            15,
        );
        foreach ($dirty as $row) {
            try {
                alerts::notify(activity::by_instance($row->id));
            } catch (\Throwable $failure) {
                mtrace('Attendance notification deferred for activity ' . (int)$row->id);
            }
        }
        $DB->delete_records_select(store::FAIL, 'expires <= :expiry', ['expiry' => time()]);
    }
}
