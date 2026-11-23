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

namespace tool_skillupsenrollment;

/**
 * Process an authorized batch in bounded, independent transactions.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class worker {
    /**
     * Process up to fifty intents before returning control to Moodle.
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
            if (!$job->cancelled && support::can_operate((int)$job->userid)) {
                \core\session\manager::set_user(\core_user::get_user($job->userid, '*', MUST_EXIST));
                self::apply($job);
            } else if (!$job->cancelled) {
                $DB->execute(
                    'UPDATE {' . jobs::ROW . '} SET state = :failed, errorcode = :reason, timemodified = :modified
                      WHERE jobid = :job AND state = :pending',
                    ['failed' => 'failed', 'reason' => 'denied', 'modified' => time(), 'job' => $jobid, 'pending' => 'pending']
                );
            }
            self::settle($jobid);
        } finally {
            \core\session\manager::set_user($previous);
            $lock->release();
        }
    }

    /**
     * Isolate failures so that a later row can still succeed.
     */
    private static function apply(\stdClass $job): void {
        global $DB;
        $rows = $DB->get_records(jobs::ROW, ['jobid' => $job->id, 'state' => 'pending'], 'id ASC', '*', 0, 50);
        $deadline = microtime(true) + 15;
        foreach ($rows as $record) {
            if ($DB->get_field(jobs::JOB, 'cancelled', ['id' => $job->id])) {
                break;
            }
            $attempt = (int)$record->attempts + 1;
            if ($attempt > 3) {
                jobs::mark((int)$record->id, 'failed', 'attemptlimit');
                continue;
            }
            $DB->set_field(jobs::ROW, 'attempts', $attempt, ['id' => $record->id]);
            try {
                mutation::apply($record, (int)$job->userid);
            } catch (\Throwable $exception) {
                $reason = $exception instanceof \moodle_exception && $exception->module === support::C
                    ? $exception->errorcode
                    : 'executionerror';
                jobs::mark((int)$record->id, 'failed', $reason);
            }
            if (microtime(true) > $deadline) {
                break;
            }
        }
    }

    /**
     * Resolve job-level completion with a lock shared by operator commands.
     */
    private static function settle(int $jobid): void {
        global $DB;
        $lock = support::lock('control_' . $jobid, 3);
        if (!$lock) {
            return;
        }
        try {
            if ($DB->get_field(jobs::JOB, 'cancelled', ['id' => $jobid])) {
                $DB->execute(
                    'UPDATE {' . jobs::ROW . '} SET state = :cancelled, timemodified = :modified
                      WHERE jobid = :job AND state = :pending',
                    ['cancelled' => 'cancelled', 'modified' => time(), 'job' => $jobid, 'pending' => 'pending']
                );
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
