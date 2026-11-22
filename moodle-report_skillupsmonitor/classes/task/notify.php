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

namespace report_skillupsmonitor\task;

use report_skillupsmonitor\store;
use report_skillupsmonitor\util;

/**
 * In-platform notices with revalidated permissions and durable claims.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notify extends \core\task\scheduled_task {
    /**
     * Task label.
     */
    public function get_name(): string {
        return util::str('tasknotify');
    }

    /**
     * Claim and send a bounded batch; ambiguous deliveries are never replayed.
     */
    public function execute(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/messagelib.php');
        $lock = util::lock('delivery');
        if (!$lock) {
            return;
        }
        try {
            $now = time();
            $DB->set_field_select(
                store::NOTICE,
                'state',
                'ambiguous',
                'state = :state AND timemodified < :cutoff',
                ['state' => 'claimed', 'cutoff' => $now - HOURSECS]
            );
            $rows = $DB->get_records(store::NOTICE, ['state' => 'pending'], 'id ASC', '*', 0, 40);
            $selected = util::recipients();
            foreach ($rows as $row) {
                $incident = $DB->get_record(store::INCIDENT, ['id' => $row->incidentid], '*', MUST_EXIST);
                if (
                    !in_array((int)$row->userid, $selected, true) || !util::valid_recipient((int)$row->userid) ||
                        !util::alerted($incident->checkkey) || $row->timecreated < $now - 2 * DAYSECS
                ) {
                    $this->update($row->id, 'skipped');
                    continue;
                }
                $this->update($row->id, 'claimed');
                try {
                    $state = $this->send($row, $incident) ? 'sent' : 'failed';
                } catch (\Throwable $error) {
                    // A processor can throw after accepting the message.
                    $state = 'ambiguous';
                }
                $this->update($row->id, $state);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Persist dispatch progress before any external-facing message processor runs.
     */
    private function update(int $id, string $state): void {
        global $DB;
        $DB->update_record(store::NOTICE, ['id' => $id, 'state' => $state, 'timemodified' => time()]);
    }

    /**
     * Do not include hostnames, paths or logs in notifications.
     */
    private function send(\stdClass $row, \stdClass $incident): bool {
        $message = new \core\message\message();
        $message->component = util::C;
        $message->name = 'incident';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = \core_user::get_user($row->userid, '*', MUST_EXIST);
        $message->notification = 1;
        $message->subject = util::str('notice_' . $row->phase, util::str('check_' . $incident->checkkey));
        $message->fullmessage = util::str('noticebody');
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->contexturl = util::url('incidents', ['incident' => $incident->id])->out(false);
        $message->contexturlname = util::str('incidents');
        return (bool)message_send($message);
    }
}
