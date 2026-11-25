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

namespace local_skillupscommunication;

/**
 * Provide bounded DML, campaign locks and privacy-safe auditing.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Campaign state and editorial content. */
    public const CAMP = access::C . '_camp';
    /** @var string Immutable audience rows and processing state. */
    public const REC = access::C . '_recipient';
    /** @var string Audit events. */
    public const LOG = access::C . '_log';
    /** @var string Bounded template catalogue. */
    public const TEMPLATE = access::C . '_template';
    /** @var string Named recipient selectors. */
    public const SEG = access::C . '_segment';
    /** @var string Custom selector members. */
    public const MEMBER = access::C . '_member';

    /**
     * Read a plugin record with a scoped generic error.
     *
     * @param string $table Owned table
     * @param int $id Record ID
     * @return \stdClass
     */
    public static function get(string $table, int $id): \stdClass {
        global $DB;
        return $DB->get_record($table, ['id' => $id]) ?: access::fail();
    }

    /**
     * Run an operation while holding a distributed Moodle lock.
     *
     * @param string $key Resource key
     * @param callable $callback Work
     * @return mixed
     */
    public static function locked(string $key, callable $callback): mixed {
        $factory = \core\lock\lock_config::get_lock_factory(access::C);
        $lock = $factory->get_lock($key, 10);
        if (!$lock) {
            access::fail('busy');
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Commit a database state mutation.
     *
     * @param callable $callback Database work with no external handoff
     * @return mixed
     */
    public static function transaction(callable $callback): mixed {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        try {
            $result = $callback();
        } catch (\Throwable $error) {
            $transaction->rollback($error);
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Record a public operation type without copying message content or credentials.
     *
     * @param \stdClass $campaign Campaign
     * @param string $action Audit action
     * @param int $userid Actor, 0 for automation
     */
    public static function audit(\stdClass $campaign, string $action, int $userid): void {
        global $DB;
        $DB->insert_record(self::LOG, (object)[
            'campaignid' => $campaign->id, 'userid' => $userid, 'action' => $action, 'created' => time(),
        ]);
        event\campaign_updated::create([
            'context' => access::scope((int)$campaign->scopeid), 'objectid' => $campaign->id, 'userid' => $userid,
            'other' => ['action' => $action],
        ])->trigger();
    }

    /**
     * Read the site bound for a single campaign.
     *
     * @return int
     */
    public static function limit(): int {
        return max(1, min(50000, (int)(get_config(access::C, 'maxrecipients') ?: 2000)));
    }

    /**
     * Read the volume of a single Moodle Adhoc execution.
     *
     * @return int
     */
    public static function batch(): int {
        return max(1, min(100, (int)(get_config(access::C, 'batchsize') ?: 40)));
    }
}
