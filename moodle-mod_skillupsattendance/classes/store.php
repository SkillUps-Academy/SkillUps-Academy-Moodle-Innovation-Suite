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

namespace mod_skillupsattendance;

use core\lock\lock_config;

/**
 * Serialize activity mutations and record structured attendance evidence.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Activity policy table. */
    public const ACTIVITY = 'skillupsattendance';
    /** @var string Schedule table. */
    public const SESSION = 'skillupsattendance_sess';
    /** @var string Attendance result table. */
    public const MARK = 'skillupsattendance_mark';
    /** @var string Justification and evidence table. */
    public const JUST = 'skillupsattendance_just';
    /** @var string Activity history. */
    public const AUDIT = 'skillupsattendance_audit';
    /** @var string Credential retry counters. */
    public const FAIL = 'skillupsattendance_fail';
    /** @var string Warning episode claims. */
    public const ALERT = 'skillupsattendance_alert';

    /**
     * Take a Moodle lock and commit a local mutation atomically.
     *
     * @param int $id Attendance instance
     * @param callable $operation Callback
     * @return mixed
     */
    public static function atomic(int $id, callable $operation): mixed {
        global $DB;
        $lock = lock_config::get_lock_factory(activity::C)->get_lock("attendance:$id", 5);
        if (!$lock) {
            activity::fail('busy');
        }
        try {
            $tx = $DB->start_delegated_transaction();
            try {
                $result = $operation();
            } catch (\Throwable $failure) {
                $tx->rollback($failure);
                throw $failure;
            }
            $tx->allow_commit();
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Retrieve only a session that belongs to the provided module.
     *
     * @param activity $activity Activity
     * @param int $id Session
     * @return \stdClass
     */
    public static function session(activity $activity, int $id): \stdClass {
        global $DB;
        $record = $DB->get_record(self::SESSION, ['id' => $id, 'attendanceid' => $activity->record->id]);
        if (!$record) {
            activity::fail('unavailable');
        }
        return $record;
    }

    /**
     * Log limited operation metadata and trigger the Moodle event.
     *
     * @param activity $activity Module scope
     * @param string $operation Operation
     * @param int $sessionid Related session
     * @param int $userid Related student
     * @param string|null $before Previous value
     * @param string|null $after Resulting value
     * @param bool $automatic Whether the system performed the action
     */
    public static function audit(
        activity $activity,
        string $operation,
        int $sessionid = 0,
        int $userid = 0,
        ?string $before = null,
        ?string $after = null,
        bool $automatic = false,
    ): void {
        global $DB, $USER;
        $actor = $automatic ? 0 : (int)$USER->id;
        if (in_array($operation, ['marked', 'finalized', 'cancelled'], true)) {
            $DB->set_field(self::ACTIVITY, 'alertdirty', 1, ['id' => $activity->record->id]);
        }
        $id = $DB->insert_record(self::AUDIT, [
            'attendanceid' => $activity->record->id, 'sessionid' => $sessionid, 'userid' => $userid,
            'actorid' => $actor, 'operation' => $operation, 'beforestate' => $before,
            'afterstate' => $after, 'timecreated' => time(),
        ]);
        event\attendance_change::create([
            'context' => $activity->context, 'objectid' => $id, 'userid' => $actor, 'other' => ['operation' => $operation],
        ])->trigger();
    }
}
