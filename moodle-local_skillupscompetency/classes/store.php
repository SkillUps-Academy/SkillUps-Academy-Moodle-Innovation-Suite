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

namespace local_skillupscompetency;

/**
 * Persistence and synchronization for SkillUps-owned extensions.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Institutional programs. */
    public const PROG = access::C . '_prog';
    /** @var string Program course links. */
    public const MAP = access::C . '_map';
    /** @var string Academic terms. */
    public const TERM = access::C . '_term';
    /** @var string Expected competency levels. */
    public const GOAL = access::C . '_goal';
    /** @var string Achievement indicators. */
    public const IND = access::C . '_ind';
    /** @var string Assessments and decisions. */
    public const ASSESS = access::C . '_assess';

    /**
     * Load a plugin-owned record.
     *
     * @param string $table Plugin table name
     * @param int $id Record ID
     * @return \stdClass
     */
    public static function get(string $table, int $id): \stdClass {
        global $DB;
        $record = $DB->get_record($table, ['id' => $id]);
        if (!$record) {
            access::fail();
        }
        return $record;
    }

    /**
     * Run a delegated transaction under the plugin write lock.
     *
     * @param callable $operation Work to perform under the write lock
     * @return mixed
     */
    public static function write(callable $operation): mixed {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory(access::C)->get_lock('write', 10);
        if (!$lock) {
            access::fail('busy');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $result = $operation();
            } catch (\Throwable $error) {
                $transaction->rollback($error);
                throw $error;
            }
            $transaction->allow_commit();
            return $result;
        } finally {
            $lock->release();
        }
    }
}
