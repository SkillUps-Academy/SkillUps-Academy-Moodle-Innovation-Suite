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

namespace report_skillupssecurity\control;

use report_skillupssecurity\access;

/**
 * Evaluate Moodle account inactivity independently of privilege assignment.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class accounts implements rule {
    /**
     * Return a clamped inactivity threshold.
     *
     * @return int
     */
    public static function days(): int {
        return max(7, min(730, (int)(get_config(access::C, 'inactivedays') ?: 90)));
    }

    /**
     * Bound indexed collection and clearly label incomplete results.
     *
     * @param int $now Scan timestamp
     * @return array
     */
    public function evaluate(int $now): array {
        global $DB;
        $cutoff = $now - self::days() * DAYSECS;
        $limit = max(100, min(5000, (int)(get_config(access::C, 'accountlimit') ?: 1000)));
        $records = $DB->get_records_select(
            'user',
            'deleted = 0 AND suspended = 0 AND confirmed = 1 AND username <> :guest AND auth <> :nologin
             AND ((lastaccess > 0 AND lastaccess <= :lastaccess)
               OR (lastaccess = 0 AND timecreated > 0 AND timecreated <= :created))',
            ['guest' => 'guest', 'nologin' => 'nologin', 'lastaccess' => $cutoff, 'created' => $cutoff],
            'id ASC',
            'id,lastaccess,timecreated',
            0,
            $limit + 1
        );
        $complete = count($records) <= $limit;
        $findings = [];
        foreach (array_slice($records, 0, $limit) as $person) {
            $findings[] = observation::make('inactive', 'confirmed', 'low', $person->id, 0, [
                'lastaccess' => (int)$person->lastaccess,
                'timecreated' => (int)$person->timecreated,
                'threshold' => self::days(),
            ]);
        }
        return ['findings' => $findings, 'coverage' => ['inactive' => $complete], 'inventory' => []];
    }
}
