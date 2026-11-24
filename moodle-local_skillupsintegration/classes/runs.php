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

use core\task\manager;
use local_skillupsintegration\connector\factory;
use stdClass;

/**
 * Create immutable simulations and gate their application independently.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class runs {
    /** @var int Cap remote or pushed items per simulation. */
    public const MAX_RECORDS = 10000;
    /** @var int Limit simultaneously pending simulations for one source. */
    private const MAX_PENDING = 20;

    /**
     * Launch a pull simulation.
     *
     * @param int $id Connector
     * @return int
     */
    public static function preview(int $id): int {
        global $USER;
        access::require('preview');
        $run = store::locked("source:$id", static function () use ($id, $USER): int {
            $source = profile::get($id);
            if (!$source->enabled || !in_array('pull', factory::make($source)->capabilities(), true)) {
                throw new problem('unavailable');
            }
            return store::transaction(static fn(): int => self::create(
                $source,
                (int)$USER->id,
                'pull',
                hash('sha256', random_bytes(32)),
                '',
                'queued',
            ));
        });
        self::enqueue($run);
        return $run;
    }

    /**
     * Accept authenticated source records and seal a simulation without changing native data.
     *
     * @param int $id Connector
     * @param string $origin REST service or webhook
     * @param int $actor Moodle service account, or zero for webhook authentication
     * @param string $json Exact authorised body
     * @param int $revision Expected current revision for a verified webhook
     * @return array
     */
    public static function ingest(int $id, string $origin, int $actor, string $json, int $revision = 0): array {
        global $DB;
        if (strlen($json) > ingress::LIMIT) {
            throw new problem('limit');
        }
        $data = json_decode($json, true, 20);
        $event = $data['event'] ?? null;
        $records = $data['records'] ?? null;
        if (
            !is_string($event) || !preg_match('/^[a-zA-Z0-9_.:-]{1,128}$/D', $event) ||
            !is_array($records) || !array_is_list($records) || count($records) < 1 || count($records) > 100
        ) {
            throw new problem('response');
        }
        return store::locked("source:$id", static function () use ($DB, $id, $origin, $actor, $data, $json, $revision): array {
            $source = profile::get($id);
            if (!$source->enabled || !in_array('push', factory::make($source)->capabilities(), true)) {
                throw new problem('unavailable');
            }
            if ($origin === 'rest') {
                if ($source->pushuserid != $actor || !access::allowed($actor, 'ingest')) {
                    throw new problem('permission');
                }
            } else if ($origin === 'webhook') {
                if (!$source->webhook || $source->revision != $revision || !access::allowed($source->ownerid, 'preview')) {
                    throw new problem('permission');
                }
            } else {
                throw new problem('permission');
            }
            $key = hash('sha256', $data['event']);
            $hash = hash('sha256', $json);
            $existing = $DB->get_record(store::RUN, ['sourceid' => $id, 'requestkey' => $key]);
            if ($existing) {
                if (!hash_equals($existing->requesthash, $hash)) {
                    throw new problem('replay');
                }
                return ['runid' => (int)$existing->id, 'state' => $existing->state, 'replayed' => true];
            }
            $records = $data['records'];
            return store::transaction(static function () use ($DB, $source, $actor, $origin, $key, $hash, $records): array {
                $run = self::create($source, $actor, $origin, $key, $hash, 'ready');
                $map = profile::settings($source)['mapping'];
                foreach ($records as $offset => $entry) {
                    $entity = is_array($entry) && is_string($entry['entity'] ?? null) ? $entry['entity'] : '';
                    $fields = is_array($entry) ? $entry['fields'] ?? null : null;
                    staging::record($source, $run, $entity, $fields, [], $map, $offset);
                }
                store::change($run, ['records' => count($records), 'sealed' => time()]);
                $health = ['id' => $source->id, 'health' => 'healthy', 'lastcheck' => time(), 'latency' => -1];
                $DB->update_record(store::SRC, $health);
                return ['runid' => $run, 'state' => 'ready', 'replayed' => false];
            });
        });
    }

    /**
     * Authorize application of a fresh reviewed simulation.
     *
     * @param int $id Run
     * @param int $sealed Snapshot timestamp seen by the approver
     */
    public static function approve(int $id, int $sealed): void {
        global $USER, $DB;
        access::require('apply');
        $ref = self::get($id);
        store::locked("source:$ref->sourceid", static function () use ($DB, $USER, $id, $sealed): void {
            $run = self::get($id);
            $source = profile::get($run->sourceid);
            if ($run->state !== 'ready' || $run->sealed != $sealed || $sealed <= time() - self::lifetime()) {
                throw new problem('stale');
            }
            if (!$source->enabled || $source->revision != $run->sourceversion) {
                throw new problem('stale');
            }
            if (!$DB->record_exists(store::ITEM, ['runid' => $id, 'state' => 'planned'])) {
                throw new problem('nothing');
            }
            store::transaction(static function () use ($USER, $id, $source): void {
                store::change($id, [
                    'state' => 'applying', 'step' => 'apply', 'nextat' => time(), 'attempts' => 0,
                    'approverid' => $USER->id, 'lasterror' => null,
                ]);
                store::audit('approved', $source->id, $id, (int)$USER->id);
            });
        });
        self::enqueue($id);
    }

    /**
     * Retry a paused run without discarding successful rows or the checked snapshot.
     *
     * @param int $id Run
     */
    public static function retry(int $id): void {
        global $USER;
        $ref = self::get($id);
        store::locked("source:$ref->sourceid", static function () use ($id, $USER): void {
            $run = self::get($id);
            $source = profile::get($run->sourceid);
            access::require($run->step === 'apply' ? 'apply' : 'preview');
            if (
                !$source->enabled || $source->revision != $run->sourceversion || $run->state !== 'failed' ||
                $run->timecreated < time() - self::lifetime()
            ) {
                throw new problem('stale');
            }
            $canretryapply = (int)$run->approverid === (int)$USER->id && $run->sealed > time() - self::lifetime();
            if (
                ($run->step === 'apply' && !$canretryapply) ||
                ($run->step === 'pull' && !access::allowed($run->actorid, 'preview'))
            ) {
                throw new problem('permission');
            }
            store::transaction(static function () use ($USER, $id, $run): void {
                store::change($id, [
                    'state' => $run->step === 'apply' ? 'applying' : 'queued',
                    'nextat' => time(), 'attempts' => 0, 'lasterror' => null,
                ]);
                store::audit('retried', $run->sourceid, $id, (int)$USER->id);
            });
        });
        self::enqueue($id);
    }

    /**
     * Return a run by its internal ID.
     *
     * @param int $id Run
     * @return stdClass
     */
    public static function get(int $id): stdClass {
        global $DB;
        $run = $DB->get_record(store::RUN, ['id' => $id]);
        if (!$run) {
            throw new problem('unavailable');
        }
        return $run;
    }

    /**
     * Determine how long an operator can act on staged information.
     *
     * @return int
     */
    public static function lifetime(): int {
        return max(1, min(30, (int)(get_config(access::C, 'stagedays') ?: 7))) * DAYSECS;
    }

    /**
     * Schedule a slice. The periodic dispatcher recovers committed but unqueued work.
     *
     * @param int $id Run
     */
    public static function enqueue(int $id): void {
        $task = new task\process();
        $task->set_custom_data(['id' => $id]);
        manager::queue_adhoc_task($task, true);
    }

    /**
     * Insert a new preview and its audit trail.
     *
     * @param stdClass $source Connector
     * @param int $actor Operator
     * @param string $origin Origin
     * @param string $key Unique idempotency key
     * @param string $hash Exact message fingerprint
     * @param string $state Initial state
     * @return int
     */
    private static function create(stdClass $source, int $actor, string $origin, string $key, string $hash, string $state): int {
        global $DB;
        $current = profile::get($source->id);
        if (!$current->enabled || $current->revision != $source->revision) {
            throw new problem('stale');
        }
        $conditions = ['sourceid' => $source->id, 'sourceversion' => $source->revision, 'state' => $state];
        if ($DB->count_records(store::RUN, $conditions) >= self::MAX_PENDING) {
            throw new problem('busy', true);
        }
        $now = time();
        $id = (int)$DB->insert_record(store::RUN, [
            'sourceid' => $source->id, 'sourceversion' => $source->revision, 'actorid' => $actor,
            'origin' => $origin, 'requestkey' => $key, 'requesthash' => $hash, 'state' => $state,
            'step' => $origin === 'pull' ? 'pull' : 'push', 'timecreated' => $now, 'timemodified' => $now, 'nextat' => $now,
        ]);
        store::audit('previewed', $source->id, $id, $actor);
        return $id;
    }
}
