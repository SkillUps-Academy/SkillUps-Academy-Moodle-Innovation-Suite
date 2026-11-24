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

namespace local_skillupsintegration;

use local_skillupsintegration\connector\factory;
use stdClass;

/**
 * Advance bounded work slices and isolate each connector and record.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class worker {
    /** @var int Number of planned rows per application task. */
    private const BATCH = 50;
    /** @var int Safety limit even when empty remote pages have continuations. */
    private const MAX_PAGES = 150;

    /**
     * Dispatch one slice under the connector lock.
     *
     * @param int $id Run
     * @param transport|null $http Optional transport seam for integration tests
     */
    public static function process(int $id, ?transport $http = null): void {
        global $DB;
        $ref = $DB->get_record(store::RUN, ['id' => $id], 'id,sourceid');
        if (!$ref) {
            return;
        }
        try {
            store::locked("source:$ref->sourceid", static function () use ($DB, $id, $http): void {
                $run = runs::get($id);
                if (!in_array($run->state, ['queued', 'applying'], true) || $run->nextat > time()) {
                    return;
                }
                $source = profile::get($run->sourceid);
                if (!$source->enabled || $source->revision != $run->sourceversion) {
                    self::terminal($run, 'superseded', 'stale');
                    return;
                }
                if ($run->timecreated <= time() - runs::lifetime()) {
                    self::terminal($run, 'expired', 'stale');
                    return;
                }
                $pull = $run->state === 'queued';
                $actor = $pull ? $run->actorid : $run->approverid;
                if (!access::allowed($actor, $pull ? 'preview' : 'apply')) {
                    self::terminal($run, 'failed', 'permission');
                    return;
                }
                $start = microtime(true);
                try {
                    if ($pull) {
                        self::pull($source, $run, $http);
                        self::health($source->id, 'healthy', $start);
                    } else {
                        self::apply($source, $run);
                    }
                } catch (problem $failure) {
                    self::error($run, $failure);
                    if ($pull) {
                        self::health($source->id, $failure->reason, $start);
                    }
                } catch (\dml_exception $failure) {
                    self::error($run, new problem('storage', true));
                } catch (\Throwable $failure) {
                    self::error($run, new problem('processing'));
                }
            });
        } catch (problem $failure) {
            if ($failure->reason !== 'busy') {
                throw $failure;
            }
        }
    }

    /**
     * Fetch a bounded external page and commit rows together with the continuation.
     *
     * @param stdClass $source Connector
     * @param stdClass $run Simulation
     * @param transport|null $http Optional test transport
     */
    private static function pull(stdClass $source, stdClass $run, ?transport $http): void {
        $adapter = factory::make($source, $http);
        $position = $run->continuation ? json_decode($run->continuation, true, 5, JSON_THROW_ON_ERROR) :
            ['entity' => $source->users ? 'user' : 'course', 'next' => ''];
        $entity = $position['entity'];
        [$records, $next] = $adapter->page($entity, $position['next']);
        if (
            $run->records + count($records) > runs::MAX_RECORDS || $run->pages >= self::MAX_PAGES ||
            ($next !== '' && $next === $position['next'])
        ) {
            throw new problem('limit');
        }
        if ($next !== '') {
            $cursor = ['entity' => $entity, 'next' => $next];
        } else if ($entity === 'user' && $source->courses) {
            $cursor = ['entity' => 'course', 'next' => ''];
        } else {
            $cursor = null;
        }
        $mapping = profile::settings($source)['mapping'];
        $defaults = $adapter->defaults($entity);
        store::transaction(static function () use ($source, $run, $entity, $records, $cursor, $mapping, $defaults): void {
            if (!self::unchanged($run) || profile::get($source->id)->revision != $source->revision) {
                return;
            }
            foreach ($records as $offset => $record) {
                staging::record($source, $run->id, $entity, $record, $defaults, $mapping, $run->records + $offset);
            }
            store::change($run->id, [
                'state' => $cursor === null ? 'ready' : 'queued',
                'sealed' => $cursor === null ? time() : 0,
                'records' => $run->records + count($records), 'pages' => $run->pages + 1,
                'continuation' => $cursor === null ? null : json_encode($cursor, JSON_THROW_ON_ERROR),
                'attempts' => 0, 'lasterror' => null, 'nextat' => time(),
            ]);
            if ($cursor === null) {
                store::audit('sealed', $source->id, $run->id);
            }
        });
    }

    /**
     * Apply a bounded batch. A terminal row failure never discards a healthy row.
     *
     * @param stdClass $source Connector
     * @param stdClass $run Approved run
     */
    private static function apply(stdClass $source, stdClass $run): void {
        global $DB;
        $conditions = ['runid' => $run->id, 'state' => 'planned'];
        $items = $DB->get_records(store::ITEM, $conditions, 'id ASC', '*', 0, self::BATCH);
        foreach ($items as $item) {
            if (!access::allowed($run->approverid, 'apply')) {
                throw new problem('permission');
            }
            try {
                target::apply($source, $item, (int)$run->approverid);
            } catch (problem | \dml_exception $failure) {
                throw $failure;
            } catch (\Throwable $failure) {
                $DB->update_record(store::ITEM, ['id' => $item->id, 'state' => 'failed', 'errorcode' => 'processing']);
            }
        }
        if (!$DB->record_exists(store::ITEM, $conditions)) {
            $where = "runid = :runid AND state IN ('failed', 'conflict')";
            $partial = $DB->record_exists_select(store::ITEM, $where, ['runid' => $run->id]);
            self::terminal($run, $partial ? 'partial' : 'done');
        } else {
            store::change($run->id, ['attempts' => 0, 'lasterror' => null, 'nextat' => time()]);
        }
    }

    /**
     * Track a bounded failure and back off transient errors.
     *
     * @param stdClass $run Run
     * @param problem $failure Sanitized failure code
     */
    private static function error(stdClass $run, problem $failure): void {
        $attempt = $run->attempts + 1;
        if (!$failure->retryable || $attempt >= 5) {
            self::terminal($run, 'failed', $failure->reason);
            return;
        }
        store::transaction(static function () use ($run, $failure, $attempt): void {
            if (!self::unchanged($run)) {
                return;
            }
            $delay = max($failure->wait, 30 * (2 ** ($attempt - 1)));
            store::change($run->id, ['attempts' => $attempt, 'lasterror' => $failure->reason, 'nextat' => time() + $delay]);
            store::audit('backoff', $run->sourceid, $run->id);
            store::audit('err_' . $failure->reason, $run->sourceid, $run->id);
        });
    }

    /**
     * Commit terminal state and its audit event together.
     *
     * @param stdClass $run Run
     * @param string $state Final state
     * @param string|null $error Safe failure reason
     */
    private static function terminal(stdClass $run, string $state, ?string $error = null): void {
        store::transaction(static function () use ($run, $state, $error): void {
            if (!self::unchanged($run)) {
                return;
            }
            $cursor = $state === 'failed' ? $run->continuation : null;
            store::change($run->id, ['state' => $state, 'lasterror' => $error, 'continuation' => $cursor]);
            store::audit($state, $run->sourceid, $run->id);
        });
    }

    /**
     * Preserve concurrent privacy revocation of a connector or run.
     *
     * @param stdClass $run Previously read run
     * @return bool
     */
    private static function unchanged(stdClass $run): bool {
        global $DB;
        return $DB->record_exists(store::RUN, ['id' => $run->id, 'state' => $run->state]);
    }

    /**
     * Record observed provider health and duration.
     *
     * @param int $sourceid Connector
     * @param string $state State
     * @param float $start Start time
     */
    private static function health(int $sourceid, string $state, float $start): void {
        global $DB;
        $DB->update_record(store::SRC, [
            'id' => $sourceid, 'health' => $state, 'lastcheck' => time(), 'latency' => (int)((microtime(true) - $start) * 1000),
        ]);
    }
}
