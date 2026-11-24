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

namespace local_skillupsscheduler;

/**
 * Persist Scheduler changes in serialized transactions.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Campus records. */
    public const SITE = scope::C . '_site';
    /** @var string Periods and cycles. */
    public const CYCLE = scope::C . '_cycle';
    /** @var string Rooms. */
    public const ROOM = scope::C . '_room';
    /** @var string Weekly time blocks. */
    public const BLOCK = scope::C . '_block';
    /** @var string Campus access grants. */
    public const GRANT = scope::C . '_grant';
    /** @var string Session reservations. */
    public const SLOT = scope::C . '_slot';
    /** @var string Revision history. */
    public const AUDIT = scope::C . '_audit';
    /** @var string Pending notices. */
    public const QUEUE = scope::C . '_queue';

    /**
     * Run a serialized Moodle transaction.
     *
     * @param callable $operation Change or transaction operation
     * @return mixed
     */
    public static function atomic(callable $operation): mixed {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory(scope::C)->get_lock('schedule', 10);
        if (!$lock) {
            scope::fail('busy');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $value = $operation();
            } catch (\Throwable $error) {
                $transaction->rollback($error);
                throw $error;
            }
            $transaction->allow_commit();
            return $value;
        } finally {
            $lock->release();
        }
    }

    /**
     * Load an existing Scheduler record.
     *
     * @param string $table Table
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
     * Store the revision and trigger an event in Moodle.
     *
     * @param \stdClass $slot Session data
     * @param \stdClass|null $previous Previous session if present
     * @param string $operation Change or transaction operation
     * @param string $reason Reason entered by the operator
     */
    public static function audit(\stdClass $slot, ?\stdClass $previous, string $operation, string $reason): void {
        global $DB, $USER;
        $snapshot = static fn($record) => $record ? json_encode(array_intersect_key((array)$record, array_flip([
            'cycleid', 'roomid', 'virtualid', 'blockid', 'title', 'mode', 'starts', 'ends', 'status', 'revision',
        ])), JSON_THROW_ON_ERROR) : null;
        $DB->insert_record(self::AUDIT, (object)[
            'siteid' => $slot->siteid, 'courseid' => $slot->courseid, 'slotid' => $slot->id,
            'actorid' => $USER->id, 'oldteacher' => $previous->teacherid ?? 0, 'newteacher' => $slot->teacherid,
            'operation' => $operation, 'reason' => $reason, 'previousdata' => $snapshot($previous),
            'nextdata' => $snapshot($slot), 'created' => time(),
        ]);
        $event = \local_skillupsscheduler\event\schedule_changed::create([
            'objectid' => $slot->id, 'context' => \context_course::instance($slot->courseid),
            'other' => ['operation' => $operation, 'revision' => (int)$slot->revision],
        ]);
        $event->trigger();
    }
}
