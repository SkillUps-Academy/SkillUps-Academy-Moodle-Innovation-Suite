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

namespace local_skillupsexam;

/**
 * Persistence, reservation locking and exam audit history.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Academic periods. */
    public const PERIOD = scope::C . '_period';
    /** @var string Institutional locations and rooms. */
    public const ROOM = scope::C . '_room';
    /** @var string Exam convocations. */
    public const EXAM = scope::C . '_exam';
    /** @var string Explicit participant registrations. */
    public const SEAT = scope::C . '_seat';
    /** @var string Attendance records. */
    public const MARK = scope::C . '_mark';
    /** @var string Supervisor incidents. */
    public const INCIDENT = scope::C . '_case';
    /** @var string Revision and operations log. */
    public const AUDIT = scope::C . '_audit';
    /** @var string Notification jobs. */
    public const QUEUE = scope::C . '_queue';

    /**
     * Resolve a plugin record.
     *
     * @param string $table Plugin table
     * @param int $id Record ID
     * @return \stdClass
     */
    public static function get(string $table, int $id): \stdClass {
        global $DB;
        $record = $DB->get_record($table, ['id' => $id]);
        if (!$record) {
            scope::fail();
        }
        return $record;
    }

    /**
     * Commit a change while holding the institutional reservation lock.
     *
     * @param callable $operation Transaction work
     * @return mixed
     */
    public static function write(callable $operation): mixed {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory(scope::C)->get_lock('reservations', 10);
        if (!$lock) {
            scope::fail('busy');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $result = $operation();
            } catch (\Throwable $failure) {
                $transaction->rollback($failure);
                throw $failure;
            }
            $transaction->allow_commit();
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove plugin course records in dependency order.
     *
     * @param int $courseid Moodle course
     */
    public static function remove_course(int $courseid): void {
        global $DB;
        self::write(static function () use ($DB, $courseid): void {
            $params = ['courseid' => $courseid];
            foreach ([self::QUEUE, self::AUDIT, self::INCIDENT, self::MARK, self::SEAT] as $table) {
                $DB->delete_records_select(
                    $table,
                    'examid IN (SELECT id FROM {' . self::EXAM . '} WHERE courseid = :courseid)',
                    $params,
                );
            }
            $DB->delete_records(self::EXAM, $params);
        });
    }

    /**
     * Record an authenticated operation.
     *
     * @param int $examid Exam ID
     * @param string $action Operation
     * @param string $reason Reason or empty string
     * @param int $subjectid Optional affected account
     * @param array|null $before Prior non-sensitive properties
     * @param array|null $after New non-sensitive properties
     */
    public static function audit(
        int $examid,
        string $action,
        string $reason = '',
        int $subjectid = 0,
        ?array $before = null,
        ?array $after = null,
    ): void {
        global $DB, $USER;
        $DB->insert_record(self::AUDIT, (object)[
            'examid' => $examid, 'actorid' => $USER->id, 'subjectid' => $subjectid,
            'action' => $action,
            'reason' => $reason,
            'beforedata' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'afterdata' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR), 'created' => time(),
        ]);
    }
}
