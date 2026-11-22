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

namespace report_skillupsmonitor;

/**
 * Compute observed availability and coverage over closed UTC slots.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class availability {
    /** Supported report horizons, in hours. */
    public const HOURS = [1, 24, 168, 720];

    /**
     * Fetch only lightweight observation columns in the requested interval.
     */
    public static function report(int $now, int $hours): array {
        global $DB;
        if (!in_array($hours, self::HOURS, true)) {
            throw new \moodle_exception('invalidwindow', util::C);
        }
        $until = store::slot($now);
        $from = $until - $hours * HOURSECS;
        $rows = $DB->get_recordset_select(
            store::SAMPLE,
            'slot >= :fromslot AND slot < :toslot',
            ['fromslot' => $from, 'toslot' => $until],
            'slot ASC',
            'slot,readiness,latencyus'
        );
        try {
            return self::summarize($from, $until, $rows);
        } finally {
            $rows->close();
        }
    }

    /**
     * Distinguish intentional maintenance, confirmed outcomes and all unknown intervals.
     */
    public static function summarize(int $from, int $until, iterable $rows): array {
        $expected = max(0, intdiv($until - $from, util::SLOT));
        $counts = ['up' => 0, 'down' => 0, 'maintenance' => 0];
        $latencies = [];
        foreach ($rows as $row) {
            if ($row->slot < $from || $row->slot >= $until || !isset($counts[$row->readiness])) {
                continue;
            }
            $counts[$row->readiness]++;
            if ($row->readiness === 'up' && $row->latencyus >= 0) {
                $latencies[] = (int)$row->latencyus;
            }
        }
        sort($latencies, SORT_NUMERIC);
        $length = count($latencies);
        $measured = array_sum($counts);
        $service = $counts['up'] + $counts['down'];
        return $counts + [
            'from' => $from, 'until' => $until, 'expected' => $expected, 'unknown' => max(0, $expected - $measured),
            'available' => $service ? 100 * $counts['up'] / $service : null,
            'coverage' => $expected ? 100 * $measured / $expected : null,
            'p95us' => $length ? $latencies[(int)ceil(0.95 * $length) - 1] : null,
        ];
    }
}
