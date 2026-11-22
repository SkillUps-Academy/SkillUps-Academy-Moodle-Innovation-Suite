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

use local_skillupsflow\action\moodle;
use local_skillupsflow\condition\predicates;

/**
 * Run one state transition under a per-workflow lock.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /**
     * @var bool Suppress recursive ingress during a native Moodle action.
     */
    private static bool $applying = false;

    /**
     * Whether this process is executing a workflow action.
     */
    public static function applying(): bool {
        return self::$applying;
    }

    /**
     * Execute once. Stale or duplicate jobs are harmless.
     */
    public static function execute(int $id): void {
        global $DB, $USER;
        if (!$run = $DB->get_record('local_skillupsflow_run', ['id' => $id])) {
            return;
        }
        $lock = store::lock($run->flowid);
        try {
            $run = $DB->get_record('local_skillupsflow_run', ['id' => $id]);
            if (
                !$run || !in_array($run->status, store::WORK, true) || $run->due > time() ||
                    !$DB->get_field('local_skillupsflow_flow', 'active', ['id' => $run->flowid])
            ) {
                return;
            }
            $version = $DB->get_record(
                'local_skillupsflow_ver',
                ['id' => $run->versionid, 'flowid' => $run->flowid],
                '*',
                MUST_EXIST
            );
            try {
                if ($version->revoked) {
                    throw new \moodle_exception('actorunavailable', 'local_skillupsflow');
                }
                $actor = security::actor($version->userid);
            } catch (\moodle_exception $exception) {
                self::transaction(static fn() => self::finish($run, 'failed', 'actorunavailable'));
                return;
            }
            $graph = definition::parse($version->definition);
            $nodes = array_column($graph['nodes'], null, 'id');
            if (!$run->node) {
                self::transaction(static fn() => self::finish($run, 'completed', 'completed'));
                return;
            }
            if (!isset($nodes[$run->node]) || $run->steps >= 40) {
                self::transaction(static fn() => self::finish($run, 'failed', 'invalidstate'));
                return;
            }
            $node = $nodes[$run->node];
            if ($run->status === 'sending') {
                self::transaction(static fn() => self::failure($run, $node, 'uncertain'));
                return;
            }
            if ($run->status === 'approval') {
                self::transaction(static fn() => self::decision($run, $node, false, 'expired'));
                return;
            }
            $previous = $USER;
            \core\session\manager::set_user($actor);
            self::$applying = true;
            try {
                if ($node['type'] === 'notify') {
                    self::send($run, $node, $actor);
                } else {
                    try {
                        self::transaction(static fn() => self::step($run, $node, $actor));
                    } catch (\Throwable $exception) {
                        self::transaction(static fn() => self::retry($run, $node, $exception));
                    }
                }
            } finally {
                self::$applying = false;
                \core\session\manager::set_user($previous);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Handle pure routing, approvals, delays and transactional native actions.
     */
    private static function step(\stdClass $run, array $node, \stdClass $actor): void {
        switch ($node['type']) {
            case 'stop':
                self::finish($run, 'completed', 'completed');
                break;
            case 'condition':
                $port = (new predicates())->evaluate($node, $run, $actor) ? 'yes' : 'no';
                self::advance($run, $node[$port], $port);
                break;
            case 'delay':
                self::advance($run, $node['next'], 'delayed', $node['minutes'] * MINSECS);
                break;
            case 'approval':
                store::log($run, 'awaiting');
                self::schedule($run, ['status' => 'approval'], time() + $node['minutes'] * MINSECS);
                break;
            default:
                (new moodle())->execute($node, $run, $actor);
                self::advance($run, $node['next'], 'applied');
        }
    }

    /**
     * Record an intent before calling a notification channel that cannot be rolled back.
     */
    private static function send(\stdClass $run, array $node, \stdClass $actor): void {
        self::transaction(static function () use ($run): void {
            store::log($run, 'sending');
            self::schedule($run, ['status' => 'sending'], time() + 300);
        });
        try {
            (new moodle())->execute($node, $run, $actor);
        } catch (\Throwable $exception) {
            self::transaction(static fn() => self::failure($run, $node, 'notificationfailed'));
            return;
        }
        self::transaction(static fn() => self::advance($run, $node['next'], 'applied'));
    }

    /**
     * Retry rollback-safe failures with bounded exponential delay.
     */
    private static function retry(\stdClass $run, array $node, \Throwable $exception): void {
        if ($exception instanceof \required_capability_exception) {
            self::failure($run, $node, 'forbidden');
            return;
        }
        $attempt = (int)$run->attempts + 1;
        if ($attempt >= 3) {
            self::failure($run, $node, 'failed');
            return;
        }
        store::log($run, 'retry');
        self::schedule($run, ['attempts' => $attempt, 'status' => 'queued'], time() + 60 * 2 ** ($attempt - 1));
    }

    /**
     * Select a configured fallback or end with a failed state.
     */
    private static function failure(\stdClass $run, array $node, string $outcome): void {
        if (!empty($node['error'])) {
            self::advance($run, $node['error'], $outcome);
        } else {
            self::finish($run, 'failed', $outcome);
        }
    }

    /**
     * Apply a human decision once, under the same lock as the reconciler.
     */
    public static function decide(int $id, bool $approve): void {
        global $DB, $USER;
        require_capability('local/skillupsflow:approve', \context_system::instance());
        $run = $DB->get_record('local_skillupsflow_run', ['id' => $id], '*', MUST_EXIST);
        $lock = store::lock($run->flowid);
        try {
            $run = $DB->get_record('local_skillupsflow_run', ['id' => $id], '*', MUST_EXIST);
            $version = $DB->get_record('local_skillupsflow_ver', ['id' => $run->versionid], '*', MUST_EXIST);
            if (
                $run->status !== 'approval' || $version->revoked || (int)$version->userid === (int)$USER->id ||
                    $run->due <= time() ||
                    !$DB->get_field('local_skillupsflow_flow', 'active', ['id' => $run->flowid])
            ) {
                throw new \moodle_exception('decisionunavailable', 'local_skillupsflow');
            }
            $nodes = array_column(definition::parse($version->definition)['nodes'], null, 'id');
            $node = $nodes[$run->node];
            self::transaction(static fn() => self::decision(
                $run,
                $node,
                $approve,
                $approve ? 'approved' : 'rejected',
                $USER->id
            ));
        } finally {
            $lock->release();
        }
    }

    /**
     * End an unconnected rejection as failed, or follow the selected decision port.
     */
    private static function decision(\stdClass $run, array $node, bool $approved, string $outcome, int $actorid = 0): void {
        $next = $node[$approved ? 'yes' : 'no'];
        if (!$approved && !$next) {
            self::finish($run, 'failed', $outcome, $actorid);
        } else {
            self::advance($run, $next, $outcome, 0, $actorid);
        }
    }

    /**
     * End a pending or paused run from the administrator interface.
     */
    public static function cancel(int $id): void {
        global $DB, $USER;
        require_capability('local/skillupsflow:manage', \context_system::instance());
        $run = $DB->get_record('local_skillupsflow_run', ['id' => $id], '*', MUST_EXIST);
        $lock = store::lock($run->flowid);
        try {
            $run = $DB->get_record('local_skillupsflow_run', ['id' => $id], '*', MUST_EXIST);
            if (in_array($run->status, store::OPEN, true)) {
                self::transaction(static fn() => self::finish($run, 'cancelled', 'cancelled', $USER->id));
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Append a transition and select its next node.
     */
    private static function advance(\stdClass $run, string $next, string $outcome, int $delay = 0, int $actorid = 0): void {
        store::log($run, $outcome, $actorid);
        $changes = ['node' => $next ?: null, 'attempts' => 0, 'steps' => $run->steps + 1];
        if (!$next && !$delay) {
            self::finish($run, 'completed', 'completed');
            return;
        }
        self::schedule($run, $changes + ['status' => 'queued'], time() + $delay);
    }

    /**
     * Persist progress and queue a wakeup atomically.
     */
    private static function schedule(\stdClass $run, array $changes, int $due): void {
        global $DB;
        $DB->update_record('local_skillupsflow_run', (object)($changes + [
            'id' => $run->id, 'due' => $due, 'wake' => $due + 90, 'timemodified' => time(),
        ]));
        store::queue($run->id, $due);
    }

    /**
     * Complete a run and release its place in the admission limit.
     */
    private static function finish(\stdClass $run, string $status, string $outcome, int $actorid = 0): void {
        global $DB;
        store::log($run, $outcome, $actorid);
        $DB->update_record('local_skillupsflow_run', (object)[
            'id' => $run->id, 'status' => $status, 'due' => 0, 'wake' => 0, 'timemodified' => time(),
        ]);
    }

    /**
     * Roll back native effects and plugin progress together.
     */
    private static function transaction(callable $work): void {
        global $DB;
        $tx = $DB->start_delegated_transaction();
        try {
            $work();
            $tx->allow_commit();
        } catch (\Throwable $exception) {
            $tx->rollback($exception);
        }
    }
}
