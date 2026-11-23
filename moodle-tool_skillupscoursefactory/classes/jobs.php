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
 * Manage persisted previews, authorization and job transitions.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class jobs {
    /** Operation records. */
    public const JOB = 'tool_skillupscoursefactory_job';
    /** Individual course requests. */
    public const ROW = 'tool_skillupscoursefactory_row';
    /** Institutional template records. */
    public const TPL = 'tool_skillupscoursefactory_tpl';

    /**
     * Store a preview without any course mutations.
     */
    public static function draft(array $input, int $size, int $runat, string $timezone): int {
        global $DB, $USER;
        if (!support::can_operate((int)$USER->id)) {
            throw new \moodle_exception('denied', support::C);
        }
        if (!in_array($size, support::sizes(), true) || !$input || count($input) > $size) {
            throw new \moodle_exception('importlimit', support::C);
        }
        if ($runat < 0 || $runat > time() + 366 * DAYSECS) {
            throw new \moodle_exception('invalidschedule', support::C);
        }
        $rows = validator::preview($input, (int)$USER->id, $timezone);
        $tx = $DB->start_delegated_transaction();
        $now = time();
        $id = $DB->insert_record(self::JOB, [
            'userid' => $USER->id, 'state' => 'draft', 'batchsize' => $size, 'runat' => $runat,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $data = [];
        foreach ($rows as $position => $row) {
            $data[] = [
                'jobid' => $id, 'position' => $position, 'payload' => support::json($row['fields']),
                'state' => $row['error'] === '' ? 'pending' : 'invalid', 'phase' => 'new', 'errorcode' => $row['error'],
            ];
        }
        $DB->insert_records(self::ROW, $data);
        $tx->allow_commit();
        return $id;
    }

    /**
     * Use a generic response for unavailable or out-of-scope jobs.
     */
    public static function visible(int $id): \stdClass {
        global $DB, $USER;
        $job = $DB->get_record(self::JOB, ['id' => $id]);
        if (
            !$job || !support::can_operate((int)$USER->id)
                || ((int)$job->userid !== (int)$USER->id && !has_capability(
                    'tool/skillupscoursefactory:manage',
                    \context_system::instance()
                ))
        ) {
            throw new \moodle_exception('denied', support::C);
        }
        return $job;
    }

    /**
     * Authorize confirmed rows, stop future work or retry a selected recoverable row.
     */
    public static function command(int $jobid, string $action, int $rowid = 0): void {
        global $DB, $USER;
        $lock = support::lock('control_' . $jobid, 4);
        if (!$lock) {
            throw new \moodle_exception('busy', support::C);
        }
        try {
            $job = self::visible($jobid);
            if ((int)$job->userid !== (int)$USER->id && $action !== 'cancel') {
                throw new \moodle_exception('denied', support::C);
            }
            if ($action === 'confirm' && $job->state === 'draft' && !$job->cancelled) {
                if (!in_array((int)$job->batchsize, support::sizes(), true)) {
                    throw new \moodle_exception('importlimit', support::C);
                }
                if (!$DB->record_exists(self::ROW, ['jobid' => $jobid, 'state' => 'pending'])) {
                    throw new \moodle_exception('emptyimport', support::C);
                }
                self::state($jobid, 'queued');
            } else if ($action === 'cancel' && in_array($job->state, ['draft', 'queued'], true) && !$job->cancelled) {
                $edit = ['id' => $jobid, 'cancelled' => 1, 'timemodified' => time()];
                if ($job->state === 'draft') {
                    $DB->set_field(self::ROW, 'state', 'cancelled', ['jobid' => $jobid, 'state' => 'pending']);
                    $edit['state'] = 'cancelled';
                }
                $DB->update_record(self::JOB, $edit);
            } else if ($action === 'retry' && !$job->cancelled && in_array($job->state, ['queued', 'done'], true)) {
                $row = $DB->get_record(self::ROW, ['id' => $rowid, 'jobid' => $jobid, 'state' => 'failed']);
                if (!$row || $row->attempts >= 3 || $row->phase === 'restoring') {
                    throw new \moodle_exception('invalidaction', support::C);
                }
                $DB->update_record(self::ROW, ['id' => $rowid, 'state' => 'pending', 'errorcode' => '']);
                self::state($jobid, 'queued');
            } else {
                throw new \moodle_exception('invalidaction', support::C);
            }
            self::audit($jobid, $action);
        } finally {
            $lock->release();
        }
    }

    /**
     * Update the public operation state.
     */
    public static function state(int $id, string $state): void {
        global $DB;
        $DB->update_record(self::JOB, ['id' => $id, 'state' => $state, 'timemodified' => time()]);
    }

    /**
     * Summarize rows with one grouped query.
     */
    public static function counts(int $jobid): array {
        global $DB;
        return $DB->get_records_sql_menu(
            'SELECT state, COUNT(id) FROM {' . self::ROW . '} WHERE jobid = :job GROUP BY state',
            ['job' => $jobid]
        );
    }

    /**
     * Write an operation event with no uploaded data or staff identifiers.
     */
    public static function audit(int $id, string $action): void {
        \tool_skillupscoursefactory\event\factory_operation::create([
            'context' => \context_system::instance(),
            'other' => ['operation' => $action, 'jobid' => $id],
        ])->trigger();
    }
}
