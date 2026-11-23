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

use core_text;
use moodle_exception;
use stdClass;

/**
 * Ticket lifecycle, conversation, staff triage and requester satisfaction.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tickets {
    /** @var array Ticket dimensions backed by catalogs. */
    public const DIMENSIONS = ['category', 'campus', 'priority', 'severity', 'status'];

    /**
     * Fetch one ticket with the same access rule as the queue and attachments.
     *
     * @param int $id Ticket ID
     * @return stdClass
     */
    public static function get(int $id): stdClass {
        global $DB;
        $ticket = $DB->get_record(storage::TICKET, ['id' => $id]);
        access::require($ticket && access::ticket($ticket));
        return $ticket;
    }

    /**
     * Open a ticket and atomically save the first public message.
     *
     * @param stdClass $data Validated form values
     * @return int
     */
    public static function create(stdClass $data): int {
        global $DB, $USER;
        access::require(access::can('request'));
        $title = trim($data->title);
        $body = self::body($data->body);
        if ($title === '' || core_text::strlen($title) > 180) {
            throw new moodle_exception('invalidtitle', access::COMPONENT);
        }
        $now = time();
        $courseid = (int)($data->courseid ?? 0);
        if ($courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            access::require($courseid !== SITEID && $course && can_access_course($course, $USER, '', true));
        }
        $lock = 'requester:' . $USER->id;
        return storage::locked($lock, static function () use ($DB, $USER, $data, $title, $body, $courseid, $now): int {
            if (
                $DB->count_records_select(
                    storage::TICKET,
                    'requesterid = :requester AND timecreated > :cutoff',
                    ['requester' => $USER->id, 'cutoff' => $now - HOURSECS]
                ) >= 12
            ) {
                throw new moodle_exception('ratelimit', access::COMPONENT);
            }
            $initial = $DB->get_record(storage::CATALOG, ['kind' => 'status', 'active' => 1, 'initialstate' => 1], '*', MUST_EXIST);
            $record = [
                'requesterid' => $USER->id, 'courseid' => $courseid, 'title' => $title,
                'statusid' => $initial->id, 'timecreated' => $now, 'timemodified' => $now,
            ];
            foreach (['category', 'campus', 'priority', 'severity'] as $kind) {
                $key = $kind . 'id';
                $record[$key] = catalog::entry($kind, (int)$data->$key)->id;
            }
            $priority = catalog::entry('priority', (int)$record['priorityid']);
            $calendar = calendar::configured();
            $record['firstdue'] = $calendar->after($now, (int)$priority->responsegoal);
            $record['resolvedue'] = $calendar->after($now, (int)$priority->resolutiongoal);
            return storage::transaction(static function () use ($DB, $USER, $data, $body, $record, $now): int {
                $id = (int)$DB->insert_record(storage::TICKET, $record);
                self::insert_post($id, (int)$USER->id, $body, false, (int)($data->attachments ?? 0), $now);
                storage::audit($id, 'opened');
                notification::queue($id, [(int)$USER->id]);
                return $id;
            });
        });
    }

    /**
     * Record a public response or staff-only note and capture the first qualifying response.
     *
     * @param int $id Ticket ID
     * @param stdClass $data Comment form values
     * @return int Message ID
     */
    public static function reply(int $id, stdClass $data): int {
        global $DB, $USER;
        $internal = !empty($data->internal);
        $body = self::body($data->body);
        return storage::locked('ticket:' . $id, static function () use ($DB, $USER, $id, $data, $internal, $body): int {
            $ticket = self::get($id);
            $staff = access::staff($ticket);
            access::require(!$internal || $staff);
            if ($ticket->resolvedat) {
                throw new moodle_exception('resolvedticket', access::COMPONENT);
            }
            if (
                $DB->count_records_select(
                    storage::POST,
                    'ticketid = :id AND userid = :userid AND timecreated > :cutoff',
                    ['id' => $id, 'userid' => $USER->id, 'cutoff' => time() - HOURSECS]
                ) >= 40
            ) {
                throw new moodle_exception('ratelimit', access::COMPONENT);
            }
            return storage::transaction(static function () use ($DB, $USER, $ticket, $id, $data, $internal, $body, $staff): int {
                $now = time();
                $postid = self::insert_post($id, (int)$USER->id, $body, $internal, (int)($data->attachments ?? 0), $now);
                $record = ['id' => $id, 'timemodified' => $now];
                if ($staff && !$internal && $ticket->requesterid != $USER->id && !$ticket->firstresponse) {
                    $record['firstresponse'] = $now;
                    self::mark_breach($ticket, 'response', $now, $record);
                }
                $DB->update_record(storage::TICKET, $record);
                storage::audit($id, $internal ? 'internalnote' : 'comment', ['post' => $postid]);
                $recipients = $internal ? [(int)$ticket->assigneeid] : [(int)$ticket->requesterid, (int)$ticket->assigneeid];
                notification::queue($id, array_diff($recipients, [(int)$USER->id]));
                return $postid;
            });
        });
    }

    /**
     * Change staff classification and assignment with current role verification.
     *
     * @param int $id Ticket ID
     * @param stdClass $data Triage values
     */
    public static function triage(int $id, stdClass $data): void {
        global $DB, $USER;
        storage::locked('ticket:' . $id, static function () use ($DB, $id, $data, $USER): void {
            $ticket = self::get($id);
            access::require(access::staff($ticket));
            $target = (int)($data->assigneeid ?? $ticket->assigneeid);
            if ($target !== (int)$ticket->assigneeid) {
                access::require(access::can('supervise') && (!$target || access::technician($target)));
            }
            if ($ticket->resolvedat) {
                throw new moodle_exception('resolvedticket', access::COMPONENT);
            }
            $changes = [];
            $record = ['id' => $id, 'assigneeid' => $target];
            if ($target !== (int)$ticket->assigneeid) {
                $changes['assigneeid'] = [(int)$ticket->assigneeid, $target];
            }
            foreach (self::DIMENSIONS as $kind) {
                $key = $kind . 'id';
                $selected = (int)$data->$key;
                $selection = catalog::entry($kind, $selected, $selected !== (int)$ticket->$key);
                $record[$key] = $selection->id;
                if ((int)$ticket->$key !== $selected) {
                    $changes[$key] = [(int)$ticket->$key, $selected];
                }
            }
            if (!$changes) {
                return;
            }
            $status = catalog::entry('status', (int)$record['statusid'], false);
            $now = time();
            $record['timemodified'] = $now;
            storage::transaction(static function () use (
                $DB,
                $USER,
                $ticket,
                $status,
                $id,
                $target,
                $changes,
                $record,
                $now
            ): void {
                $update = $record;
                if ($status->terminal) {
                    $update['resolvedat'] = $now;
                    self::mark_breach($ticket, 'resolve', $now, $update);
                    if (!$ticket->firstresponse) {
                        self::mark_breach($ticket, 'response', $now, $update);
                    }
                }
                $DB->update_record(storage::TICKET, $update);
                storage::audit($id, 'triage', $changes);
                notification::queue($id, array_diff([(int)$ticket->requesterid, $target], [(int)$USER->id]));
            });
        });
    }

    /**
     * Escalate an assigned or supervised ticket with a recorded staff decision.
     *
     * @param int $id Ticket ID
     */
    public static function escalate(int $id): void {
        global $DB;
        storage::locked('ticket:' . $id, static function () use ($DB, $id): void {
            $ticket = self::get($id);
            access::require(access::staff($ticket));
            if ($ticket->resolvedat) {
                throw new moodle_exception('resolvedticket', access::COMPONENT);
            }
            if (
                $DB->record_exists_select(
                    storage::AUDIT,
                    'ticketid = :ticket AND action = :action AND timecreated > :cutoff',
                    ['ticket' => $id, 'action' => 'manualescalation', 'cutoff' => time() - 15 * MINSECS]
                )
            ) {
                throw new moodle_exception('escalationrecent', access::COMPONENT);
            }
            storage::transaction(static function () use ($ticket, $id): void {
                storage::audit($id, 'manualescalation');
                notification::escalate($ticket);
            });
        });
    }

    /**
     * Record a resolved ticket's single requester satisfaction rating.
     *
     * @param int $id Ticket ID
     * @param int $rating One to five
     */
    public static function rate(int $id, int $rating): void {
        global $DB, $USER;
        storage::locked('ticket:' . $id, static function () use ($DB, $USER, $id, $rating): void {
            $ticket = self::get($id);
            access::require($ticket->requesterid == $USER->id && access::can('request'));
            if (!$ticket->resolvedat || $ticket->rating || $rating < 1 || $rating > 5) {
                throw new moodle_exception('invalidrating', access::COMPONENT);
            }
            storage::transaction(static function () use ($id, $rating, $DB): void {
                $now = time();
                $DB->update_record(storage::TICKET, ['id' => $id, 'rating' => $rating, 'ratedat' => $now, 'timemodified' => $now]);
                storage::audit($id, 'rating');
            });
        });
    }

    /**
     * Record an unmet milestone independently of whether the periodic task already processed it.
     *
     * @param stdClass $ticket Ticket snapshot
     * @param string $type response or resolve
     * @param int $now Current timestamp
     * @param array $record Ticket fields being updated
     */
    public static function mark_breach(stdClass $ticket, string $type, int $now, array &$record): void {
        $deadline = $type === 'response' ? $ticket->firstdue : $ticket->resolvedue;
        $field = $type === 'response' ? 'responsebreach' : 'resolvebreach';
        if (!$ticket->$field && $now > $deadline) {
            $record[$field] = $now;
            storage::audit((int)$ticket->id, $type . 'breach', [], 0);
            notification::escalate($ticket);
        }
    }

    /**
     * Normalize and validate plaintext ticket content.
     *
     * @param string $body Input text
     * @return string
     */
    private static function body(string $body): string {
        $body = trim($body);
        if ($body === '' || core_text::strlen($body) > 8000) {
            throw new moodle_exception('invalidbody', access::COMPONENT);
        }
        return $body;
    }

    /**
     * Save a message and its attachment area.
     *
     * @param int $ticketid Ticket ID
     * @param int $userid Author
     * @param string $body Plain text
     * @param bool $internal Staff-only note
     * @param int $draftid File manager draft area
     * @param int $now Current timestamp
     * @return int
     */
    private static function insert_post(int $ticketid, int $userid, string $body, bool $internal, int $draftid, int $now): int {
        global $DB;
        $id = (int)$DB->insert_record(storage::POST, [
            'ticketid' => $ticketid, 'userid' => $userid, 'body' => $body, 'internal' => (int)$internal, 'timecreated' => $now,
        ]);
        attachments::save($draftid, $id);
        return $id;
    }
}
