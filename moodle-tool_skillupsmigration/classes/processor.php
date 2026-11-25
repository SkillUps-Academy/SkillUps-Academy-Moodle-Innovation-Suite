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

namespace tool_skillupsmigration;

/**
 * Orchestrate bounded, resumable file transfer and non-replayable native restores.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class processor {
    /**
     * Queue exactly the current job revision.
     *
     * @param \stdClass $job Active job
     * @param int $when Earliest useful run time
     */
    public static function queue(\stdClass $job, int $when = 0): void {
        $task = new task\process();
        $task->set_custom_data(['job' => (int)$job->id, 'wave' => (int)$job->wave]);
        $task->set_userid((int)$job->actor);
        $task->set_next_run_time(max(time(), $when));
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Compute an approved job row private staging location from its given identifiers.
     *
     * @param \stdClass $item Row record
     * @return string Absolute private path
     */
    public static function path(\stdClass $item): string {
        if (!preg_match('/^[0-9a-f]{48}$/D', $item->requestkey)) {
            support::fail('archivepath');
        }
        return support::staging() . DIRECTORY_SEPARATOR . $item->requestkey . '.mbz';
    }

    /**
     * Remove only the private file derived from this exact row.
     *
     * @param \stdClass $item Row
     */
    public static function clean(\stdClass $item): void {
        $path = self::path($item);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Advance one row one phase and queue the next available work.
     *
     * @param int $id Job ID
     * @param int $wave Expected job wave
     */
    public static function advance(int $id, int $wave): void {
        global $DB;
        $next = support::lock('job:' . $id, static function () use ($DB, $id, $wave): ?array {
            $job = $DB->get_record(support::JOB, ['id' => $id]);
            if (!$job || $job->wave != $wave || !in_array($job->state, ['preflight', 'running'], true)) {
                return null;
            }
            foreach ($DB->get_records(support::ITEM, ['jobid' => $id, 'state' => 'restoring']) as $interrupted) {
                self::failure($job, $interrupted, 'restoreinterrupted', 'uncertain');
            }
            $states = $job->state === 'preflight' ? ['planned', 'waiting', 'transfer'] : ['ready', 'verify'];
            [$list, $params] = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED);
            $rows = $DB->get_records_select(
                support::ITEM,
                "jobid = :jid AND state $list",
                $params + ['jid' => $id],
                'nexttry ASC, id ASC',
                '*',
                0,
                1
            );
            $row = reset($rows);
            if ($row && $row->nexttry <= time()) {
                try {
                    support::category((int)$job->categoryid, (int)$job->actor);
                    if (!hash_equals($job->originhash, client::identity($job->endpoint))) {
                        support::fail('originmismatch');
                    }
                    self::step($job, $row);
                } catch (\Throwable $error) {
                    $rec = support::get(support::ITEM, (int)$row->id);
                    $code = $error instanceof \moodle_exception ? $error->errorcode : 'operationfailed';
                    $safe = ['remoteconnection', 'archiveio', 'fileintegrity', 'archivebounds', 'archiveformat', 'archivepath',
                        'archivemismatch', 'backupfailed', 'originmismatch', 'sourcechanged', 'configuration', 'usersmap',
                        'newersource', 'missingplugin', 'olderplugin', 'unsupportedactivity', 'nativeprecheck', 'targetchanged',
                        'duplicate', 'unavailable', 'remoteaccess', 'remoteresponse', 'endpoint',
                        'credential', 'selection', 'badrequest',
                    ];
                    $code = in_array($code, $safe, true) ? $code : 'operationfailed';
                    if ($rec->state === 'restoring') {
                        self::failure($job, $rec, 'restoreinterrupted', 'uncertain');
                    } else if (in_array($code, ['remoteconnection', 'archiveio'], true) && $rec->attempts < 4) {
                        self::delay($job, $rec, $code);
                    } else {
                        self::failure($job, $rec, $code);
                    }
                }
            }
            $pending = $DB->get_records_select(
                support::ITEM,
                "jobid = :jid AND state $list",
                $params + ['jid' => $id],
                'nexttry ASC, id ASC',
                'id,nexttry',
                0,
                1
            );
            $queued = reset($pending);
            if (!$queued) {
                $job->state = $job->state === 'preflight' ? 'review' : 'complete';
                support::audit($id, 0, 0, $job->state);
            }
            $job->wave++;
            $job->updated = time();
            $DB->update_record(support::JOB, $job);
            return $queued ? [$job, (int)$queued->nexttry] : null;
        }, 0);
        if ($next) {
            self::queue(...$next);
        }
    }

    /**
     * Execute the next idempotent boundary.
     *
     * @param \stdClass $job Authorized job
     * @param \stdClass $row Row
     */
    private static function step(\stdClass $job, \stdClass $row): void {
        global $DB;
        $options = support::decode($row->options);
        $inventory = support::decode($row->inventory);
        $row->nexttry = 0;
        if ($row->state === 'planned') {
            $issues = archive::compatibility($inventory, $options);
            $row->report = support::encode(['issues' => $issues]);
            if ($issues) {
                $row->state = 'blocked';
            } else {
                $response = client::call($job->endpoint, 'prepare', [
                    'course' => (int)$row->sourceid, 'key' => $row->requestkey, 'options' => jobs::origin_options($options),
                    'fingerprint' => hash('sha256', $row->inventory),
                ]);
                if (empty($response['id']) || !is_int($response['id'])) {
                    support::fail('remoteresponse');
                }
                $row->exportid = $response['id'];
                $row->state = 'waiting';
                $row->nexttry = time() + 5;
            }
        } else if ($row->state === 'waiting') {
            $response = client::call($job->endpoint, 'status', ['id' => (int)$row->exportid]);
            if (($response['id'] ?? 0) != $row->exportid) {
                support::fail('remoteresponse');
            }
            if ($response['state'] === 'failed') {
                $safe = ['archivebounds', 'sourcechanged', 'selection', 'unavailable', 'configuration'];
                support::fail(in_array($response['reason'] ?? '', $safe, true) ? $response['reason'] : 'backupfailed');
            }
            if ($response['state'] === 'ready') {
                if (
                    $response['bytes'] < 1 || $response['bytes'] > support::limit() ||
                    !preg_match('/^[0-9a-f]{64}$/D', $response['sha256'] ?? '')
                ) {
                    support::fail('archivebounds');
                }
                $row->bytes = (int)$response['bytes'];
                $row->digest = $response['sha256'];
                $row->state = 'transfer';
            } else if (in_array($response['state'], ['pending', 'building'], true)) {
                if (time() - $row->updated > DAYSECS) {
                    support::fail('backupfailed');
                }
                $DB->set_field(support::ITEM, 'nexttry', time() + 15, ['id' => $row->id]);
                return;
            } else {
                support::fail('remoteresponse');
            }
        } else if ($row->state === 'transfer') {
            if (self::download($job, $row)) {
                $report = archive::inspect(self::path($row), $inventory, $options);
                $row->report = support::encode($report);
                $row->state = $report['issues'] ? 'blocked' : 'ready';
            }
        } else if ($row->state === 'ready') {
            self::checksum($row);
            if (archive::compatibility($inventory, $options)) {
                support::fail('configuration');
            }
            destination::restore($job, $row, self::path($row));
            return;
        } else {
            $report = support::decode($row->report);
            $report['verification'] = destination::verify($job, $row);
            $row->report = support::encode($report);
            $row->state = $report['verification']['match'] ? 'done' : 'blocked';
        }
        $row->attempts = 0;
        $row->updated = time();
        $DB->update_record(support::ITEM, $row);
        support::audit((int)$job->id, (int)$row->id, 0, $row->state);
        if ($row->state === 'done') {
            self::clean($row);
        }
    }

    /**
     * Transfer at most four MiB in each execution and resume from the private file length.
     *
     * @param \stdClass $job Active job
     * @param \stdClass $row Receiver row with expected size and SHA-256
     * @return bool Whether transfer is complete and verified
     */
    private static function download(\stdClass $job, \stdClass $row): bool {
        $path = self::path($row);
        $file = fopen($path, 'c+b');
        if (!$file) {
            support::fail('archiveio');
        }
        try {
            if (fseek($file, 0, SEEK_END) !== 0 || ($offset = ftell($file)) === false) {
                support::fail('archiveio');
            }
            $deadline = microtime(true) + 15;
            for ($i = 0; $offset < $row->bytes && $i < 16 && microtime(true) <= $deadline; $i++) {
                $response = client::call($job->endpoint, 'chunk', ['id' => (int)$row->exportid, 'offset' => $offset]);
                $bytes = base64_decode($response['data'] ?? '', true);
                $length = min(support::CHUNK, $row->bytes - $offset);
                if (
                    ($response['id'] ?? null) != $row->exportid || ($response['offset'] ?? null) !== $offset ||
                    ($response['bytes'] ?? null) != $row->bytes || ($response['sha256'] ?? '') !== $row->digest ||
                    !is_string($bytes) || strlen($bytes) !== $length
                ) {
                    support::fail('remoteresponse');
                }
                if (fwrite($file, $bytes) !== $length || !fflush($file)) {
                    ftruncate($file, $offset);
                    support::fail('archiveio');
                }
                $offset += $length;
            }
        } finally {
            fclose($file);
        }
        if ($offset >= $row->bytes) {
            self::checksum($row);
            return true;
        }
        return false;
    }

    /**
     * Check the local stored length and cryptographic digest.
     *
     * @param \stdClass $row Row
     */
    private static function checksum(\stdClass $row): void {
        $path = self::path($row);
        clearstatcache(true, $path);
        if (!is_file($path) || filesize($path) != $row->bytes || !hash_equals($row->digest, hash_file('sha256', $path))) {
            support::fail('fileintegrity');
        }
    }

    /**
     * Defer an explicitly reversible step after a temporary network or disk error.
     *
     * @param \stdClass $job Migration
     * @param \stdClass $row Item
     * @param string $code Safe error key
     */
    private static function delay(\stdClass $job, \stdClass $row, string $code): void {
        global $DB;
        $row->attempts++;
        $row->nexttry = time() + 15 * (2 ** $row->attempts);
        $report = support::decode($row->report);
        $report['lastretry'] = $code;
        $row->report = support::encode($report);
        $DB->update_record(support::ITEM, $row);
        support::audit((int)$job->id, (int)$row->id, 0, 'deferred');
    }

    /**
     * Write a safe terminal error and keep destination evidence.
     *
     * @param \stdClass $job Migration
     * @param \stdClass $row Item
     * @param string $code Safe report key
     * @param string $state Terminal state
     */
    private static function failure(\stdClass $job, \stdClass $row, string $code, string $state = 'blocked'): void {
        global $DB;
        $report = support::decode($row->report);
        $report['issues'][] = ['code' => $code, 'detail' => ''];
        $row->report = support::encode($report);
        $row->state = $state;
        $row->updated = time();
        $DB->update_record(support::ITEM, $row);
        support::audit((int)$job->id, (int)$row->id, 0, $state);
    }
}
