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
 * Validate reservations and identify simultaneous use of academic resources.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conflicts {
    /**
     * Evaluate teacher, period, block and capacity rules.
     *
     * @param \stdClass $slot Session data
     * @param array $overrides Proposed catalog values
     * @return array
     */
    public static function constraints(\stdClass $slot, array $overrides = []): array {
        global $DB;
        $site = store::get(store::SITE, (int)$slot->siteid);
        $cycle = $overrides['cycle'] ?? store::get(store::CYCLE, (int)$slot->cycleid);
        $zone = new \DateTimeZone($site->timezone);
        $from = (new \DateTimeImmutable('@' . $slot->starts))->setTimezone($zone);
        $to = (new \DateTimeImmutable('@' . $slot->ends))->setTimezone($zone);
        $issues = [];
        $course = $DB->get_record('course', ['id' => $slot->courseid]);
        if (
            !$course || !scope::course_in_site($site, $course) ||
            ($slot->groupid && !$DB->record_exists('groups', ['id' => $slot->groupid, 'courseid' => $slot->courseid]))
        ) {
            $issues[] = 'invalidaudience';
        }
        if (!$course || !scope::teacher((int)$slot->courseid, (int)$slot->teacherid)) {
            $issues[] = 'missingteacher';
        }
        if ($from->format('Y-m-d') < $cycle->startday || $to->format('Y-m-d') > $cycle->endday) {
            $issues[] = 'outsidecycle';
        }
        if ($slot->blockid) {
            $block = $overrides['block'] ?? store::get(store::BLOCK, (int)$slot->blockid);
            if (
                (int)$from->format('N') !== (int)$block->weekday || $from->format('Y-m-d') !== $to->format('Y-m-d') ||
                catalog::minute($from->format('H:i')) < $block->beginmin ||
                catalog::minute($to->format('H:i')) > $block->endmin
            ) {
                $issues[] = 'outsideblock';
            }
        }
        if (($slot->mode !== 'online' && !$slot->roomid) || ($slot->mode !== 'onsite' && !$slot->virtualid)) {
            $issues[] = 'missingroom';
        }
        if ($course && !in_array('invalidaudience', $issues, true) && ($slot->roomid || $slot->virtualid)) {
            [$enrolled, $params] = get_enrolled_sql(
                \context_course::instance($course->id),
                'moodle/course:isincompletionreports',
                (int)$slot->groupid,
                true,
            );
            $count = $DB->count_records_sql(
                "SELECT COUNT(1) FROM ($enrolled) e JOIN {user} u ON u.id = e.id WHERE u.deleted = 0 AND u.suspended = 0",
                $params,
            );
            foreach (array_filter([$slot->roomid, $slot->virtualid]) as $id) {
                $room = isset($overrides['room']) && $overrides['room']->id == $id ?
                    $overrides['room'] : store::get(store::ROOM, (int)$id);
                if ($room->capacity && $count > $room->capacity) {
                    $issues[] = 'overcapacity';
                    break;
                }
            }
        }
        return $issues;
    }

    /**
     * Find overlaps without exposing the reservation owners.
     *
     * @param \stdClass $slot Session data
     * @param bool $planning Include draft reservations
     * @return array
     */
    public static function detect(\stdClass $slot, bool $planning = false): array {
        global $DB;
        $issues = self::constraints($slot);
        $sql = 'starts < :end AND ends > :start AND id <> :id AND ';
        $sql .= $planning ? 'status <> :state' : 'status = :state';
        $params = [
            'start' => $slot->starts, 'end' => $slot->ends, 'id' => $slot->id ?? 0,
            'state' => $planning ? 'cancelled' : 'published',
        ];
        $criteria = ['courseid = :course'];
        $params['course'] = $slot->courseid;
        foreach (['teacherid' => 'teacherbusy', 'roomid' => 'roombusy', 'virtualid' => 'virtualbusy'] as $field => $issue) {
            if ($slot->{$field}) {
                $criteria[] = "$field = :$field";
                $params[$field] = $slot->{$field};
            }
        }
        $sql .= ' AND (' . implode(' OR ', $criteria) . ')';
        $matches = $DB->get_recordset_select(store::SLOT, $sql, $params);
        try {
            foreach ($matches as $other) {
                foreach (['teacherid' => 'teacherbusy', 'roomid' => 'roombusy', 'virtualid' => 'virtualbusy'] as $field => $issue) {
                    if ($slot->{$field} && $slot->{$field} == $other->{$field}) {
                        $issues[] = $issue;
                    }
                }
                if (
                    $slot->courseid == $other->courseid &&
                    (!$slot->groupid || !$other->groupid || $slot->groupid == $other->groupid)
                ) {
                    $issues[] = 'groupbusy';
                }
            }
        } finally {
            $matches->close();
        }
        // Report conflict types only. A different campus may own the conflicting reservation.
        return array_values(array_unique($issues));
    }
}
