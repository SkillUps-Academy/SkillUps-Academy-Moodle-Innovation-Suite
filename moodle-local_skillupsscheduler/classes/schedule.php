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

namespace local_skillupsscheduler;

/**
 * Write, publish, reschedule, substitute and cancel sessions.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schedule {
    /**
     * Validate and persist a scheduling change.
     *
     * @param int $siteid Campus ID
     * @param \stdClass $input Submitted values
     * @return array
     */
    public static function save(int $siteid, \stdClass $input): array {
        global $DB;
        return store::atomic(static function () use ($DB, $siteid, $input): array {
            $site = store::get(store::SITE, $siteid);
            scope::require_staff($site, 'plan');
            $old = !empty($input->id) ? store::get(store::SLOT, (int)$input->id) : null;
            if ($old && $old->siteid != $siteid) {
                scope::fail();
            }
            if ($old && ($old->status === 'cancelled' || $old->revision != $input->revision)) {
                scope::fail('stale');
            }
            $publishing = !empty($input->publish) || ($old && $old->status === 'published');
            if ($publishing) {
                scope::require_staff($site, 'publish');
            }
            $courseid = $old ? $old->courseid : (int)$input->courseid;
            $course = $DB->get_record('course', ['id' => $courseid]);
            $groupid = $old ? $old->groupid : (int)$input->groupid;
            if (
                !$course || !scope::course_in_site($site, $course) ||
                ($groupid && !$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $courseid]))
            ) {
                scope::fail('invalidaudience');
            }
            $teacherid = (int)$input->teacherid;
            if ($teacherid && !scope::teacher((int)$courseid, $teacherid)) {
                scope::fail('invalidteacher');
            }
            $mode = $input->mode;
            $title = trim($input->title);
            $reason = trim($input->reason ?? '');
            if (
                !in_array($mode, ['onsite', 'online', 'hybrid'], true) || $title === '' ||
                \core_text::strlen($title) > 120 || ($old && $reason === '') || \core_text::strlen($reason) > 1000
            ) {
                scope::fail('invalidinput');
            }
            $cycle = self::resource(store::CYCLE, (int)$input->cycleid, $siteid);
            $block = self::resource(store::BLOCK, (int)$input->blockid, $siteid);
            $room = self::resource(store::ROOM, (int)$input->roomid, $siteid);
            $virtual = self::resource(store::ROOM, (int)$input->virtualid, $siteid);
            if (
                !$cycle || ($room && ($room->kind !== 'physical' || $mode === 'online')) ||
                ($virtual && ($virtual->kind !== 'virtual' || $mode === 'onsite'))
            ) {
                scope::fail('invalidinput');
            }
            $count = $old ? 1 : (int)$input->count;
            $step = (int)($input->interval ?? 7);
            $series = $old ? $old->series : ($input->series ?? bin2hex(random_bytes(16)));
            if ($count < 1 || $count > 40 || !in_array($step, [1, 7, 14], true) || !preg_match('/^[a-f0-9]{32}$/D', $series)) {
                scope::fail('invalidinput');
            }
            if (!$old && $DB->record_exists(store::SLOT, ['series' => $series])) {
                scope::fail('stale');
            }
            $date = catalog::local_date($input->day, $site->timezone);
            $occurrences = [];
            $operation = $old ? ($old->status === 'draft' && $publishing ? 'published' :
                ($old->teacherid != $teacherid ? 'substituted' : 'updated')) : ($publishing ? 'published' : 'created');
            for ($i = 0; $i < $count; $i++) {
                $day = $date->modify('+' . ($step * $i) . ' days')->format('Y-m-d');
                $start = catalog::local_time($day, $input->begin, $site->timezone)->getTimestamp();
                $end = catalog::local_time($day, $input->finish, $site->timezone)->getTimestamp();
                if ($end <= $start) {
                    scope::fail('invalidtime');
                }
                $slot = (object)[
                    'siteid' => $siteid, 'cycleid' => $cycle->id, 'courseid' => $courseid, 'groupid' => $groupid,
                    'teacherid' => $teacherid, 'roomid' => $room->id ?? 0, 'virtualid' => $virtual->id ?? 0,
                    'blockid' => $block->id ?? 0, 'title' => $title, 'mode' => $mode, 'starts' => $start, 'ends' => $end,
                    'status' => $publishing ? 'published' : 'draft', 'revision' => ((int)($old->revision ?? 0)) + 1,
                    'series' => $series, 'ordinal' => $old->ordinal ?? $i, 'eventid' => $old->eventid ?? 0, 'changed' => time(),
                ];
                if ($old) {
                    $slot->id = $old->id;
                }
                if ($publishing) {
                    $issues = conflicts::detect($slot);
                    if ($issues) {
                        $labels = array_map(static fn(string $key): string => scope::text($key), $issues);
                        scope::fail('publicationblocked', "$day — " . implode(', ', $labels));
                    }
                }
                $occurrences[] = $slot;
            }
            $written = [];
            foreach ($occurrences as $slot) {
                if ($old) {
                    $DB->update_record(store::SLOT, $slot);
                } else {
                    $slot->id = $DB->insert_record(store::SLOT, $slot);
                }
                self::finish($slot, $old, $operation, $reason);
                $written[] = (int)$slot->id;
            }
            return $written;
        });
    }

    /**
     * Cancel.
     *
     * @param int $id Record ID
     * @param int $revision Expected revision
     * @param string $reason Reason entered by the operator
     */
    public static function cancel(int $id, int $revision, string $reason): void {
        global $DB;
        store::atomic(static function () use ($DB, $id, $revision, $reason): void {
            $old = store::get(store::SLOT, $id);
            $site = store::get(store::SITE, (int)$old->siteid);
            scope::require_staff($site, 'plan');
            if ($old->status === 'published') {
                scope::require_staff($site, 'publish');
            }
            if ($old->status === 'cancelled' || $old->revision != $revision) {
                scope::fail('stale');
            }
            $reason = trim($reason);
            if ($reason === '' || \core_text::strlen($reason) > 1000) {
                scope::fail('invalidinput');
            }
            $slot = clone $old;
            $slot->status = 'cancelled';
            $slot->revision++;
            $slot->changed = time();
            $DB->update_record(store::SLOT, $slot);
            self::finish($slot, $old, 'cancelled', $reason);
        });
    }

    /**
     * Resource.
     *
     * @param string $table Table
     * @param int $id Record ID
     * @param int $siteid Campus ID
     * @return \stdClass|null
     */
    private static function resource(string $table, int $id, int $siteid): ?\stdClass {
        if (!$id) {
            return null;
        }
        $record = store::get($table, $id);
        if ($record->siteid != $siteid) {
            scope::fail();
        }
        return $record;
    }

    /**
     * Finish.
     *
     * @param \stdClass $slot Session data
     * @param \stdClass|null $old Previous revision
     * @param string $operation Change or transaction operation
     * @param string $reason Reason entered by the operator
     */
    private static function finish(\stdClass $slot, ?\stdClass $old, string $operation, string $reason): void {
        global $DB;
        calendar::sync($slot);
        store::audit($slot, $old, $operation, $reason);
        if ($slot->status === 'published' || ($old && $old->status === 'published')) {
            $DB->insert_record(store::QUEUE, (object)[
                'slotid' => $slot->id, 'revision' => $slot->revision, 'operation' => $operation,
                'oldteacher' => $old->teacherid ?? 0, 'newteacher' => $slot->teacherid, 'nexttry' => time(),
            ]);
        }
    }
}
