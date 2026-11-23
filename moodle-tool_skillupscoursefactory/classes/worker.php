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

namespace tool_skillupscoursefactory;

/**
 * Run operations by durable checkpoints with cooperative cancellation.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class worker {
    /**
     * Process a bounded slice with a cluster concurrency limit of one.
     */
    public static function run(int $jobid): void {
        global $DB, $USER;
        $lock = support::lock('workers');
        if (!$lock) {
            return;
        }
        $previous = $USER;
        try {
            $job = $DB->get_record(jobs::JOB, ['id' => $jobid, 'state' => 'queued']);
            if (!$job || (!$job->cancelled && $job->runat > time())) {
                return;
            }
            if ($job->cancelled) {
                self::settle($jobid);
                return;
            }
            if (!support::can_operate((int)$job->userid)) {
                $DB->execute(
                    'UPDATE {' . jobs::ROW . "} SET state = :failed, errorcode = :reason WHERE jobid = :job AND state = :pending",
                    ['failed' => 'failed', 'reason' => 'denied', 'job' => $jobid, 'pending' => 'pending']
                );
                self::settle($jobid);
                return;
            }
            \core\session\manager::set_user(\core_user::get_user($job->userid, '*', MUST_EXIST));
            $rows = $DB->get_records(jobs::ROW, ['jobid' => $jobid, 'state' => 'pending'], 'id ASC', '*', 0, 10);
            $deadline = microtime(true) + 15;
            foreach ($rows as $row) {
                if ($DB->get_field(jobs::JOB, 'cancelled', ['id' => $jobid])) {
                    break;
                }
                $data = json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR);
                self::row($row, $data, (int)$job->userid);
                if ($data['source'] || microtime(true) > $deadline) {
                    break;
                }
            }
            self::settle($jobid);
        } finally {
            \core\session\manager::set_user($previous);
            $lock->release();
        }
    }

    /**
     * Resume a row exactly at its last safe stage.
     */
    private static function row(\stdClass $row, array $data, int $actor): void {
        global $DB;
        if ($row->phase === 'restoring') {
            self::result((int)$row->id, 'attention', 'ambiguous');
            return;
        }
        $attempts = (int)$row->attempts + 1;
        if ($attempts > 3) {
            self::result((int)$row->id, 'failed', 'attemptlimit');
            return;
        }
        $DB->set_field(jobs::ROW, 'attempts', $attempts, ['id' => $row->id]);
        try {
            validator::authorize($data, $actor);
            if ($row->phase === 'new') {
                provisioner::prepare($row, $data);
                $row = $DB->get_record(jobs::ROW, ['id' => $row->id], '*', MUST_EXIST);
            }
            if ($row->phase === 'prepared') {
                provisioner::destination($row, $data, $actor);
                provisioner::restore($row, $data, $actor);
            }
            provisioner::finish($row, $data, $actor);
        } catch (\Throwable $error) {
            $phase = $DB->get_field(jobs::ROW, 'phase', ['id' => $row->id], MUST_EXIST);
            $code = $error instanceof \moodle_exception && $error->module === support::C ? $error->errorcode : 'executionerror';
            if ($phase === 'restoring') {
                self::result((int)$row->id, 'attention', 'ambiguous');
            } else if ($code === 'cancelledrow') {
                self::result((int)$row->id, 'cancelled', $code);
            } else {
                self::result((int)$row->id, 'failed', $code);
            }
        }
    }

    /**
     * Store an explicit operator-facing result.
     */
    private static function result(int $id, string $state, string $code): void {
        global $DB;
        $DB->update_record(jobs::ROW, ['id' => $id, 'state' => $state, 'errorcode' => $code]);
    }

    /**
     * Settle atomic controls without overwriting concurrent cancel or retry requests.
     */
    private static function settle(int $jobid): void {
        global $DB;
        $lock = support::lock('control_' . $jobid, 3);
        if (!$lock) {
            return;
        }
        try {
            if ($DB->get_field(jobs::JOB, 'cancelled', ['id' => $jobid])) {
                $rows = $DB->get_records(jobs::ROW, ['jobid' => $jobid, 'state' => 'pending'], '', 'id,phase');
                foreach ($rows as $row) {
                    self::result(
                        (int)$row->id,
                        $row->phase === 'restoring' ? 'attention' : 'cancelled',
                        $row->phase === 'restoring' ? 'ambiguous' : 'cancelledrow'
                    );
                }
                jobs::state($jobid, 'cancelled');
                jobs::audit($jobid, 'stopped');
            } else if (!$DB->record_exists(jobs::ROW, ['jobid' => $jobid, 'state' => 'pending'])) {
                jobs::state($jobid, 'done');
                jobs::audit($jobid, 'finished');
            }
        } finally {
            $lock->release();
        }
    }
}
