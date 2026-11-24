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

namespace report_skillupsaccessibility;

/**
 * Persist audit data under a course lock.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Job records. */
    public const JOB = scope::C . '_job';
    /** @var string Source catalog. */
    public const ASSET = scope::C . '_asset';
    /** @var string Cached source results. */
    public const REV = scope::C . '_rev';
    /** @var string Job-to-source snapshot links. */
    public const HIT = scope::C . '_hit';
    /** @var string Current workflow findings. */
    public const FINDING = scope::C . '_finding';
    /** @var string Reviewer decisions and evidence links. */
    public const REVIEW = scope::C . '_review';

    /**
     * Resolve one plugin record without exposing a database error.
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
     * Execute work under the current course lock.
     *
     * @param int $courseid Moodle course
     * @param callable $operation Work to perform
     * @return mixed
     */
    public static function lock(int $courseid, callable $operation): mixed {
        $factory = \core\lock\lock_config::get_lock_factory(scope::C);
        $lock = $factory->get_lock('course:' . $courseid, 8);
        if (!$lock) {
            scope::fail('busy');
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    /**
     * Run a delegated database transaction.
     *
     * @param callable $operation Work to perform
     * @return mixed
     */
    public static function transaction(callable $operation): mixed {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        try {
            $result = $operation();
        } catch (\Throwable $error) {
            $transaction->rollback($error);
            throw $error;
        }
        $transaction->allow_commit();
        return $result;
    }
}
