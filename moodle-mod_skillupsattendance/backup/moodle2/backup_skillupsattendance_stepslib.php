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

/**
 * Back up activity settings and authorized user data without access credentials.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_skillupsattendance_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the XML structure and file areas.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $main = new backup_nested_element('attendance', ['id'], [
            'name', 'intro', 'introformat', 'minimum', 'consecutive', 'absences', 'alerts', 'grades', 'reasons',
            'timecreated', 'timemodified',
        ]);
        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], [
            'groupid', 'teacherid', 'name', 'modality', 'method', 'state', 'starts', 'ends', 'early', 'grace', 'late',
            'checkout', 'extra', 'serieskey', 'modified',
        ]);
        $marks = new backup_nested_element('marks');
        $mark = new backup_nested_element('mark', ['id'], ['userid', 'state', 'timein', 'timeexit', 'source', 'modified']);
        $requests = new backup_nested_element('requests');
        $request = new backup_nested_element('request', ['id'], [
            'userid', 'reviewer', 'reason', 'statement', 'state', 'response', 'timecreated', 'timereviewed',
        ]);
        $history = new backup_nested_element('history');
        $audit = new backup_nested_element('audit', ['id'], [
            'sessionid', 'userid', 'actorid', 'operation', 'beforestate', 'afterstate', 'timecreated',
        ]);
        $main->add_child($sessions);
        $sessions->add_child($session);
        $session->add_child($marks);
        $marks->add_child($mark);
        $session->add_child($requests);
        $requests->add_child($request);
        $main->add_child($history);
        $history->add_child($audit);
        $main->set_source_table('skillupsattendance', ['id' => backup::VAR_ACTIVITYID]);
        $session->set_source_table('skillupsattendance_sess', ['attendanceid' => backup::VAR_PARENTID], 'id ASC');
        $session->annotate_ids('group', 'groupid');
        $session->annotate_ids('user', 'teacherid');
        $main->annotate_files('mod_skillupsattendance', 'intro', null);
        if ($this->get_setting_value('userinfo')) {
            $mark->set_source_table('skillupsattendance_mark', ['sessionid' => backup::VAR_PARENTID]);
            $mark->annotate_ids('user', 'userid');
            $request->set_source_table('skillupsattendance_just', ['sessionid' => backup::VAR_PARENTID]);
            $request->annotate_ids('user', 'userid');
            $request->annotate_ids('user', 'reviewer');
            $request->annotate_files('mod_skillupsattendance', 'evidence', 'id');
            $audit->set_source_table('skillupsattendance_audit', ['attendanceid' => backup::VAR_PARENTID]);
            $audit->annotate_ids('user', 'userid');
            $audit->annotate_ids('user', 'actorid');
        }
        return $this->prepare_activity_structure($main);
    }
}
