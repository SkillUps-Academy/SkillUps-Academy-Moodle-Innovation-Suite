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

namespace local_skillupshelpdesk;

use context_system;
use core\message\message;
use core_user;
use stdClass;

/**
 * Retryable delivery of minimal Moodle notifications.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification {
    /**
     * Queue one pending generic notification per ticket and recipient.
     *
     * @param int $ticketid Ticket ID
     * @param int[] $recipients Potential recipients
     */
    public static function queue(int $ticketid, array $recipients): void {
        global $DB;
        if (!(get_config(access::COMPONENT)->notifications ?? 1)) {
            return;
        }
        foreach (array_unique($recipients) as $userid) {
            if ($userid > 0 && !$DB->record_exists(storage::OUTBOX, ['ticketid' => $ticketid, 'userid' => $userid])) {
                $DB->insert_record(storage::OUTBOX, [
                    'ticketid' => $ticketid, 'userid' => $userid, 'timecreated' => time(), 'availableat' => time(),
                ]);
            }
        }
    }

    /**
     * Notify the assigned technician and verified escalation supervisor.
     *
     * @param stdClass $ticket Ticket snapshot
     */
    public static function escalate(stdClass $ticket): void {
        $id = (int)get_config(access::COMPONENT, 'escalationuserid');
        $recipient = $id ? core_user::get_user($id) : get_admin();
        $supervisor = $recipient && access::can('supervise', $recipient) ? (int)$recipient->id : 0;
        self::queue((int)$ticket->id, [(int)$ticket->assigneeid, $supervisor]);
    }

    /**
     * Process a bounded batch of pending notifications.
     */
    public static function deliver(): void {
        global $DB;
        $rows = $DB->get_records_select(
            storage::OUTBOX,
            'availableat <= :now',
            ['now' => time()],
            'availableat ASC, id ASC',
            '*',
            0,
            100
        );
        foreach ($rows as $pending) {
            try {
                storage::locked('ticket:' . $pending->ticketid, static function () use ($pending): void {
                    self::deliver_one((int)$pending->id);
                });
            } catch (\moodle_exception $exception) {
                if ($exception->errorcode !== 'busy') {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Revalidate the recipient before every send and back off after failures.
     *
     * @param int $id Queue identifier
     */
    private static function deliver_one(int $id): void {
        global $DB;
        $pending = $DB->get_record(storage::OUTBOX, ['id' => $id]);
        if (!$pending) {
            return;
        }
        $ticket = $DB->get_record(storage::TICKET, ['id' => $pending->ticketid]);
        $recipient = core_user::get_user($pending->userid);
        if (
            !(get_config(access::COMPONENT)->notifications ?? 1) ||
            !$ticket || !$recipient || !access::ticket($ticket, $recipient)
        ) {
            $DB->delete_records(storage::OUTBOX, ['id' => $id]);
            return;
        }
        try {
            $success = self::send($recipient);
        } catch (\Exception $failure) {
            $success = false;
        }
        if ($success) {
            $DB->delete_records(storage::OUTBOX, ['id' => $id]);
            return;
        }
        $attempt = (int)$pending->attempts + 1;
        if ($attempt >= 5) {
            storage::transaction(static function () use ($DB, $id, $ticket): void {
                $DB->delete_records(storage::OUTBOX, ['id' => $id]);
                storage::audit((int)$ticket->id, 'notificationfailed', [], 0);
            });
            return;
        }
        $DB->update_record(storage::OUTBOX, [
            'id' => $id, 'attempts' => $attempt, 'availableat' => time() + 300 * (2 ** $attempt),
        ]);
    }

    /**
     * Send a localized generic notice, with a link only to the secured portal.
     *
     * @param stdClass $recipient Verified recipient
     * @return bool
     */
    private static function send(stdClass $recipient): bool {
        $strings = get_string_manager();
        $language = $recipient->lang ?: current_language();
        $summary = $strings->get_string('notificationbody', access::COMPONENT, null, $language);
        $notification = new message();
        $notification->component = access::COMPONENT;
        $notification->name = 'ticketupdate';
        $notification->userfrom = core_user::get_noreply_user();
        $notification->userto = $recipient;
        $notification->subject = $strings->get_string('notificationsubject', access::COMPONENT, null, $language);
        $notification->fullmessage = $summary;
        $notification->fullmessageformat = FORMAT_PLAIN;
        $notification->fullmessagehtml = '';
        $notification->smallmessage = $summary;
        $notification->notification = 1;
        $notification->contexturl = access::url()->out(false);
        $notification->contexturlname = $strings->get_string('pluginname', access::COMPONENT, null, $language);
        return (bool)message_send($notification);
    }
}
