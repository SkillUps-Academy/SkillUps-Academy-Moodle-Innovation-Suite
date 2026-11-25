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

namespace tool_skillupsmigration;

/**
 * Provide private persistence, limits and native permission checks.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class support {
    /** @var string Plugin component. */
    public const C = 'tool_skillupsmigration';
    /** @var string Capability prefix. */
    public const CAP = 'tool/skillupsmigration:';
    /** @var string Job table. */
    public const JOB = self::C . '_job';
    /** @var string Migration row table. */
    public const ITEM = self::C . '_item';
    /** @var string Source artifact table. */
    public const ARTIFACT = self::C . '_artifact';
    /** @var string Audit table. */
    public const AUDIT = self::C . '_audit';
    /** @var int External protocol version. */
    public const PROTOCOL = 1;
    /** @var int Per-request raw chunk bound. */
    public const CHUNK = 262144;

    /**
     * Raise an error with a fixed, safe language key.
     *
     * @param string $key Stable error code
     */
    public static function fail(string $key): never {
        throw new \moodle_exception($key, self::C);
    }

    /**
     * Return a bounded maximum archive size.
     *
     * @return int Bytes
     */
    public static function limit(): int {
        global $CFG;
        return max(self::CHUNK, min(1073741824, (int)($CFG->tool_skillupsmigration_maxbytes ?? 134217728)));
    }

    /**
     * Return a durable private Moodle data directory.
     *
     * @return string Directory
     */
    public static function staging(): string {
        return make_upload_directory('tool_skillupsmigration');
    }

    /**
     * Fetch a plugin-owned record.
     *
     * @param string $table Plugin table
     * @param int $id Row ID
     * @return \stdClass
     */
    public static function get(string $table, int $id): \stdClass {
        global $DB;
        return $DB->get_record($table, ['id' => $id]) ?: self::fail('unavailable');
    }

    /**
     * Run a scoped operation under a native shared lock, or defer it.
     *
     * @param string $key Internal lock key
     * @param callable $work Work done under the lock
     * @param int $seconds Time to acquire the lock
     * @return mixed
     */
    public static function lock(string $key, callable $work, int $seconds = 10): mixed {
        $factory = \core\lock\lock_config::get_lock_factory(self::C);
        $lock = $factory->get_lock($key, $seconds);
        if (!$lock) {
            if ($seconds === 0) {
                return null;
            }
            self::fail('busy');
        }
        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * Execute privileged local writes in an atomic transaction.
     *
     * @param callable $work Database operations
     * @return mixed
     */
    public static function transaction(callable $work): mixed {
        global $DB;
        $tx = $DB->start_delegated_transaction();
        try {
            $result = $work();
        } catch (\Throwable $error) {
            $tx->rollback($error);
        }
        $tx->allow_commit();
        return $result;
    }

    /**
     * Check destination creation and restore permission in scope.
     *
     * @param int $categoryid Category
     * @param int $actor Authorizing Moodle account
     * @return \context_coursecat
     */
    public static function category(int $categoryid, int $actor): \context_coursecat {
        self::active($actor);
        $context = \context_coursecat::instance($categoryid, IGNORE_MISSING);
        if (
            !$context || !has_capability(self::CAP . 'manage', \context_system::instance(), $actor) ||
            !has_capability('moodle/course:create', $context, $actor) ||
            !has_capability('moodle/restore:restorecourse', $context, $actor)
        ) {
            self::fail('unavailable');
        }
        return $context;
    }

    /**
     * Require an active native account before queued actions.
     *
     * @param int $id User ID
     * @return \stdClass
     */
    public static function active(int $id): \stdClass {
        global $DB;
        $actor = $DB->get_record('user', ['id' => $id, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1]);
        if (!$actor || $actor->auth === 'nologin' || isguestuser($actor)) {
            self::fail('unavailable');
        }
        return $actor;
    }

    /**
     * Record the phase and identifiers without storing exceptions, URLs or credentials.
     *
     * @param int $jobid Migration
     * @param int $itemid Course row, or zero
     * @param int $actor Acting account or zero for cron
     * @param string $action Audit event type
     */
    public static function audit(int $jobid, int $itemid, int $actor, string $action): void {
        global $DB;
        $DB->insert_record(self::AUDIT, (object)[
            'jobid' => $jobid, 'itemid' => $itemid, 'actor' => $actor, 'action' => $action, 'created' => time(),
        ]);
    }

    /**
     * Encode deterministic metadata without HTML or credential material.
     *
     * @param array $value Structured values
     * @return string
     */
    public static function encode(array $value): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Read structured plugin metadata.
     *
     * @param string|null $value Persisted JSON
     * @return array
     */
    public static function decode(?string $value): array {
        return $value ? json_decode($value, true, 24, JSON_THROW_ON_ERROR) : [];
    }
}
