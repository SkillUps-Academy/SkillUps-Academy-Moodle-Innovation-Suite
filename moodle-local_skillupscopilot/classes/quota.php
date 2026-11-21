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

namespace local_skillupscopilot;

/**
 * Daily usage reservations and aggregate reporting.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quota {
    /**
     * Resolve the start of the UTC accounting day.
     */
    public static function day_start(int $now): int {
        return intdiv($now, DAYSECS) * DAYSECS;
    }

    /**
     * Check all limits while the caller holds the quota lock.
     */
    public static function require_capacity(int $userid, int $courseid, int $now): void {
        global $DB;
        $table = 'local_skillupscopilot_meter';
        $from = self::day_start($now);
        $courselimit = $DB->get_field('local_skillupscopilot_course', 'requestlimit', ['courseid' => $courseid]);
        $scopes = [
            [(int)get_config('local_skillupscopilot', 'sitequota'), '', []],
            [(int)get_config('local_skillupscopilot', 'userquota'), ' AND userid = :uid', ['uid' => $userid]],
            [$courselimit === false ? (int)get_config('local_skillupscopilot', 'coursequota') : (int)$courselimit,
                ' AND courseid = :cid', ['cid' => $courseid]],
        ];
        foreach ($scopes as [$limit, $filter, $params]) {
            if (
                $limit > 0 && $DB->count_records_select(
                    $table,
                    'timecreated >= :start' . $filter,
                    ['start' => $from] + $params
                ) >= $limit
            ) {
                throw new \moodle_exception('quotaexceeded', 'local_skillupscopilot');
            }
        }
    }

    /**
     * Write one reservation under the same lock and in the turn's transaction.
     */
    public static function reserve(int $turnid, int $userid, int $courseid, string $provider, int $now): void {
        global $DB;
        $DB->insert_record('local_skillupscopilot_meter', (object)[
            'turnid' => $turnid, 'userid' => $userid, 'courseid' => $courseid, 'provider' => $provider,
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Report usage within a fixed period without exposing prompt or user data.
     */
    public static function totals(int $start, ?int $courseid = null, ?int $userid = null): array {
        global $DB;
        $where = 'timecreated >= :start';
        $params = ['start' => $start];
        foreach (['courseid' => $courseid, 'userid' => $userid] as $field => $value) {
            if ($value !== null) {
                $where .= " AND $field = :$field";
                $params[$field] = $value;
            }
        }
        $sql = "SELECT provider, COUNT(*) AS requests, SUM(inputtokens) AS inputtokens,
                       SUM(outputtokens) AS outputtokens, SUM(CASE WHEN status = :error THEN 1 ELSE 0 END) AS failures
                  FROM {local_skillupscopilot_meter}
                 WHERE $where
              GROUP BY provider";
        return $DB->get_records_sql($sql, $params + ['error' => 'failed']);
    }
}
