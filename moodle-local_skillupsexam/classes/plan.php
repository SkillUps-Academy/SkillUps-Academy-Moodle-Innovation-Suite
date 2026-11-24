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

namespace local_skillupsexam;

use stdClass;

/**
 * Plan and cancel convocations with optimistic revisions and reservation validation.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan {
    /**
     * Create or update a convocation.
     *
     * @param stdClass $course Moodle course
     * @param stdClass $input Validated form fields
     * @return int Convocation ID
     */
    public static function save(stdClass $course, stdClass $input): int {
        global $DB;
        return store::write(static function () use ($DB, $course, $input): int {
            $old = !empty($input->id) ? store::get(store::EXAM, (int)$input->id) : null;
            if ($old) {
                if (!scope::exam($old, $course, 'edit')) {
                    scope::fail();
                }
                if ($old->status === 'cancelled' || $old->revision != $input->revision) {
                    scope::fail('stale');
                }
            } else if (!scope::course($course, 'plan')) {
                scope::fail();
            }
            $context = \context_course::instance($course->id);
            $name = trim($input->name);
            $reason = trim($input->reason ?? '');
            $instruction = trim($input->instruction ?? '');
            if (
                $name === '' || \core_text::strlen($name) > 160 || \core_text::strlen($instruction) > 8000 ||
                    ($old && $reason === '') || \core_text::strlen($reason) > 1000 ||
                    !in_array($input->mode, ['onsite', 'online', 'hybrid'], true) ||
                    !in_array($input->regmode, ['active', 'manual'], true)
            ) {
                scope::fail('invalidinput');
            }
            $published = !empty($input->publish) || ($old && $old->status === 'scheduled');
            if ($published && !scope::course($course, 'edit')) {
                scope::fail();
            }
            $key = $old->requestkey ?? $input->requestkey ?? '';
            if (!preg_match('/^[a-f0-9]{32}$/D', $key) || (!$old && $DB->record_exists(store::EXAM, ['requestkey' => $key]))) {
                scope::fail('stale');
            }
            $period = store::get(store::PERIOD, (int)$input->periodid);
            $room = $input->roomid ? store::get(store::ROOM, (int)$input->roomid) : null;
            if (!scope::inside($course, (int)$period->categoryid) || ($room && !scope::inside($course, (int)$room->categoryid))) {
                scope::fail();
            }
            $start = (int)$input->starts;
            $duration = (int)$input->duration;
            $end = $start + $duration * MINSECS;
            if ($start <= 0 || $duration < 1 || $duration > 1440) {
                scope::fail('invalidtime');
            }
            if ($start < $period->starts || $end > $period->ends) {
                scope::fail('outsideperiod');
            }
            $cmid = (int)$input->cmid;
            $cm = scope::module($course, $cmid);
            if ($cmid && !$cm) {
                scope::fail();
            }
            $group = (int)$input->groupid;
            $groups = scope::groups($course, $cm);
            if (
                ($group && !$DB->record_exists('groups', ['id' => $group, 'courseid' => $course->id])) ||
                ($groups !== null && (!$group || !isset($groups[$group])))
            ) {
                scope::fail();
            }
            if (
                $group && $cm && $cm->groupingid &&
                !$DB->record_exists('groupings_groups', ['groupingid' => $cm->groupingid, 'groupid' => $group])
            ) {
                scope::fail('invalidinput');
            }
            if (
                $old && ($old->groupid != $group || $old->cmid != $cmid || $old->regmode !== $input->regmode) &&
                ($DB->record_exists(store::SEAT, ['examid' => $old->id]) ||
                    $DB->record_exists(store::MARK, ['examid' => $old->id]) ||
                    $DB->record_exists(store::INCIDENT, ['examid' => $old->id]))
            ) {
                scope::fail('participantslocked');
            }
            $teacher = (int)$input->teacherid;
            $supervisor = (int)$input->supervisor;
            if (
                $teacher && (!is_enrolled($context, $teacher, 'moodle/grade:viewall', true) ||
                !$DB->record_exists('user', ['id' => $teacher, 'deleted' => 0, 'suspended' => 0]))
            ) {
                scope::fail('invalidstaff');
            }
            if ($supervisor) {
                $visiblemodule = scope::module($course, $cmid, $supervisor);
                $supervisorgroups = scope::groups($course, $visiblemodule, $supervisor);
                if (
                    !scope::course($course, 'supervise', $supervisor) || ($cmid && !$visiblemodule) ||
                    ($supervisorgroups !== null && (!$group || !isset($supervisorgroups[$group]))) ||
                    !$DB->record_exists('user', ['id' => $supervisor, 'deleted' => 0, 'suspended' => 0])
                ) {
                    scope::fail('invalidstaff');
                }
            }
            if ($published && (!$teacher || !$cm || (!$room && $input->mode !== 'online'))) {
                scope::fail('publicationblocked');
            }
            $record = (object)[
                'courseid' => $course->id, 'periodid' => $period->id, 'roomid' => $room->id ?? 0,
                'name' => $name, 'groupid' => $group, 'cmid' => $cmid, 'teacherid' => $teacher, 'supervisor' => $supervisor,
                'mode' => $input->mode, 'regmode' => $input->regmode, 'status' => $published ? 'scheduled' : 'draft',
                'starts' => $start, 'ends' => $end, 'instruction' => $instruction,
                'revision' => ((int)($old->revision ?? 0)) + 1, 'updated' => time(), 'requestkey' => $key,
            ];
            if ($old) {
                $record->id = $old->id;
            }
            if ($published) {
                self::reservations($record);
                if ($room && roster::count($record, $room->capacity) > $room->capacity) {
                    scope::fail('capacityexceeded');
                }
            }
            if ($old) {
                $DB->update_record(store::EXAM, $record);
            } else {
                $record->id = $DB->insert_record(store::EXAM, $record);
            }
            $action = $published ? (($old && $old->status === 'scheduled') ? 'rescheduled' : 'published') :
                ($old ? 'edited' : 'created');
            store::audit((int)$record->id, $action, $reason, 0, self::snapshot($old), self::snapshot($record));
            if ($published) {
                notifications::enqueue($record, $action === 'published' ? 'published' : 'changed');
            }
            return (int)$record->id;
        });
    }

    /**
     * Cancel the current revision and free its resources.
     *
     * @param int $id Convocation ID
     * @param int $revision Expected revision
     * @param string $reason Cancellation reason
     */
    public static function cancel(int $id, int $revision, string $reason): void {
        global $DB;
        store::write(static function () use ($DB, $id, $revision, $reason): void {
            $record = store::get(store::EXAM, $id);
            if (!scope::exam($record, get_course($record->courseid), 'edit')) {
                scope::fail();
            }
            $reason = trim($reason);
            if ($record->revision != $revision || $record->status === 'cancelled') {
                scope::fail('stale');
            }
            if ($reason === '' || \core_text::strlen($reason) > 1000) {
                scope::fail('invalidinput');
            }
            $previous = self::snapshot($record);
            $notify = $record->status === 'scheduled';
            $record->revision++;
            $record->status = 'cancelled';
            $record->updated = time();
            $DB->update_record(store::EXAM, $record);
            store::audit($id, 'cancelled', $reason, 0, $previous, self::snapshot($record));
            if ($notify) {
                notifications::enqueue($record, 'cancelled');
            }
        });
    }

    /**
     * Reject overlapping published reservations without disclosing unrelated exam details.
     *
     * @param stdClass $exam Proposed convocation
     */
    private static function reservations(stdClass $exam): void {
        global $DB;
        $operators = array_values(array_unique(array_filter([$exam->teacherid, $exam->supervisor])));
        [$staff, $p1] = $DB->get_in_or_equal($operators, SQL_PARAMS_NAMED, 'owner');
        [$supervisors, $p2] = $DB->get_in_or_equal($operators, SQL_PARAMS_NAMED, 'supervisor');
        $rule = "teacherid $staff OR supervisor $supervisors
                    OR (courseid = :course AND (groupid = :grp OR groupid = 0 OR :allgrp = 0))";
        $params = [
            'course' => $exam->courseid, 'grp' => $exam->groupid, 'allgrp' => $exam->groupid,
            'status' => 'scheduled', 'begins' => $exam->starts, 'ends' => $exam->ends, 'examid' => $exam->id ?? 0,
        ] + $p1 + $p2;
        if ($exam->roomid) {
            $rule .= ' OR roomid = :room';
            $params['room'] = $exam->roomid;
        }
        if (
            $DB->record_exists_select(
                store::EXAM,
                "id <> :examid AND status = :status AND starts < :ends AND ends > :begins AND ($rule)",
                $params
            )
        ) {
            scope::fail('overlap');
        }
    }

    /**
     * Keep structural audit values without copying instructions or identities into JSON.
     *
     * @param stdClass|null $exam Convocation
     * @return array|null
     */
    private static function snapshot(?stdClass $exam): ?array {
        return $exam ? array_intersect_key((array)$exam, array_flip([
            'name', 'periodid', 'roomid', 'groupid', 'cmid', 'starts', 'ends', 'mode', 'status', 'regmode', 'revision',
        ])) : null;
    }
}
