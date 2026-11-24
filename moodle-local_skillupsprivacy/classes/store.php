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

namespace local_skillupsprivacy;

use core\lock\lock_config;

/**
 * Serialize internal case state and maintain an append-only audit trail.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Request workflow table. */
    public const CASES = 'local_skillupsprivacy_case';
    /** @var string Audit history. */
    public const AUDIT = 'local_skillupsprivacy_audit';
    /** @var string One record per reminder claim. */
    public const NOTICE = 'local_skillupsprivacy_notice';

    /**
     * Commit a serialized local change.
     *
     * @param callable $operation Protected operation
     * @return mixed
     */
    public static function atomic(callable $operation): mixed {
        global $DB;
        $lock = lock_config::get_lock_factory(access::C)->get_lock('register', 5);
        if (!$lock) {
            throw new \moodle_exception('busy', access::C);
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
     * Read a tracked case.
     *
     * @param int $id Internal case
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::CASES, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Record native audit evidence without disclosing private notes to log stores.
     *
     * @param string $operation Operation ID
     * @param int $caseid Related case
     * @param string|null $previous Previous value
     * @param string|null $result New value
     * @param string|null $note Internal reason
     * @param bool $automatic Attribute automated changes to the system
     * @param int $priorid Previous responsible officer
     * @param int $targetid New responsible officer
     */
    public static function audit(
        string $operation,
        int $caseid = 0,
        ?string $previous = null,
        ?string $result = null,
        ?string $note = null,
        bool $automatic = false,
        int $priorid = 0,
        int $targetid = 0,
    ): void {
        global $DB, $USER;
        $context = \context_system::instance();
        $id = $DB->insert_record(self::AUDIT, [
            'caseid' => $caseid, 'actorid' => $automatic ? 0 : (int)$USER->id,
            'priorid' => $priorid, 'targetid' => $targetid,
            'operation' => $operation, 'previous' => $previous, 'result' => $result, 'note' => $note, 'timecreated' => time(),
        ]);
        event\case_activity::create([
            'context' => $context, 'objectid' => $id, 'other' => ['operation' => $operation],
        ])->trigger();
    }
}
