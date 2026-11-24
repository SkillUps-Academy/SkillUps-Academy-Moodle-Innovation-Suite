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

use stdClass;

/**
 * Register attendance, prevent duplicates and finalize an eligible roster.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class marks {
    /** @var array Supported attendance outcomes. */
    public const STATES = ['present', 'absent', 'late', 'excused', 'pending'];

    /**
     * Validate a one-minute rotating QR token scoped to this session and revision.
     *
     * @param stdClass $session Session
     * @param int $now Timestamp
     * @return string
     */
    public static function token(stdClass $session, int $now): string {
        $slot = intdiv($now, 60);
        $data = implode(':', [$session->id, $session->attendanceid, $session->revision, $slot]);
        return $slot . '.' . substr(hash_hmac('sha256', $data, $session->qrkey ?? ''), 0, 32);
    }

    /**
     * Process a student's own authenticated entrance or exit.
     *
     * @param activity $activity Activity
     * @param int $id Session
     * @param string $pin Submitted access code
     * @param string $token Submitted QR token
     * @param bool $leave Record an exit
     * @param int|null $now Optional trusted test timestamp
     * @return string Current attendance outcome
     */
    public static function self_record(
        activity $activity,
        int $id,
        string $pin,
        string $token,
        bool $leave = false,
        ?int $now = null,
    ): string {
        global $USER, $DB;
        $activity->require('recordown');
        $now ??= time();
        $userid = (int)$USER->id;
        $registration = static function () use ($DB, $activity, $id, $pin, $token, $leave, $now, $userid): array {
            $session = store::session($activity, $id);
            if (
                !$activity->eligible($userid, $session->groupid) ||
                    !session::is_open($session, $now) || $session->method === 'manual'
            ) {
                return ['error' => 'closedwindow'];
            }
            $key = ['sessionid' => $id, 'userid' => $userid];
            $current = $DB->get_record(store::MARK, $key);
            if ($leave) {
                $valid = $current && $current->timein && in_array($current->state, ['present', 'late'], true);
                if (!$session->checkout || !$valid) {
                    return ['error' => 'noentry'];
                }
                if (!$current->timeexit && $now >= $current->timein) {
                    $DB->update_record(store::MARK, ['id' => $current->id, 'timeexit' => $now, 'modified' => $now]);
                    store::audit($activity, 'checkout', $id, $userid, $current->state, $current->state);
                }
                return ['state' => $current->state];
            }
            if ($current && $current->state !== 'pending') {
                return ['state' => $current->state];
            }
            $counter = $DB->get_record(store::FAIL, $key);
            $fails = $counter && $counter->expires > $now ? $counter->failures : 0;
            if ($fails >= 5) {
                return ['error' => 'limited'];
            }
            if (!self::valid_credential($session, $pin, $token, $now)) {
                $values = ['failures' => $fails + 1, 'expires' => $fails ? $counter->expires : $now + 300];
                if ($counter) {
                    $DB->update_record(store::FAIL, ['id' => $counter->id] + $values);
                } else {
                    $DB->insert_record(store::FAIL, $key + $values);
                }
                return ['error' => 'badcredential'];
            }
            $DB->delete_records(store::FAIL, $key);
            $result = $now > $session->starts + $session->late * MINSECS ? 'late' : 'present';
            self::save_row($activity, $id, $userid, $result, 'self', $current ?: null, $now);
            return ['state' => $result];
        };
        $result = store::atomic($activity->record->id, $registration);
        if (isset($result['error'])) {
            activity::fail($result['error']);
        }
        return $result['state'];
    }

    /**
     * Correct or mark a visible page of authorized students.
     *
     * @param activity $activity Activity
     * @param int $id Session
     * @param array $changes USERID to state map
     */
    public static function manual(activity $activity, int $id, array $changes): void {
        global $DB;
        $activity->require('mark');
        if (!$changes || count($changes) > 50) {
            activity::fail('badmark');
        }
        store::atomic($activity->record->id, static function () use ($DB, $activity, $id, $changes): void {
            $session = store::session($activity, $id);
            $opens = $session->starts - $session->early * MINSECS;
            if ($session->state === 'cancelled' || !$activity->staff_group($session->groupid) || time() < $opens) {
                activity::fail('closedwindow');
            }
            foreach ($changes as $userid => $state) {
                if (
                    !in_array($state, self::STATES, true) || !ctype_digit((string)$userid) ||
                    !$activity->eligible((int)$userid, $session->groupid) || ($state === 'pending' && $session->state === 'final')
                ) {
                    activity::fail('badmark');
                }
            }
            foreach ($changes as $userid => $state) {
                $existing = $DB->get_record(store::MARK, ['sessionid' => $id, 'userid' => $userid]);
                if ((!$existing && $state !== 'pending') || ($existing && $existing->state !== $state)) {
                    self::save_row($activity, $id, (int)$userid, $state, 'manual', $existing ?: null);
                }
            }
        });
    }

    /**
     * Finalize the eligible roster after the registration window.
     *
     * @param activity $activity Activity
     * @param int $id Session
     * @param bool $automatic Scheduled close
     * @return bool True only for a newly finalized session
     */
    public static function finalize(activity $activity, int $id, bool $automatic = false): bool {
        global $DB;
        if (!$automatic) {
            $activity->require('mark');
        }
        return store::atomic($activity->record->id, static function () use ($DB, $activity, $id, $automatic): bool {
            $session = store::session($activity, $id);
            if (!$automatic && !$activity->staff_group($session->groupid)) {
                activity::fail('unavailable');
            }
            if ($session->state !== 'scheduled' || time() < $session->ends + $session->grace * MINSECS) {
                return false;
            }
            if (!$activity->valid_group($session->groupid)) {
                // A deleted group does not turn into an activity-wide roster.
                $DB->set_field(store::SESSION, 'state', 'cancelled', ['id' => $id]);
                session::remove_calendar($session);
                store::audit($activity, 'cancelled', $id, automatic: $automatic);
                return false;
            }
            for ($offset = 0;; $offset += 500) {
                $roster = $activity->roster($session->groupid, $offset, 500);
                if (!$roster) {
                    break;
                }
                [$insql, $params] = $DB->get_in_or_equal(array_keys($roster), SQL_PARAMS_NAMED, 'eligible');
                $known = $DB->get_records_select(
                    store::MARK,
                    "sessionid = :session AND userid $insql",
                    $params + ['session' => $id],
                    '',
                    'userid,id,state'
                );
                $new = [];
                foreach ($roster as $student) {
                    if (!isset($known[$student->id])) {
                        $new[] = [
                            'sessionid' => $id, 'userid' => $student->id, 'state' => 'absent',
                            'source' => 'closing', 'modified' => time(),
                        ];
                    }
                }
                if ($new) {
                    $DB->insert_records(store::MARK, $new);
                }
            }
            $wherestate = ['sessionid' => $id, 'state' => 'pending'];
            $DB->set_field(store::MARK, 'state', 'absent', $wherestate);
            $DB->update_record(store::SESSION, [
                'id' => $id, 'state' => 'final', 'pin' => null, 'qrkey' => null, 'modified' => time(),
            ]);
            $DB->delete_records(store::FAIL, ['sessionid' => $id]);
            store::audit($activity, 'finalized', $id, before: 'scheduled', after: 'final', automatic: $automatic);
            return true;
        });
    }

    /**
     * Persist a result and emit an audit event. Caller holds the activity lock.
     *
     * @param activity $activity Activity
     * @param int $id Session
     * @param int $userid Student
     * @param string $state Outcome
     * @param string $source Origin
     * @param stdClass|null $current Previous mark
     * @param int $timein Optional entry timestamp
     */
    public static function save_row(
        activity $activity,
        int $id,
        int $userid,
        string $state,
        string $source,
        ?stdClass $current,
        int $timein = 0,
    ): void {
        global $DB;
        $data = [
            'sessionid' => $id, 'userid' => $userid, 'state' => $state, 'source' => $source,
            'timein' => $current?->timein ?: $timein,
            'timeexit' => $current?->timeexit ?? 0, 'modified' => time(),
        ];
        if ($current) {
            $DB->update_record(store::MARK, $data + ['id' => $current->id]);
        } else {
            $DB->insert_record(store::MARK, $data);
        }
        store::audit($activity, 'marked', $id, $userid, $current->state ?? 'pending', $state);
    }

    /**
     * Compare the selected registration credential without storing learner-supplied data.
     *
     * @param stdClass $record Session
     * @param string $pin Access code
     * @param string $token QR token
     * @param int $now Timestamp
     * @return bool
     */
    private static function valid_credential(stdClass $record, string $pin, string $token, int $now): bool {
        return match ($record->method) {
            'open' => true,
            'code' => $record->pin && strlen($pin) <= 32 && password_verify($pin, $record->pin),
            'qr' => $record->qrkey && strlen($token) < 64 && (
                hash_equals(self::token($record, $now), $token) || hash_equals(self::token($record, $now - 60), $token)
            ),
            default => false,
        };
    }
}
