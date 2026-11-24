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
 * Calculate attendance from the authorized set of finalized sessions.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {
    /**
     * Produce the attendance metrics for one activity or visible activities in its course.
     *
     * @param activity $selected Current activity and policy
     * @param array $filters Validated scope
     * @param int $userid Restrict to the student for self service
     * @param bool $automatic Allow the internal alerts engine to evaluate its own module
     * @return array
     */
    public static function overview(activity $selected, array $filters = [], int $userid = 0, bool $automatic = false): array {
        global $DB, $USER;
        $modules = [$selected];
        if (!empty($filters['coursewide']) && !$userid && !$automatic) {
            $modules = [];
            foreach (get_fast_modinfo($selected->course)->get_instances_of('skillupsattendance') as $cm) {
                if ($cm->uservisible) {
                    $module = activity::by_instance((int)$cm->instance);
                    if ($module->can('view') && $module->can('report')) {
                        $modules[] = $module;
                    }
                }
            }
        }
        $staff = !$userid && !$automatic;
        if ($staff) {
            $selected->require('report');
        } else if (!$automatic && $userid !== (int)$USER->id) {
            activity::fail('unavailable');
        }
        $scopes = [];
        $params = ['final' => 'final', 'pending' => 'pending'];
        foreach ($modules as $idx => $module) {
            $scope = "s.attendanceid = :activity$idx";
            $binding = ["activity$idx" => $module->record->id];
            if ($staff && !$module->staff_group(0)) {
                $allowed = array_keys($module->groups());
                if (!$allowed) {
                    continue;
                }
                [$insql, $ids] = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, "group{$idx}_");
                $scope .= " AND s.groupid $insql";
                $binding += $ids;
            }
            $scopes[] = "($scope)";
            $params += $binding;
        }
        if (!$scopes) {
            return [];
        }
        $conditions = ['(' . implode(' OR ', $scopes) . ')', 's.state = :final', 'm.state <> :pending', 'u.deleted = 0'];
        if ($userid) {
            $conditions[] = 'm.userid = :student';
            $params['student'] = $userid;
        }
        foreach (['groupid', 'teacherid', 'modality'] as $field) {
            if (!empty($filters[$field])) {
                $conditions[] = "s.$field = :$field";
                $params[$field] = $filters[$field];
            }
        }
        foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
            if (!empty($filters[$key])) {
                $conditions[] = "s.starts $operator :$key";
                $params[$key] = $filters[$key];
            }
        }
        $where = implode(' AND ', $conditions);
        $marks = store::MARK;
        $sessions = store::SESSION;
        $rows = $DB->get_recordset_sql(
            "SELECT m.id, m.userid, m.state, u.firstname, u.lastname
               FROM {{$marks}} m JOIN {{$sessions}} s ON m.sessionid = s.id JOIN {user} u ON u.id = m.userid
              WHERE $where ORDER BY m.userid ASC, s.starts ASC, s.id ASC",
            $params,
        );
        $totals = [];
        try {
            foreach ($rows as $record) {
                $id = $record->userid;
                $totals[$id] ??= [
                    'userid' => $id, 'name' => $record->firstname . ' ' . $record->lastname,
                    'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'streak' => 0,
                ];
                $totals[$id][$record->state]++;
                $totals[$id]['streak'] = $record->state === 'absent' ? $totals[$id]['streak'] + 1 : 0;
            }
        } finally {
            $rows->close();
        }
        foreach ($totals as &$row) {
            $attended = $row['present'] + $row['late'];
            $count = $attended + $row['absent'];
            $row['percent'] = $count ? round($attended * 100 / $count, 2) : null;
            $row['flags'] = self::risks($row, $selected->record);
        }
        unset($row);
        return $totals;
    }

    /**
     * Apply the selected activity thresholds to a calculated student summary.
     *
     * @param array $row Counts and percentage
     * @param stdClass $policy Activity rules
     * @return array
     */
    public static function risks(array $row, stdClass $policy): array {
        $flags = [];
        if ($row['percent'] !== null && $row['percent'] < $policy->minimum) {
            $flags[] = 'minimum';
        }
        if ($policy->consecutive && $row['streak'] >= $policy->consecutive) {
            $flags[] = 'consecutive';
        }
        if ($policy->absences && $row['absent'] > $policy->absences) {
            $flags[] = 'absences';
        }
        return $flags;
    }

    /**
     * Neutralize spreadsheet formula prefixes on externally supplied display values.
     *
     * @param string $value Raw cell
     * @return string
     */
    public static function safe_cell(string $value): string {
        return preg_match('/^\s*[=+\-@]|^[\t\r\n]/u', $value) ? "'" . $value : $value;
    }

    /**
     * Parse a calendar-day filter in the user's Moodle time zone.
     *
     * @param string $day Date string
     * @param bool $end Add the following midnight for an exclusive range end
     * @return int
     */
    public static function day(string $day, bool $end = false): int {
        if ($day === '') {
            return 0;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day, \core_date::get_user_timezone_object());
        if (!$date || $date->format('Y-m-d') !== $day) {
            activity::fail('badschedule');
        }
        return ($end ? $date->modify('+1 day') : $date)->getTimestamp();
    }
}
