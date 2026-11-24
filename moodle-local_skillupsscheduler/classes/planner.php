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
 * Read an authorized calendar window and construct display data.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class planner {
    /**
     * Stream sessions visible in the requested calendar interval.
     *
     * @param int $start Start timestamp
     * @param int $end Exclusive end timestamp
     * @param array $filters Calendar filters
     * @param bool $issues Only return conflicting sessions
     * @return \Generator
     */
    public static function slots(int $start, int $end, array $filters, bool $issues = false): \Generator {
        global $DB;
        $conditions = ['starts < :until', 'ends > :since'];
        $params = ['since' => $start, 'until' => $end];
        foreach (['siteid', 'cycleid', 'courseid', 'groupid', 'teacherid', 'roomid'] as $field) {
            if (!empty($filters[$field])) {
                if ($field === 'roomid') {
                    $conditions[] = '(roomid = :roomid OR virtualid = :virtualid)';
                    $params['virtualid'] = $filters[$field];
                } else if ($field === 'cycleid') {
                    $conditions[] = '(cycleid = :cycleid OR EXISTS (SELECT 1 FROM {' . store::CYCLE . '} cyc
                        WHERE cyc.id = cycleid AND cyc.parentid = :parent))';
                    $params['parent'] = $filters[$field];
                } else {
                    $conditions[] = "$field = :$field";
                }
                $params[$field] = $filters[$field];
            }
        }
        $rows = $DB->get_recordset_select(store::SLOT, implode(' AND ', $conditions), $params, 'starts ASC, id ASC');
        try {
            foreach ($rows as $slot) {
                if (!scope::visible($slot)) {
                    continue;
                }
                if ($issues) {
                    $site = store::get(store::SITE, (int)$slot->siteid);
                    if ($slot->status === 'cancelled' || !scope::staff($site, 'plan')) {
                        continue;
                    }
                    $slot->conflicts = conflicts::detect($slot, $slot->status === 'draft');
                    if (!$slot->conflicts) {
                        continue;
                    }
                }
                yield $slot;
            }
        } finally {
            $rows->close();
        }
    }

    /**
     * Find courses in the authorized campus that have no active teacher.
     *
     * @param int $siteid Campus filter, zero for all authorized campuses
     * @param int $courseid Optional Moodle course filter
     * @return array First results for the conflict panel
     */
    public static function courses_without_teachers(int $siteid, int $courseid): array {
        global $DB;
        $sites = $DB->get_records(store::SITE, $siteid ? ['id' => $siteid] : [], 'id');
        $found = [];
        foreach ($sites as $site) {
            if (!scope::staff($site, 'plan')) {
                continue;
            }
            $category = \core_course_category::get($site->categoryid, IGNORE_MISSING, true);
            if (!$category) {
                continue;
            }
            foreach ($category->get_courses(['recursive' => true, 'sort' => ['fullname' => SORT_ASC]]) as $course) {
                if ($courseid && $courseid != $course->id) {
                    continue;
                }
                [$enrolled, $params] = get_enrolled_sql(\context_course::instance($course->id), scope::CAP . 'teach', 0, true);
                $sql = "SELECT 1 FROM ($enrolled) e JOIN {user} u ON u.id = e.id WHERE u.deleted = 0 AND u.suspended = 0";
                if (!$DB->record_exists_sql($sql, $params)) {
                    $found[] = [
                        'course' => format_string($course->fullname), 'campus' => format_string($site->name),
                        'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    ];
                    if (count($found) >= 25) {
                        return $found;
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Resolve the display name of an authorized resource.
     *
     * @param string $table Table
     * @param int $id Record ID
     * @param string $field Database field
     * @return string
     */
    public static function label(string $table, int $id, string $field = 'name'): string {
        global $DB;
        $value = $id ? $DB->get_field($table, $field, ['id' => $id]) : false;
        return $value === false ? '' : format_string($value);
    }

    /**
     * Card.
     *
     * @param \stdClass $slot Session data
     * @return array
     */
    public static function card(\stdClass $slot): array {
        $alerts = array_map(static fn($key): string => scope::text($key), $slot->conflicts ?? []);
        return [
            'title' => $slot->title,
            'url' => scope::url('session', ['id' => $slot->id])->out(false),
            'campus' => self::label(store::SITE, (int)$slot->siteid),
            'course' => self::label('course', (int)$slot->courseid, 'fullname'),
            'hours' => userdate($slot->starts, '%H:%M') . ' – ' . userdate($slot->ends, '%H:%M'),
            'status' => scope::text($slot->status),
            'pending' => $slot->status !== 'published',
            'issue' => implode(' · ', $alerts),
        ];
    }

    /**
     * Options.
     *
     * @param int $start Start timestamp
     * @param int $end Exclusive end timestamp
     * @return array
     */
    public static function options(int $start, int $end): array {
        global $DB;
        $choices = ['siteid' => [], 'cycleid' => [], 'courseid' => [], 'groupid' => [], 'teacherid' => [], 'roomid' => []];
        foreach ($DB->get_records(store::SITE, [], 'name') as $site) {
            if (scope::staff($site, 'plan') || scope::staff($site, 'consult')) {
                $choices['siteid'][$site->id] = format_string($site->name);
            }
        }
        $tables = [
            'siteid' => store::SITE, 'cycleid' => store::CYCLE, 'courseid' => 'course',
            'groupid' => 'groups', 'teacherid' => 'user', 'roomid' => store::ROOM, 'virtualid' => store::ROOM,
        ];
        foreach (self::slots($start, $end, []) as $slot) {
            foreach ($tables as $field => $table) {
                $id = $slot->{$field};
                $key = $field === 'virtualid' ? 'roomid' : $field;
                if (!$id || isset($choices[$key][$id])) {
                    continue;
                }
                if ($field === 'teacherid') {
                    $user = \core_user::get_user($id, '*');
                    $choices[$key][$id] = $user && !$user->deleted ? fullname($user) : scope::text('unknown');
                } else {
                    $choices[$key][$id] = self::label($table, (int)$id, $field === 'courseid' ? 'fullname' : 'name');
                    if ($field === 'cycleid') {
                        $parentid = (int)$DB->get_field(store::CYCLE, 'parentid', ['id' => $id]);
                        if ($parentid && !isset($choices[$key][$parentid])) {
                            $choices[$key][$parentid] = self::label(store::CYCLE, $parentid);
                        }
                    }
                }
            }
        }
        foreach ($choices as &$values) {
            asort($values, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($values);
        return $choices;
    }
}
