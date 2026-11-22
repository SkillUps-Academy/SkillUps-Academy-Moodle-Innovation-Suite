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

namespace local_skillupsflow;

use local_skillupsflow\trigger\occurrence;

/**
 * Workflow revisions, guarded admission and durable scheduling.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /**
     * States eligible for an ad hoc job.
     */
    public const WORK = ['queued', 'sending', 'approval'];
    /**
     * All unfinished execution states.
     */
    public const OPEN = self::WORK;

    /**
     * Acquire the common workflow lock used by editors, admission, workers and reviewers.
     */
    public static function lock(int $id, int $wait = 5): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('local_skillupsflow')->get_lock('flow_' . $id, $wait);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_skillupsflow');
        }
        return $lock;
    }

    /**
     * Persist a new immutable revision. The active revision remains pinned.
     */
    public static function save(int $id, int $base, string $name, array $graph, int $userid): int {
        global $DB;
        $lock = self::lock($id);
        try {
            $tx = $DB->start_delegated_transaction();
            try {
                $now = time();
                if (!$id) {
                    $id = $DB->insert_record('local_skillupsflow_flow', (object)[
                        'name' => $name, 'triggertype' => 'manual', 'timemodified' => $now,
                    ]);
                    $revision = 1;
                } else {
                    $flow = $DB->get_record('local_skillupsflow_flow', ['id' => $id], '*', MUST_EXIST);
                    if ((int)$flow->latest !== $base) {
                        throw new \moodle_exception('editconflict', 'local_skillupsflow');
                    }
                    $revision = (int)$DB->get_field('local_skillupsflow_ver', 'revision', ['id' => $flow->latest]) + 1;
                }
                $version = $DB->insert_record('local_skillupsflow_ver', (object)[
                    'flowid' => $id, 'revision' => $revision, 'userid' => $userid,
                    'definition' => json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'timecreated' => $now,
                ]);
                $targets = array_unique([$graph['trigger']['userid'], ...array_column($graph['nodes'], 'userid')]);
                foreach (array_filter($targets) as $target) {
                    $DB->insert_record('local_skillupsflow_target', (object)['versionid' => $version, 'userid' => $target]);
                }
                $DB->update_record('local_skillupsflow_flow', (object)[
                    'id' => $id, 'latest' => $version, 'name' => $name, 'timemodified' => $now,
                ]);
                self::audit('saved', $id);
                $tx->allow_commit();
                return $id;
            } catch (\Throwable $exception) {
                $tx->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Publish one known revision, or stop accepting work and pause pending runs.
     */
    public static function activate(int $id, int $versionid): void {
        global $DB;
        $lock = self::lock($id);
        try {
            $flow = $DB->get_record('local_skillupsflow_flow', ['id' => $id], '*', MUST_EXIST);
            if ((int)$flow->active === $versionid) {
                return;
            }
            $type = 'manual';
            $due = 0;
            if ($versionid) {
                $version = $DB->get_record('local_skillupsflow_ver', ['flowid' => $id, 'id' => $versionid], '*', MUST_EXIST);
                if ($version->revoked) {
                    throw new \moodle_exception('actorunavailable', 'local_skillupsflow');
                }
                security::actor($version->userid);
                $trigger = definition::parse($version->definition)['trigger'];
                $type = $trigger['type'];
                if (in_array($type, ['once', 'interval'], true)) {
                    $due = $trigger['start'] ?: time();
                }
            }
            $DB->update_record('local_skillupsflow_flow', (object)[
                'id' => $id, 'active' => $versionid, 'triggertype' => $type, 'nextdue' => $due, 'timemodified' => time(),
            ]);
            self::audit($versionid ? 'activated' : 'paused', $id);
        } finally {
            $lock->release();
        }
    }

    /**
     * Admit one manual, timer or event occurrence against the active revision.
     */
    public static function admit(int $id, string $type, string $occurrence, int $userid = 0, int $courseid = 0): int {
        global $DB, $USER;
        $lock = self::lock($id);
        try {
            $flow = $DB->get_record('local_skillupsflow_flow', ['id' => $id], '*', MUST_EXIST);
            if (!(int)$flow->active || ($type !== 'manual' && $flow->triggertype !== $type)) {
                return 0;
            }
            $version = $DB->get_record('local_skillupsflow_ver', ['id' => $flow->active], '*', MUST_EXIST);
            $graph = definition::parse($version->definition);
            $trigger = $graph['trigger'];
            $timer = in_array($type, ['once', 'interval'], true);
            $event = in_array($type, definition::EVENTS, true);
            $now = time();
            if ($timer && ((int)$flow->nextdue <= 0 || (int)$flow->nextdue > $now || (string)$flow->nextdue !== $occurrence)) {
                return 0;
            }
            if (
                $event && (($trigger['courseid'] && $trigger['courseid'] !== $courseid) ||
                    ($trigger['userid'] && $trigger['userid'] !== $userid))
            ) {
                return 0;
            }
            $key = (new occurrence())->key($type, $timer ? $version->id . ':' . $occurrence : $occurrence);
            $duplicatescope = ['flowid' => $id, 'dedupe' => $key];
            $existing = $DB->get_field('local_skillupsflow_run', 'id', $duplicatescope);
            $limit = max(1, min(5000, (int)(get_config('local_skillupsflow', 'dailymax') ?: 250)));
            $open = $DB->count_records_select(
                'local_skillupsflow_run',
                'flowid = ? AND status IN (?, ?, ?)',
                [$id, ...self::OPEN]
            );
            $daily = $DB->count_records_select(
                'local_skillupsflow_run',
                'flowid = ? AND timecreated >= ?',
                [$id, intdiv($now, DAYSECS) * DAYSECS]
            );
            if ($existing || $open >= 100 || $daily >= $limit) {
                if ($timer && $existing) {
                    self::advance_timer($flow, $trigger, $now);
                }
                return (int)$existing;
            }
            $tx = $DB->start_delegated_transaction();
            try {
                $run = (object)($duplicatescope + [
                    'versionid' => $version->id, 'userid' => $event ? $userid : $trigger['userid'],
                    'courseid' => $event ? $courseid : $trigger['courseid'], 'status' => 'queued',
                    'node' => $graph['entry'], 'due' => $now, 'wake' => $now + 90,
                    'timecreated' => $now, 'timemodified' => $now,
                ]);
                $run->id = $DB->insert_record('local_skillupsflow_run', $run);
                self::log($run, 'queued', $type === 'manual' ? (int)$USER->id : 0);
                if ($timer) {
                    self::advance_timer($flow, $trigger, $now);
                }
                self::queue($run->id, $now);
                $tx->allow_commit();
                return (int)$run->id;
            } catch (\Throwable $exception) {
                $tx->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Fixed elapsed intervals coalesce missed occurrences into one run.
     */
    private static function advance_timer(\stdClass $flow, array $trigger, int $now): void {
        global $DB;
        $seconds = $trigger['minutes'] * MINSECS;
        $next = $trigger['type'] === 'once' ? 0 :
            (int)$flow->nextdue + (intdiv($now - (int)$flow->nextdue, $seconds) + 1) * $seconds;
        $DB->set_field('local_skillupsflow_flow', 'nextdue', $next, ['id' => $flow->id]);
    }

    /**
     * Queue only a durable run ID, with retry owned by the state machine.
     */
    public static function queue(int $id, int $when): void {
        $task = new \local_skillupsflow\task\execute_run();
        $task->set_custom_data(['runid' => $id]);
        if ($when > time()) {
            $task->set_next_run_time($when);
        }
        if (!\core\task\manager::queue_adhoc_task($task)) {
            throw new \moodle_exception('busy', 'local_skillupsflow');
        }
    }

    /**
     * Append an auditable state transition without storing payloads or exceptions.
     */
    public static function log(\stdClass $run, string $outcome, int $actorid = 0): void {
        global $DB;
        $DB->insert_record('local_skillupsflow_log', (object)[
            'runid' => $run->id, 'node' => $run->node, 'outcome' => $outcome, 'actorid' => $actorid, 'timecreated' => time(),
        ]);
    }

    /**
     * Record configuration changes in Moodle's log subsystem.
     */
    public static function audit(string $action, int $id): void {
        \local_skillupsflow\event\flow_changed::create([
            'context' => \context_system::instance(), 'objectid' => $id, 'other' => ['action' => $action],
        ])->trigger();
    }
}
