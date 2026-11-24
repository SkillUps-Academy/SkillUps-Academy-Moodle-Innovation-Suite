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

namespace report_skillupssecurity;

/**
 * Queue one limited Moodle notification per scan and authorized recipient.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class alerts {
    /**
     * Write deduplicated outbox rows matched by priority.
     *
     * @param int $scan Scan identifier
     * @param int $now Scan time
     * @param array $new Newly detected or recurrent evidence
     */
    public static function plan(int $scan, int $now, array $new): void {
        global $DB;
        if (!get_config(access::C, 'notifyenabled') || !$new) {
            return;
        }
        $threshold = get_config(access::C, 'notifyseverity') === 'medium' ? ['high', 'medium'] : ['high'];
        $recipients = preg_split('/[\s,]+/', trim((string)get_config(access::C, 'recipients')), -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($recipients as $value) {
            if (ctype_digit($value) && strlen($value) <= 15 && (int)$value > 0) {
                $ids[(int)$value] = true;
            }
        }
        $matched = array_filter($new, static fn(array $row): bool => in_array($row['severity'], $threshold, true));
        if (!$matched) {
            return;
        }
        $personalonly = !in_array(0, array_map('intval', array_column($matched, 'userid')), true);
        foreach (array_slice(array_keys($ids), 0, 20) as $userid) {
            $user = \core_user::get_user($userid);
            if (!self::eligible($user, $personalonly)) {
                continue;
            }
            $DB->insert_record(store::OUTBOX, [
                'scanid' => $scan, 'userid' => $user->id, 'state' => 'waiting', 'timecreated' => $now,
                'personalonly' => (int)$personalonly,
            ]);
        }
    }

    /**
     * Deliver claims once. A failed or uncertain delivery remains visible in the report.
     */
    public static function dispatch(): void {
        global $DB;
        $records = $DB->get_records(store::OUTBOX, ['state' => 'waiting'], 'id ASC', '*', 0, 40);
        foreach ($records as $row) {
            $claimed = store::atomic(static function () use ($DB, $row): bool {
                if (!$DB->record_exists(store::OUTBOX, ['id' => $row->id, 'state' => 'waiting'])) {
                    return false;
                }
                $DB->update_record(store::OUTBOX, ['id' => $row->id, 'state' => 'sending', 'timesent' => time()]);
                return true;
            });
            if (!$claimed) {
                continue;
            }
            $user = \core_user::get_user($row->userid);
            try {
                $enabled = get_config(access::C, 'notifyenabled');
                $result = $enabled && self::eligible($user, (bool)$row->personalonly) && self::send($user);
                $DB->set_field(store::OUTBOX, 'state', $result ? 'sent' : 'skipped', ['id' => $row->id]);
            } catch (\Throwable $failure) {
                $DB->set_field(store::OUTBOX, 'state', 'failed', ['id' => $row->id]);
            }
        }
    }

    /**
     * Check current account status and capability.
     *
     * @param \stdClass|false|null $user Moodle user
     * @param bool $personalonly The alert has only identifiable findings
     * @return bool
     */
    private static function eligible(\stdClass|false|null $user, bool $personalonly): bool {
        return $user && !$user->deleted && !$user->suspended && !isguestuser($user) &&
            has_capability(access::CAP . 'view', \context_system::instance(), $user) &&
            (!$personalonly || has_capability(access::CAP . 'inventory', \context_system::instance(), $user));
    }

    /**
     * Send a summary without identities or control details.
     *
     * @param \stdClass $user Recipient
     * @return bool
     */
    private static function send(\stdClass $user): bool {
        $label = access::text('alertsubject');
        $body = access::text('alertbody');
        $message = new \core\message\message();
        $message->component = access::C;
        $message->name = 'risk';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $label;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p>';
        $message->smallmessage = $label;
        $message->notification = 1;
        $message->contexturl = access::url()->out(false);
        $message->contexturlname = access::text('pluginname');
        return (bool)message_send($message);
    }
}
