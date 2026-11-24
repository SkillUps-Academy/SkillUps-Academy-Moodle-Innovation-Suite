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

namespace local_skillupsintegration;

use core\lock\lock_config;

/**
 * Own integration state and serialize independent connector operations.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Connector configuration. */
    public const SRC = 'local_skillupsintegration_src';
    /** @var string Run state. */
    public const RUN = 'local_skillupsintegration_run';
    /** @var string Staged records. */
    public const ITEM = 'local_skillupsintegration_item';
    /** @var string External identity links. */
    public const LINK = 'local_skillupsintegration_link';
    /** @var string Operational audit. */
    public const AUDIT = 'local_skillupsintegration_audit';

    /**
     * Take a named Moodle lock and hold it through the operation.
     *
     * @param string $key Lock scope
     * @param callable $operation Operation
     * @return mixed
     */
    public static function locked(string $key, callable $operation): mixed {
        $lock = lock_config::get_lock_factory(access::C)->get_lock($key, 3);
        if (!$lock) {
            throw new problem('busy', true);
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    /**
     * Commit a short local operation atomically.
     *
     * @param callable $operation Operation
     * @return mixed
     */
    public static function transaction(callable $operation): mixed {
        global $DB;
        return self::locked('data', static function () use ($DB, $operation): mixed {
            $tx = $DB->start_delegated_transaction();
            try {
                $value = $operation();
            } catch (\Throwable $failure) {
                $tx->rollback($failure);
                throw $failure;
            }
            $tx->allow_commit();
            return $value;
        });
    }

    /**
     * Write a structured, credential-free audit event.
     *
     * @param string $action Event
     * @param int $sourceid Connector
     * @param int $runid Run
     * @param int $actorid Operator, or zero for cron
     */
    public static function audit(string $action, int $sourceid, int $runid = 0, int $actorid = 0): void {
        global $DB;
        $DB->insert_record(self::AUDIT, [
            'action' => $action, 'sourceid' => $sourceid, 'runid' => $runid,
            'actorid' => $actorid, 'timecreated' => time(),
        ]);
    }

    /**
     * Write operational state without changing an immutable run snapshot.
     *
     * @param int $id Run
     * @param array $changes Fields
     */
    public static function change(int $id, array $changes): void {
        global $DB;
        $DB->update_record(self::RUN, $changes + ['id' => $id, 'timemodified' => time()]);
    }
}
