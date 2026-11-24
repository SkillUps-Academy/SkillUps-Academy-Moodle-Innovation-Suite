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

namespace report_skillupssecurity;

use core\lock\lock_config;
use moodle_exception;

/**
 * Manage security evidence and audit operator actions.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Scan snapshots. */
    public const SCAN = 'report_skillupssecurity_scan';
    /** @var string Findings. */
    public const FINDING = 'report_skillupssecurity_finding';
    /** @var string Case history. */
    public const HISTORY = 'report_skillupssecurity_history';
    /** @var string Privilege snapshots. */
    public const PRIVILEGE = 'report_skillupssecurity_privilege';
    /** @var string Audit and native event metadata. */
    public const ACTIVITY = 'report_skillupssecurity_activity';
    /** @var string Pending notifications. */
    public const OUTBOX = 'report_skillupssecurity_outbox';
    /** @var array Allowed workflow states. */
    public const STATES = ['new', 'reviewing', 'accepted'];

    /**
     * Serialize changes to audit evidence and commit together.
     *
     * @param callable $operation Work
     * @return mixed
     */
    public static function atomic(callable $operation): mixed {
        global $DB;
        $lock = lock_config::get_lock_factory(access::C)->get_lock('evidence', 3);
        if (!$lock) {
            throw new moodle_exception('busy', access::C);
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
     * Queue at most one manual request per minute.
     *
     * @return bool Whether a new task was accepted
     */
    public static function queue(): bool {
        access::require('run');
        return self::atomic(static function (): bool {
            global $DB;
            if (
                $DB->record_exists_select(self::ACTIVITY, 'action = :action AND timecreated >= :recent', [
                'action' => 'queuescan', 'recent' => time() - MINSECS,
                ])
            ) {
                return false;
            }
            $queued = \core\task\manager::queue_adhoc_task(new task\manual(), true);
            if ($queued) {
                self::audit('queuescan');
            }
            return (bool)$queued;
        });
    }
    /**
     * Get the latest evidence snapshot.
     *
     * @return \stdClass|null
     */
    public static function latest(): ?\stdClass {
        global $DB;
        $rows = $DB->get_records(self::SCAN, null, 'id DESC', '*', 0, 1);
        return reset($rows) ?: null;
    }

    /**
     * Record a case review and its reason.
     *
     * @param int $id Finding
     * @param string $state New review status
     * @param string $note Plain-text reason
     */
    public static function review(int $id, string $state, string $note): void {
        global $USER;
        access::require('review');
        $note = trim(clean_param($note, PARAM_TEXT));
        if (!in_array($state, self::STATES, true) || $note === '' || \core_text::strlen($note) > 250) {
            throw new moodle_exception('invalidreview', access::C);
        }
        self::atomic(static function () use ($id, $state, $note, $USER): void {
            global $DB;
            $criteria = ['id' => $id] + (access::can('inventory') ? [] : ['userid' => 0]);
            if (!$DB->record_exists(self::FINDING, $criteria)) {
                throw new moodle_exception('invalidreview', access::C);
            }
            $DB->set_field(self::FINDING, 'reviewstate', $state, ['id' => $id]);
            self::transition($id, $state, (int)$USER->id, $note);
            self::audit('review', $id);
        });
    }

    /**
     * Add an evidence state transition.
     *
     * @param int $id Finding
     * @param string $action Transition
     * @param int $actor User or zero for automatic
     * @param string|null $note Plain-text reason
     */
    public static function transition(int $id, string $action, int $actor = 0, ?string $note = null): void {
        global $DB;
        $DB->insert_record(self::HISTORY, [
            'findingid' => $id, 'actorid' => $actor, 'action' => $action, 'note' => $note, 'timecreated' => time(),
        ]);
    }

    /**
     * Record an authorized report access or operation.
     *
     * @param string $operation Key from the report
     * @param int $reference Optional record
     */
    public static function audit(string $operation, int $reference = 0): void {
        global $DB, $USER;
        $context = \context_system::instance();
        $id = $DB->insert_record(self::ACTIVITY, [
            'actorid' => (int)$USER->id, 'action' => $operation, 'objectid' => $reference,
            'contextid' => $context->id, 'timecreated' => time(),
        ]);
        event\report_accessed::create([
            'context' => $context, 'objectid' => $id, 'other' => ['operation' => $operation],
        ])->trigger();
    }
}
