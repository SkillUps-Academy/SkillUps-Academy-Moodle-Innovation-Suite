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

namespace local_skillupshelpdesk;

use core\lock\lock_config;
use moodle_exception;

/**
 * Shared persistence names, locks and append-only audit entry points.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class storage {
    /** @var string Catalog table. */
    public const CATALOG = 'local_skillupshelpdesk_catalog';

    /** @var string Ticket table. */
    public const TICKET = 'local_skillupshelpdesk_ticket';

    /** @var string Conversation table. */
    public const POST = 'local_skillupshelpdesk_post';

    /** @var string Activity ledger. */
    public const AUDIT = 'local_skillupshelpdesk_audit';

    /** @var string Retryable notification queue. */
    public const OUTBOX = 'local_skillupshelpdesk_outbox';

    /**
     * Serialize a critical section across Moodle workers.
     *
     * @param string $key Resource
     * @param callable $operation Critical section
     * @return mixed
     */
    public static function locked(string $key, callable $operation): mixed {
        $lock = lock_config::get_lock_factory(access::COMPONENT)->get_lock($key, 3);
        if (!$lock) {
            throw new moodle_exception('busy', access::COMPONENT);
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    /**
     * Commit structured writes together and roll back on an error.
     *
     * @param callable $operation Atomic operation
     * @return mixed
     */
    public static function transaction(callable $operation): mixed {
        global $DB;
        $tx = $DB->start_delegated_transaction();
        try {
            $result = $operation();
        } catch (\Throwable $failure) {
            $tx->rollback($failure);
            throw $failure;
        }
        $tx->allow_commit();
        return $result;
    }

    /**
     * Record a support or configuration event without recording message contents.
     *
     * @param int $ticketid Ticket ID, or zero for configuration
     * @param string $action Operation name
     * @param array $details Structured integer changes
     * @param int|null $userid Explicit actor, or current user
     */
    public static function audit(int $ticketid, string $action, array $details = [], ?int $userid = null): void {
        global $DB, $USER;
        [$from, $to] = $details['assigneeid'] ?? [0, 0];
        unset($details['assigneeid']);
        $entryid = $DB->insert_record(self::AUDIT, [
            'formerassignee' => $from,
            'newassignee' => $to,
            'ticketid' => $ticketid,
            'userid' => $userid ?? $USER->id,
            'action' => $action,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'timecreated' => time(),
        ]);
        event\operation_recorded::create([
            'context' => \context_system::instance(),
            'objectid' => $entryid,
            'userid' => $userid ?? $USER->id,
            'other' => ['operation' => $action],
        ])->trigger();
    }

    /**
     * Remove one ticket and its dependent plugin records and attachment areas.
     *
     * @param int $id Ticket ID
     */
    public static function delete(int $id): void {
        global $DB;
        self::transaction(static function () use ($DB, $id): void {
            $context = \context_system::instance();
            $criteria = ['ticketid' => $id];
            $posts = $DB->get_recordset(self::POST, $criteria, '', 'id');
            try {
                foreach ($posts as $post) {
                    get_file_storage()->delete_area_files($context->id, access::COMPONENT, 'attachment', $post->id);
                }
            } finally {
                $posts->close();
            }
            foreach ([self::OUTBOX, self::POST, self::AUDIT] as $table) {
                $DB->delete_records($table, $criteria);
            }
            $DB->delete_records(self::TICKET, ['id' => $id]);
        });
    }
}
