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
 * Safe Moodle configuration observations and authorized operational error metadata.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class diagnostics {
    /**
     * Read only enumerated settings and emit booleans without configuration contents.
     */
    public static function configuration(): array {
        global $CFG;
        $logger = collector::standard_task_log();
        return [
            'https' => is_https(),
            'croncli' => !empty($CFG->cronclionly),
            'cronconfigured' => get_config('core', 'cron_enabled') === false || (bool)get_config('core', 'cron_enabled'),
            'maintenancemode' => !empty($CFG->maintenance_enabled),
            'tasklogger' => $logger,
            'logreader' => self::reader() !== null,
            'debughidden' => empty($CFG->debugdisplay),
            'scrapeconfigured' => (bool)get_config(util::C, 'scrapeenabled') && gateway::configured((string)getenv(gateway::ENV)),
        ];
    }

    /**
     * Render bounded native task log metadata only with native administration permission.
     */
    public static function task_errors(): ?array {
        global $DB;
        require_capability('moodle/site:config', \context_system::instance());
        if (!collector::standard_task_log()) {
            return null;
        }
        return $DB->get_records_select(
            'task_log',
            'result = 1 AND timestart >= :cutoff',
            ['cutoff' => time() - DAYSECS],
            'timestart DESC, id DESC',
            'id,classname,type,timestart,timeend',
            0,
            50
        );
    }

    /**
     * Show up to 50 native overdue or retrying schedules without task arguments.
     */
    public static function schedule_rows(): array {
        require_capability('moodle/site:config', \context_system::instance());
        $rows = [];
        $cutoff = time() - util::policy()['dueminutes'] * MINSECS;
        foreach (\core\task\manager::get_all_scheduled_tasks() as $task) {
            if ($task->get_disabled() || (!$task->is_component_enabled() && !$task->get_run_if_component_disabled())) {
                continue;
            }
            $next = (int)$task->get_next_run_time();
            $retry = (int)$task->get_fail_delay() > 0;
            if (!$retry && ($task->get_timestarted() || $next <= 0 || $next > $cutoff)) {
                continue;
            }
            $rows[] = ['classname' => get_class($task), 'when' => $next, 'label' => $retry ? 'retrying' : 'overdue'];
            if (count($rows) >= 50) {
                break;
            }
        }
        return $rows;
    }

    /**
     * Retrieve selected error events using the native reader and discard all payload and identities.
     */
    public static function events(): ?array {
        global $DB;
        require_capability('report/log:view', \context_system::instance());
        $reader = self::reader();
        if ($reader === null) {
            return null;
        }
        $events = [
            '\core\event\email_failed' => 'emailfailed',
            '\core\event\antivirus_scan_file_error' => 'scanfileerror',
            '\core\event\antivirus_scan_data_error' => 'scandataerror',
        ];
        [$sql, $params] = $DB->get_in_or_equal(array_keys($events), SQL_PARAMS_NAMED, 'evt');
        $found = $reader->get_events_select(
            "eventname $sql AND anonymous = 0 AND timecreated >= :since",
            $params + ['since' => time() - DAYSECS],
            'timecreated DESC, id DESC',
            0,
            50
        );
        $rows = [];
        foreach ($found as $event) {
            $data = $event->get_data();
            if (isset($events[$data['eventname']])) {
                $rows[] = ['type' => $events[$data['eventname']], 'when' => (int)$data['timecreated']];
            }
        }
        return $rows;
    }

    /**
     * Only the standard authorized SQL log reader has the declared behavior.
     */
    private static function reader(): ?\core\log\sql_internal_table_reader {
        $readers = get_log_manager()->get_readers(\core\log\sql_internal_table_reader::class);
        return $readers['logstore_standard'] ?? null;
    }
}
