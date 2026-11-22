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
 * Bounded, internal Moodle observations. No HTTP probing or system-resource inspection.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class collector {
    /** Stable names shared with incidents and optional scrapes. */
    public const CHECKS = ['readiness', 'latency', 'cron', 'scheduled', 'adhoc', 'failures', 'components'];

    /**
     * Observe Moodle and the database with one indexed read and monotonic timing.
     */
    public static function readiness(): array {
        global $CFG, $DB;
        if (!empty($CFG->maintenance_enabled)) {
            return ['state' => 'maintenance', 'latencyus' => -1];
        }
        $start = hrtime(true);
        try {
            $site = $DB->get_field('course', 'id', ['id' => SITEID]);
            $state = $site ? 'up' : 'down';
        } catch (\dml_exception) {
            $state = 'unknown';
        }
        return ['state' => $state, 'latencyus' => $state === 'unknown' ? -1 : (int)min(
            2147483647,
            round((hrtime(true) - $start) / 1000)
        )];
    }

    /**
     * Read the latest native cron start without invoking cron or any task.
     */
    public static function cron(int $now, array $policy): array {
        $last = (int)get_config('tool_task', 'lastcronstart');
        $enabled = get_config('core', 'cron_enabled');
        $age = $last > 0 && $last <= $now ? $now - $last : null;
        $state = $enabled !== false && !(bool)$enabled ? 'error' :
            ($age === null ? 'unknown' : ($age > $policy['cronminutes'] * MINSECS ? 'warning' : 'ok'));
        return ['last' => $last, 'age' => $age, 'state' => $state];
    }

    /**
     * Gather checks once for this interval. Historical settings never change past observations.
     */
    public static function capture(int $now, array $policy, ?array $previous = null): array {
        $probe = self::readiness();
        $cron = self::cron($now, $policy);
        $queue = self::tasks($now, $policy['dueminutes'] * MINSECS);
        $failures = self::failed_count($now);
        $components = $policy['monitorcomponents'] ? self::components() : [];
        $usage = $previous['usage'] ?? [];
        if (!$usage || ($usage['captured'] ?? 0) < $now - DAYSECS) {
            $usage = self::usage($now);
        }
        $degraded = array_filter($components, static fn($item) => in_array(
            $item['status'],
            ['missing', 'upgrade', 'downgrade', 'disabled'],
            true
        ));
        $checks = [
            'readiness' => match ($probe['state']) {
                'up' => 'ok',
                'down' => 'error',
                'maintenance' => 'maintenance',
                default => 'unknown',
            },
            'latency' => $probe['latencyus'] < 0 ? 'unknown' :
                ($probe['latencyus'] >= $policy['latencyms'] * 1000 ? 'warning' : 'ok'),
            'cron' => $cron['state'],
            'scheduled' => $queue['failed'] ? 'error' : ($queue['due'] ? 'warning' : 'ok'),
            'adhoc' => $queue['adhocfailed'] ? 'error' : ($queue['adhocdue'] >= $policy['queuewarn'] ? 'warning' : 'ok'),
            'failures' => $failures === null ? 'unknown' : ($failures > 0 ? 'warning' : 'ok'),
            'components' => !$components ? 'unknown' : ($degraded ? 'warning' : 'ok'),
        ];
        if ($probe['state'] === 'maintenance') {
            $checks = array_fill_keys(self::CHECKS, 'maintenance');
        }
        return [
            'readiness' => $probe['state'], 'latencyus' => $probe['latencyus'], 'checks' => $checks,
            'cron' => $cron, 'queue' => $queue, 'recentfailures' => $failures, 'components' => $components, 'usage' => $usage,
        ];
    }

    /**
     * Evaluate configured schedules through the Task API, with an indexed capped adhoc queue read.
     */
    public static function tasks(int $now, int $grace): array {
        global $DB;
        $result = ['due' => 0, 'failed' => 0, 'running' => 0, 'enabled' => 0, 'disabled' => 0];
        foreach (\core\task\manager::get_all_scheduled_tasks() as $task) {
            // Core does not execute tasks of disabled components unless the task explicitly opts in.
            if ($task->get_disabled() || (!$task->is_component_enabled() && !$task->get_run_if_component_disabled())) {
                $result['disabled']++;
                continue;
            }
            $result['enabled']++;
            if ((int)$task->get_fail_delay() > 0) {
                $result['failed']++;
            }
            if ($task->get_timestarted()) {
                $result['running']++;
            } else if ($task->get_next_run_time() > 0 && $task->get_next_run_time() <= $now - $grace) {
                $result['due']++;
            }
        }
        $waiting = $DB->get_records_select(
            'task_adhoc',
            'nextruntime <= :due AND (timestarted IS NULL OR timestarted = 0)',
            ['due' => $now - $grace],
            'nextruntime ASC',
            'id',
            0,
            util::CAPCOUNT + 1
        );
        $failed = $DB->get_records_select('task_adhoc', 'faildelay > 0', [], 'id ASC', 'id', 0, util::CAPCOUNT + 1);
        return $result + [
            'adhocdue' => min(util::CAPCOUNT, count($waiting)),
            'adhocfailed' => min(util::CAPCOUNT, count($failed)),
            'adhocduecapped' => count($waiting) > util::CAPCOUNT,
            'adhocfailedcapped' => count($failed) > util::CAPCOUNT,
        ];
    }

    /**
     * The task log schema applies only to the exact default database logger.
     */
    public static function standard_task_log(): bool {
        return \core\task\logmanager::get_logger_classname() === \core\task\database_logger::class;
    }

    /**
     * Count bounded, recent failures while keeping the log payload out of plugin storage.
     */
    public static function failed_count(int $now): ?int {
        global $DB;
        if (!self::standard_task_log()) {
            return null;
        }
        $rows = $DB->get_records_select(
            'task_log',
            'result = 1 AND timestart >= :cutoff AND timestart <= :now',
            ['cutoff' => $now - 15 * MINSECS, 'now' => $now],
            'timestart DESC',
            'id',
            0,
            util::CAPCOUNT
        );
        return count($rows);
    }

    /**
     * Fixed site components only; report lifecycle presence instead of invoking their services.
     */
    public static function components(): array {
        $manager = \core_plugin_manager::instance();
        $rows = [];
        $components = ['tool_task', 'report_status', 'logstore_standard', 'auth_manual', 'enrol_manual', 'cachestore_file'];
        foreach ($components as $component) {
            $item = $manager->get_plugin_info($component);
            $status = $item ? $item->get_status() : 'missing';
            if ($item && in_array($status, ['uptodate', 'nodb'], true) && $item->is_enabled() === false) {
                $status = 'disabled';
            }
            $rows[$component] = ['status' => $status];
        }
        return $rows;
    }

    /**
     * Recompute site-level use at most daily, without identifiers or activity time estimates.
     */
    private static function usage(int $now): array {
        global $DB;
        return [
            'captured' => $now,
            'courses' => $DB->count_records_select('course', 'id <> :site', ['site' => SITEID]),
            'activeaccounts' => $DB->count_records_select(
                'user',
                'lastaccess >= :since AND lastaccess <= :now AND deleted = 0 AND suspended = 0' .
                    ' AND confirmed = 1 AND username <> :guest',
                ['since' => $now - DAYSECS, 'now' => $now, 'guest' => 'guest']
            ),
        ];
    }
}
