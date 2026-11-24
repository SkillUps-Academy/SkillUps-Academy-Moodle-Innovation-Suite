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

use DateInterval;
use DateTimeImmutable;
use stdClass;

/**
 * Manage activity sessions, recurrence and native calendar events.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session {
    /** @var array Attendance delivery modes. */
    public const MODALITIES = ['onsite', 'online', 'hybrid'];
    /** @var array Self-service methods. */
    public const METHODS = ['manual', 'open', 'code', 'qr'];

    /**
     * List dates for individual or recurrent schedule creation.
     *
     * @param int $starts Initial start
     * @param int $ends Initial close
     * @param int $count Number of occurrences
     * @param string $frequency Day or week
     * @param \DateTimeZone $zone Operator time zone
     * @return array
     */
    public static function dates(int $starts, int $ends, int $count, string $frequency, \DateTimeZone $zone): array {
        if (
            $starts <= 0 || $ends <= $starts || $ends - $starts > DAYSECS || $count < 1 || $count > 26 ||
            !in_array($frequency, ['day', 'week'], true)
        ) {
            activity::fail('badschedule');
        }
        $start = (new DateTimeImmutable('@' . $starts))->setTimezone($zone);
        $end = (new DateTimeImmutable('@' . $ends))->setTimezone($zone);
        $step = new DateInterval($frequency === 'day' ? 'P1D' : 'P1W');
        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $from = $start->getTimestamp();
            $until = $end->getTimestamp();
            if ($until <= $from) {
                activity::fail('badschedule');
            }
            $dates[] = [$from, $until];
            $start = $start->add($step);
            $end = $end->add($step);
        }
        return $dates;
    }

    /**
     * Create a session series or revise a single scheduled session.
     *
     * @param activity $activity Activity
     * @param stdClass $input Form values
     * @return int First affected session
     */
    public static function save(activity $activity, stdClass $input): int {
        global $USER, $DB;
        $activity->require('sessions');
        $id = (int)$input->session;
        $teacher = (int)($input->teacherid ?: $USER->id);
        $name = trim($input->name);
        if (
            $name === '' || \core_text::strlen($name) > 100 || !in_array($input->modality, self::MODALITIES, true) ||
            !in_array($input->method, self::METHODS, true) || !$activity->staff_group($input->groupid) ||
            !$activity->can('mark', $teacher) || !$activity->staff_group($input->groupid, $teacher)
        ) {
            activity::fail('badschedule');
        }
        foreach (['early', 'late', 'grace'] as $timing) {
            if ($input->$timing < 0 || $input->$timing > 120) {
                activity::fail('badschedule');
            }
        }
        $dates = self::dates(
            $input->starts,
            $input->ends,
            $id ? 1 : $input->occurrences,
            $input->frequency ?: 'week',
            \core_date::get_user_timezone_object(),
        );
        $pin = trim($input->newpin ?? '');
        if ($pin !== '' && ($input->method !== 'code' || !preg_match('/^[a-zA-Z0-9]{6,32}$/D', $pin))) {
            activity::fail('badpin');
        }
        return store::atomic($activity->record->id, static function () use (
            $DB,
            $activity,
            $id,
            $input,
            $name,
            $dates,
            $teacher,
            $pin,
        ): int {
            $old = $id ? store::session($activity, $id) : null;
            if (
                $old && (!$activity->staff_group($old->groupid) ||
                    $old->state !== 'scheduled' || $old->revision != $input->revision)
            ) {
                activity::fail('stale');
            }
            if (
                $old && ($old->starts != $input->starts || $old->ends != $input->ends || $old->groupid != $input->groupid) &&
                $DB->record_exists(store::MARK, ['sessionid' => $id])
            ) {
                activity::fail('recorded');
            }
            if ($input->method === 'code' && $pin === '' && empty($old->pin)) {
                activity::fail('badpin');
            }
            $hash = $input->method === 'code' ? ($pin === '' ? $old->pin : password_hash($pin, PASSWORD_DEFAULT)) : null;
            $series = $old->serieskey ?? (count($dates) > 1 ? bin2hex(random_bytes(24)) : null);
            $first = $id;
            foreach ($dates as [$starts, $ends]) {
                $values = [
                    'attendanceid' => $activity->record->id, 'name' => $name, 'groupid' => $input->groupid,
                    'teacherid' => $teacher, 'modality' => $input->modality, 'method' => $input->method,
                    'starts' => $starts, 'ends' => $ends, 'early' => $input->early, 'grace' => $input->grace,
                    'late' => $input->late, 'checkout' => $input->checkout, 'extra' => $input->extra,
                    'pin' => $hash, 'qrkey' => $input->method === 'qr' ? bin2hex(random_bytes(32)) : null,
                    'serieskey' => $series, 'modified' => time(),
                ];
                $sid = $id;
                if ($id) {
                    $DB->update_record(store::SESSION, $values + ['id' => $id, 'revision' => $old->revision + 1]);
                } else {
                    $sid = (int)$DB->insert_record(store::SESSION, $values);
                }
                self::calendar($activity, store::session($activity, $sid));
                $changed = $old && ($old->starts != $starts || $old->ends != $ends);
                $action = $changed ? 'rescheduled' : ($id ? 'edited' : 'created');
                store::audit($activity, $action, $sid);
                $first = $first ?: $sid;
            }
            return $first;
        });
    }

    /**
     * Cancel one session and invalidate access credentials.
     *
     * @param activity $activity Activity
     * @param int $id Session
     */
    public static function cancel(activity $activity, int $id): void {
        global $DB;
        $activity->require('sessions');
        store::atomic($activity->record->id, static function () use ($DB, $activity, $id): void {
            $record = store::session($activity, $id);
            if (!$activity->staff_group($record->groupid)) {
                activity::fail('unavailable');
            }
            if ($record->state === 'cancelled') {
                return;
            }
            self::remove_calendar($record);
            $DB->update_record(store::SESSION, [
                'id' => $id, 'state' => 'cancelled', 'eventid' => 0, 'pin' => null, 'qrkey' => null,
                'revision' => $record->revision + 1, 'modified' => time(),
            ]);
            store::audit($activity, 'cancelled', $id, before: $record->state, after: 'cancelled');
        });
    }

    /**
     * Determine whether the student self-service window is open.
     *
     * @param stdClass $record Session
     * @param int $now Current timestamp
     * @return bool
     */
    public static function is_open(stdClass $record, int $now): bool {
        return $record->state === 'scheduled' && $now >= $record->starts - $record->early * MINSECS &&
            $now < $record->ends + $record->grace * MINSECS;
    }

    /**
     * Authorize the current viewer for a session.
     *
     * @param activity $activity Activity
     * @param stdClass $record Session
     * @param int $userid Optional viewer
     * @return bool
     */
    public static function visible(activity $activity, stdClass $record, int $userid = 0): bool {
        global $USER;
        if (
            ($activity->can('sessions', $userid) || $activity->can('mark', $userid) ||
                $activity->can('report', $userid) || $activity->can('review', $userid)) &&
            $activity->staff_group($record->groupid, $userid)
        ) {
            return true;
        }
        return $activity->eligible($userid ?: (int)$USER->id, $record->groupid);
    }

    /**
     * Add or refresh the native activity calendar event.
     *
     * @param activity $activity Activity
     * @param stdClass $record Session
     */
    public static function calendar(activity $activity, stdClass $record): void {
        global $CFG, $DB;
        if ($record->state === 'cancelled' || !$activity->valid_group($record->groupid)) {
            return;
        }
        require_once($CFG->dirroot . '/calendar/lib.php');
        $data = (object)[
            'name' => $record->name, 'description' => '', 'format' => FORMAT_PLAIN,
            'courseid' => $activity->course->id, 'groupid' => $record->groupid, 'userid' => 0,
            'modulename' => 'skillupsattendance', 'instance' => $activity->record->id,
            'eventtype' => 'attendance_session', 'timestart' => $record->starts,
            'timeduration' => $record->ends - $record->starts, 'visible' => $activity->cm->visible,
        ];
        if (
            $record->eventid && $DB->record_exists('event', ['id' => $record->eventid, 'instance' => $activity->record->id,
                'modulename' => 'skillupsattendance', 'eventtype' => 'attendance_session'])
        ) {
            \calendar_event::load($record->eventid)->update($data, false);
        } else {
            $event = \calendar_event::create($data, false);
            $DB->set_field(store::SESSION, 'eventid', $event->id, ['id' => $record->id]);
        }
    }

    /**
     * Delete the native event if it still belongs to this session.
     *
     * @param stdClass $record Session
     */
    public static function remove_calendar(stdClass $record): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $keys = ['id' => $record->eventid, 'modulename' => 'skillupsattendance', 'instance' => $record->attendanceid];
        if ($record->eventid && $DB->record_exists('event', $keys)) {
            \calendar_event::load($record->eventid)->delete(false);
        }
    }
}
