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

namespace mod_skillupsattendance;

/**
 * Notify each eligible learner once for every attendance risk episode.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class alerts {
    /**
     * Evaluate an activity policy, persist deduplication, and send private notices.
     *
     * @param activity $activity Scope
     * @return int Delivery attempts
     */
    public static function notify(activity $activity): int {
        global $DB;
        if (!$activity->record->alerts || !$activity->cm->visible || !$activity->course->visible) {
            return 0;
        }
        $recipients = store::atomic($activity->record->id, static function () use ($activity, $DB): array {
            $activityid = $activity->record->id;
            if (!$DB->get_field(store::ACTIVITY, 'alertdirty', ['id' => $activityid])) {
                return [];
            }
            $rows = report::overview($activity, [], 0, true);
            $existing = [];
            foreach ($DB->get_records(store::ALERT, ['attendanceid' => $activityid]) as $alert) {
                $existing[$alert->userid][$alert->rulekey] = $alert;
            }
            $recipients = [];
            foreach ($rows as $id => $result) {
                $flags = $activity->eligible($id, 0) ? $result['flags'] : [];
                foreach ($flags as $flag) {
                    $row = $existing[$id][$flag] ?? null;
                    if (!$row || !$row->active) {
                        $entry = ['active' => 1, 'modified' => time()];
                        if ($row) {
                            $DB->update_record(store::ALERT, $entry + ['id' => $row->id]);
                        } else {
                            $key = ['attendanceid' => $activityid, 'userid' => $id, 'rulekey' => $flag];
                            $DB->insert_record(store::ALERT, $entry + $key);
                        }
                        $recipients[$id] = $id;
                    }
                    unset($existing[$id][$flag]);
                }
            }
            foreach ($existing as $oldflags) {
                foreach ($oldflags as $row) {
                    if ($row->active) {
                        $DB->set_field(store::ALERT, 'active', 0, ['id' => $row->id]);
                    }
                }
            }
            $DB->set_field(store::ACTIVITY, 'alertdirty', 0, ['id' => $activityid]);
            return $recipients;
        });
        foreach ($recipients as $id) {
            self::deliver($activity, (int)$id);
        }
        return count($recipients);
    }

    /**
     * Deliver a generic notice through Moodle messaging without disclosing absences.
     *
     * @param activity $activity Module
     * @param int $userid Student
     */
    private static function deliver(activity $activity, int $userid): void {
        $visible = get_fast_modinfo($activity->course, $userid)->get_cm($activity->cm->id)->uservisible;
        if (!$visible || !$activity->eligible($userid, 0)) {
            return;
        }
        $user = \core_user::get_user($userid);
        $title = get_string_manager()->get_string('warningtitle', activity::C, null, $user->lang);
        $body = get_string_manager()->get_string('warningbody', activity::C, null, $user->lang);
        $message = new \core\message\message();
        $message->component = activity::C;
        $message->name = 'attendancewarning';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $title;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p>';
        $message->smallmessage = $title;
        $message->notification = 1;
        $message->contexturl = $activity->url()->out(false);
        $message->contexturlname = activity::text('pluginname');
        try {
            $outcome = message_send($message) ? 'warned' : 'warningfailed';
        } catch (\Throwable $failure) {
            $outcome = 'warningfailed';
        }
        store::audit($activity, $outcome, userid: $userid, automatic: true);
    }
}
