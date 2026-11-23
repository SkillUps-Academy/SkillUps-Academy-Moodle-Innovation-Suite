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

namespace local_skillupsgamification;

use core\lock\lock_config;
use moodle_exception;
use stdClass;

/**
 * Course locking, scoped records and persistence primitives.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Course settings. */
    public const COURSE = 'local_skillupsgamification_course';

    /** @var string Participants. */
    public const PLAYER = 'local_skillupsgamification_player';

    /** @var string Award rules. */
    public const RULE = 'local_skillupsgamification_rule';

    /** @var string Award ledger. */
    public const LEDGER = 'local_skillupsgamification_ledger';

    /** @var string Goals and rewards. */
    public const GOAL = 'local_skillupsgamification_goal';

    /** @var string Achievements and redemptions. */
    public const GRANT = 'local_skillupsgamification_grant';

    /** @var string Configuration audit. */
    public const AUDIT = 'local_skillupsgamification_audit';

    /**
     * Fetch explicit course configuration, or disabled defaults.
     *
     * @param int $courseid Course identifier
     * @return stdClass
     */
    public static function configuration(int $courseid): stdClass {
        global $DB;
        return $DB->get_record(self::COURSE, ['courseid' => $courseid]) ?: (object)[
            'courseid' => $courseid, 'enabled' => 0, 'rankingenabled' => 0, 'pathenabled' => 0,
            'xplevel' => 100, 'dailycap' => 0, 'groupingid' => 0, 'timeenabled' => 0,
        ];
    }

    /**
     * Return current preferences, with private-by-default fallbacks.
     *
     * @param int $courseid Course ID
     * @param int $userid Participant
     * @return stdClass
     */
    public static function player(int $courseid, int $userid): stdClass {
        global $DB;
        return $DB->get_record(self::PLAYER, ['courseid' => $courseid, 'userid' => $userid]) ?: (object)[
            'courseid' => $courseid, 'userid' => $userid, 'consent' => 0, 'excluded' => 0, 'alias' => '',
        ];
    }

    /**
     * Insert or save a current course record.
     *
     * @param string $table Plugin table
     * @param stdClass $record Data
     * @return int
     */
    public static function save(string $table, stdClass $record): int {
        global $DB;
        if (!empty($record->id)) {
            $DB->update_record($table, $record);
            return (int)$record->id;
        }
        return (int)$DB->insert_record($table, $record);
    }

    /**
     * Execute an atomic update under the course lock.
     *
     * @param int $courseid Course scope
     * @param callable $operation Unit of work
     * @return mixed
     */
    public static function change(int $courseid, callable $operation): mixed {
        global $DB;
        $lock = lock_config::get_lock_factory(access::C)->get_lock('course:' . $courseid, 5);
        if (!$lock) {
            throw new moodle_exception('busy', access::C);
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
     * Write a change event without extra personal details.
     *
     * @param int $courseid Course ID
     * @param string $action Operation
     * @param int $targetid Plugin record ID
     */
    public static function audit(int $courseid, string $action, int $targetid = 0): void {
        global $DB, $USER;
        $id = $DB->insert_record(self::AUDIT, [
            'courseid' => $courseid, 'userid' => $USER->id, 'action' => $action, 'targetid' => $targetid, 'timecreated' => time(),
        ]);
        event\operation_recorded::create([
            'context' => \context_course::instance($courseid),
            'objectid' => $id,
            'other' => ['action' => $action],
        ])->trigger();
    }

    /**
     * Drop a deleted course's local records.
     *
     * @param int $courseid Deleted course
     */
    public static function delete_course(int $courseid): void {
        global $DB;
        self::change($courseid, static function () use ($DB, $courseid): void {
            foreach ([self::PLAYER, self::LEDGER, self::GRANT, self::AUDIT, self::RULE, self::GOAL, self::COURSE] as $table) {
                $DB->delete_records($table, ['courseid' => $courseid]);
            }
        });
    }
}
