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

use mod_skillupsattendance\activity;
use mod_skillupsattendance\session;
use mod_skillupsattendance\store;

/**
 * Restore attendance records with new identity, group and file mappings.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class restore_skillupsattendance_activity_structure_step extends restore_activity_structure_step {
    /**
     * Register the activity data paths.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('skillupsattendance', '/activity/attendance'),
            new restore_path_element('skillupsattendance_session', '/activity/attendance/sessions/session'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('skillupsattendance_mark', '/activity/attendance/sessions/session/marks/mark');
            $requests = '/activity/attendance/sessions/session/requests/request';
            $paths[] = new restore_path_element('skillupsattendance_just', $requests);
            $paths[] = new restore_path_element('skillupsattendance_audit', '/activity/attendance/history/audit');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore activity settings in the target course.
     *
     * @param array $data XML fields
     */
    protected function process_skillupsattendance($data): void {
        global $DB;
        $row = (object)$data;
        $row->course = $this->get_courseid();
        $this->apply_activity_instance($DB->insert_record(store::ACTIVITY, $row));
    }

    /**
     * Restore a session without portable credentials or access to an unmapped group.
     *
     * @param array $data XML fields
     */
    protected function process_skillupsattendance_session($data): void {
        global $DB;
        $row = (object)$data;
        $oldid = $row->id;
        $row->attendanceid = $this->get_new_parentid('skillupsattendance');
        $row->starts = $this->apply_date_offset($row->starts);
        $row->ends = $this->apply_date_offset($row->ends);
        $row->teacherid = $this->get_setting_value('userinfo') ? $this->get_mappingid('user', $row->teacherid, 0) : 0;
        $row->groupid = $row->groupid ? $this->get_mappingid('group', $row->groupid, -1) : 0;
        if ($row->groupid < 0 || (!$this->get_setting_value('userinfo') && $row->ends <= time())) {
            $row->state = 'cancelled';
        } else if (!$this->get_setting_value('userinfo') && $row->state === 'final') {
            $row->state = 'scheduled';
        }
        $row->method = $row->method === 'code' ? 'manual' : $row->method;
        $row->qrkey = $row->method === 'qr' && $row->state === 'scheduled' ? bin2hex(random_bytes(32)) : null;
        $this->set_mapping('skillupsattendance_session', $oldid, $DB->insert_record(store::SESSION, $row));
    }

    /**
     * Restore a student's mark if the participant was mapped.
     *
     * @param array $data XML fields
     */
    protected function process_skillupsattendance_mark($data): void {
        global $DB;
        $row = (object)$data;
        $row->userid = $this->get_mappingid('user', $row->userid);
        if (!$row->userid) {
            return;
        }
        $row->sessionid = $this->get_new_parentid('skillupsattendance_session');
        $row->timein = $row->timein ? $this->apply_date_offset($row->timein) : 0;
        $row->timeexit = $row->timeexit ? $this->apply_date_offset($row->timeexit) : 0;
        $DB->insert_record(store::MARK, $row);
    }

    /**
     * Restore the approved request and remap its private file identifier.
     *
     * @param array $data XML fields
     */
    protected function process_skillupsattendance_just($data): void {
        global $DB;
        $row = (object)$data;
        $row->userid = $this->get_mappingid('user', $row->userid);
        if (!$row->userid) {
            return;
        }
        $row->reviewer = $this->get_mappingid('user', $row->reviewer, 0);
        $row->sessionid = $this->get_new_parentid('skillupsattendance_session');
        $this->set_mapping('skillupsattendance_just', $row->id, $DB->insert_record(store::JUST, $row), true);
    }

    /**
     * Remap operator and subject identifiers in audit evidence.
     *
     * @param array $data XML fields
     */
    protected function process_skillupsattendance_audit($data): void {
        global $DB;
        $row = (object)$data;
        $row->attendanceid = $this->get_new_parentid('skillupsattendance');
        $row->sessionid = $this->get_mappingid('skillupsattendance_session', $row->sessionid, 0);
        $row->actorid = $this->get_mappingid('user', $row->actorid, 0);
        $row->userid = $this->get_mappingid('user', $row->userid, 0);
        $DB->insert_record(store::AUDIT, $row);
    }

    /**
     * Restore files using Moodle and regenerate calendar entry bindings.
     */
    protected function after_execute(): void {
        global $DB;
        $this->add_related_files('mod_skillupsattendance', 'intro', null);
        if ($this->get_setting_value('userinfo')) {
            $this->add_related_files('mod_skillupsattendance', 'evidence', 'skillupsattendance_just');
        }
        $instance = (int)$this->get_task()->get_activityid();
        $activity = activity::by_instance($instance);
        foreach ($DB->get_records(store::SESSION, ['attendanceid' => $instance]) as $row) {
            session::calendar($activity, $row);
        }
    }
}
