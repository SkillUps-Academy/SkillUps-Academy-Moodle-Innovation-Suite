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
 * Stable, low-cardinality Prometheus exposition from cached samples and a native heartbeat.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class metrics {
    /**
     * Render supported gauges. Unknown numeric values are encoded as NaN.
     */
    public static function render(?\stdClass $row, int $now, array $policy, array $cron): string {
        $data = $row ? store::payload($row) : [];
        $age = $row ? max(0, $now - $row->captured) : null;
        $fresh = ($age !== null && $age <= $policy['stale']) ? 1 : 0;
        $gauges = [
            'sample_timestamp_seconds' => ['UTC timestamp of the latest internal sample.', $row->captured ?? null],
            'sample_age_seconds' => ['Age of the latest internal sample.', $age],
            'sample_fresh' => ['One if an internal sample has arrived within the configured threshold.', $fresh],
            'cron_last_start_timestamp_seconds' => ['Last native Moodle cron start.', $cron['last'] ?: null],
            'cron_age_seconds' => ['Age of the last native Moodle cron start.', $cron['age']],
            'cron_healthy' => ['One if native cron is recent and enabled.', match ($cron['state']) {
                'ok' => 1, 'warning', 'error' => 0, default => null,
            }],
            'readiness' => ['Internal Moodle database probe result, excluding maintenance.', match ($row->readiness ?? 'unknown') {
                'up' => 1, 'down' => 0, default => null,
            }],
            'maintenance' => ['One if the latest internal sample observed maintenance mode.',
                $row ? (int)($row->readiness === 'maintenance') : null],
            'probe_duration_seconds' => ['Duration of the last internal indexed database check.',
                $row && $row->latencyus >= 0 ? $row->latencyus / 1000000.0 : null],
            'scheduled_overdue' => ['Enabled scheduled tasks overdue after grace.', $data['queue']['due'] ?? null],
            'scheduled_retrying' => ['Scheduled tasks with retry backoff.', $data['queue']['failed'] ?? null],
            'adhoc_overdue' => ['Due non-running adhoc tasks, capped at 1000.', $data['queue']['adhocdue'] ?? null],
            'adhoc_retrying' => ['Adhoc tasks with retry backoff, capped at 1000.', $data['queue']['adhocfailed'] ?? null],
            'task_failures_recent' => ['Recorded task failures in the recent 15 minute window, capped at 1000.',
                $data['recentfailures'] ?? null],
        ];
        $body = '';
        foreach ($gauges as $key => [$help, $value]) {
            $name = 'skillups_moodle_' . $key;
            $encoded = $value === null ? 'NaN' : (is_float($value) ? sprintf('%.6F', $value) : (string)$value);
            $body .= "# HELP $name $help\n# TYPE $name gauge\n$name $encoded\n";
        }
        return $body;
    }
}
