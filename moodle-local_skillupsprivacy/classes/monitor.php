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

namespace local_skillupsprivacy;

/**
 * Refresh safe native statuses and send bounded deadline warnings.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class monitor {
    /**
     * Refresh linked records and process due reminders.
     */
    public static function run(): void {
        global $DB;
        $now = time();
        $limit = 50;
        $cases = $DB->get_records_select(
            store::CASES,
            'archived = 0 AND nativeid IS NOT NULL AND checkedat < :due',
            ['due' => $now - 15 * MINSECS],
            'checkedat ASC, id ASC',
            '*',
            0,
            $limit
        );
        foreach ($cases as $row) {
            self::refresh((int)$row->id);
        }
        if (get_config(access::C, 'notifyenabled')) {
            self::due($now, 2, $limit);
            self::due($now, 1, $limit);
        }
        $days = max(30, min(730, (int)(get_config(access::C, 'accessretention') ?: 365)));
        $DB->delete_records_select(
            store::AUDIT,
            "(caseid = 0 OR operation = 'viewcase') AND timecreated < :cutoff",
            ['cutoff' => $now - $days * DAYSECS]
        );
        $DB->set_field_select(store::NOTICE, 'status', 'uncertain', 'status = :state AND timecreated < :cutoff', [
            'state' => 'sending', 'cutoff' => $now - HOURSECS,
        ]);
    }

    /**
     * Reconcile one local snapshot without changing native state.
     *
     * @param int $id Internal case
     */
    public static function refresh(int $id): void {
        store::atomic(static function () use ($id): void {
            global $DB;
            $case = $DB->get_record(store::CASES, ['id' => $id]);
            if (!$case || $case->archived || !$case->nativeid) {
                return;
            }
            $native = bridge::read((int)$case->nativeid);
            $same = $native && $native['userid'] === (int)$case->userid && $native['kind'] === $case->kind;
            $status = $same ? $native['status'] : -2;
            $DB->update_record(store::CASES, ['id' => $id, 'nativestatus' => $status, 'checkedat' => time()]);
            if ($case->nativestatus != $status) {
                store::audit('synced', $id, (string)$case->nativestatus, (string)$status, automatic: true);
            }
        });
    }

    /**
     * Queue a bounded set at a time and skip existing claims before selecting the next batch.
     *
     * @param int $now Scan time
     * @param int $level Warning or overdue
     * @param int $limit Query limit
     */
    private static function due(int $now, int $level, int $limit): void {
        global $DB;
        $case = store::CASES;
        $notice = store::NOTICE;
        $window = max(1, min(30, (int)(get_config(access::C, 'warningdays') ?: 3))) * DAYSECS;
        $params = ['level' => $level, 'now' => $now, 'open' => 'open', 'review' => 'review', 'approved' => 'approved'];
        $where = $level === 2 ? 'c.due <= :now' : 'c.due > :now AND c.due <= :soon';
        if ($level === 1) {
            $params['soon'] = $now + $window;
        }
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.ownerid, c.due FROM {{$case}} c
              WHERE c.archived = 0 AND c.ownerid > 0 AND c.state IN (:open, :review, :approved) AND $where
                AND NOT EXISTS (SELECT 1 FROM {{$notice}} n
                                 WHERE n.caseid = c.id AND n.userid = c.ownerid AND n.due = c.due AND n.level = :level)
           ORDER BY c.due ASC, c.id ASC",
            $params,
            0,
            $limit
        );
        foreach ($rows as $row) {
            $claim = store::atomic(static function () use ($DB, $row, $level, $now, $window): ?int {
                $current = $DB->get_record(store::CASES, ['id' => $row->id]);
                if (!$current) {
                    return null;
                }
                $nowlevel = $current->due <= $now ? 2 : ($current->due <= $now + $window ? 1 : 0);
                if (
                    $current->archived || !in_array($current->state, ['open', 'review', 'approved'], true) ||
                    $current->ownerid != $row->ownerid || $current->due != $row->due || $level !== $nowlevel
                ) {
                    return null;
                }
                $key = ['caseid' => $row->id, 'userid' => $row->ownerid, 'due' => $row->due, 'level' => $level];
                if ($DB->record_exists(store::NOTICE, $key)) {
                    return null;
                }
                return (int)$DB->insert_record(store::NOTICE, $key + ['status' => 'sending', 'timecreated' => $now]);
            });
            if ($claim) {
                self::send($claim, (int)$row->id, (int)$row->ownerid);
            }
        }
    }

    /**
     * Recheck access before messaging. Protect subject identity and case material.
     *
     * @param int $claim Delivery claim
     * @param int $caseid Case
     * @param int $userid Responsible officer
     */
    private static function send(int $claim, int $caseid, int $userid): void {
        global $DB;
        $status = 'skipped';
        $row = $DB->get_record(store::CASES, ['id' => $caseid]);
        if (
            $row && !$row->archived && $row->ownerid == $userid && in_array($row->state, ['open', 'review', 'approved'], true) &&
            access::allowed($userid, 'manage') && get_config(access::C, 'notifyenabled')
        ) {
            try {
                $title = access::text('messagesubject');
                $body = access::text('messagebody');
                $message = new \core\message\message();
                $message->component = access::C;
                $message->name = 'deadline';
                $message->userfrom = \core_user::get_noreply_user();
                $message->userto = \core_user::get_user($userid);
                $message->subject = $title;
                $message->fullmessage = $body;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = '<p>' . s($body) . '</p>';
                $message->smallmessage = $title;
                $message->notification = 1;
                $message->contexturl = access::url()->out(false);
                $message->contexturlname = access::text('pluginname');
                $status = message_send($message) ? 'sent' : 'failed';
            } catch (\Throwable $failure) {
                $status = 'failed';
            }
        }
        store::atomic(static function () use ($DB, $claim, $caseid, $status): void {
            if ($DB->record_exists(store::NOTICE, ['id' => $claim])) {
                $DB->set_field(store::NOTICE, 'status', $status, ['id' => $claim]);
                store::audit('notified', $caseid, result: $status, automatic: true);
            }
        });
    }
}
