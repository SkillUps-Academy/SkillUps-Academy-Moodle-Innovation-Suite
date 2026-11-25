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

namespace local_skillupsrepository;

/**
 * Persist the library and audit category-scoped mutations.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Asset table. */
    public const ASSET = access::COMPONENT . '_asset';
    /** @var string Immutable revision metadata and moderation state. */
    public const REVISION = access::COMPONENT . '_revision';
    /** @var string Native module reuse history. */
    public const USAGE = access::COMPONENT . '_usage';
    /** @var string Operations log. */
    public const AUDIT = access::COMPONENT . '_audit';

    /**
     * Return a plugin record or raise a scoped error.
     *
     * @param string $table Own table name
     * @param int $id Record ID
     * @return \stdClass
     */
    public static function get(string $table, int $id): \stdClass {
        global $DB;
        return $DB->get_record($table, ['id' => $id]) ?: access::fail();
    }

    /**
     * Execute a transaction under a lock shared by category deletion and mutation.
     *
     * @param int $categoryid Owning category
     * @param callable $callback Transaction body
     * @return mixed
     */
    public static function write(int $categoryid, callable $callback): mixed {
        global $DB;
        $factory = \core\lock\lock_config::get_lock_factory(access::COMPONENT);
        $lock = $factory->get_lock('category:' . $categoryid, 10);
        if (!$lock) {
            access::fail('busy');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $result = $callback();
            } catch (\Throwable $failure) {
                $transaction->rollback($failure);
            }
            $transaction->allow_commit();
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Record an actor and state transition without copying metadata or private links.
     *
     * @param int $assetid Asset ID
     * @param int $versionid Revision ID
     * @param string $action Operation key
     */
    public static function audit(int $assetid, int $versionid, string $action): void {
        global $DB, $USER;
        $DB->insert_record(self::AUDIT, (object)[
            'assetid' => $assetid, 'revisionid' => $versionid, 'action' => $action, 'userid' => $USER->id, 'created' => time(),
        ]);
    }
}
