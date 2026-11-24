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

use moodle_exception;
use stdClass;

/**
 * Govern internal requests without approving or executing native Moodle operations.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow {
    /** @var string[] Distinct institutional request categories. */
    public const KINDS = ['access', 'export', 'delete', 'anonymize'];
    /** @var string[] Internal workflow statuses. */
    public const STATES = ['open', 'review', 'approved', 'rejected', 'closed'];
    /** @var string[] Local retention assessment outcomes. */
    public const RETENTION = ['pending', 'held', 'cleared'];

    /**
     * Choose an initial operational due date, independent of any legal deadline.
     *
     * @return int
     */
    public static function default_due(): int {
        return time() + max(1, min(365, (int)(get_config(access::C, 'duedays') ?: 30))) * DAYSECS;
    }

    /**
     * Register a standalone case or an exact native reference.
     *
     * @param stdClass $input Validated intake fields
     * @return int
     */
    public static function create(stdClass $input): int {
        global $USER, $DB;
        access::require('manage');
        $userid = (int)$input->userid;
        $ownerid = (int)($input->ownerid ?: $USER->id);
        $nativeid = (int)$input->nativeid;
        $kind = $input->kind;
        $scope = bridge::context((int)$input->contextid, $userid);
        $user = $userid > 0 ? \core_user::get_user($userid) : null;
        if (!$user || isguestuser($user) || !$scope || !in_array($kind, self::KINDS, true) || ($user->deleted && !$nativeid)) {
            self::fail('invalidcase');
        }
        self::validate_routing($ownerid, (int)$input->due);
        return store::atomic(static function () use ($DB, $USER, $input, $userid, $ownerid, $nativeid, $kind, $scope): int {
            $native = $nativeid ? bridge::read($nativeid) : null;
            if ($nativeid && (!$native || $native['userid'] !== $userid || $native['kind'] !== $kind)) {
                self::fail('nativeinvalid');
            }
            if ($nativeid && $DB->record_exists(store::CASES, ['nativeid' => $nativeid])) {
                self::fail('nativelinked');
            }
            $now = time();
            $id = $DB->insert_record(store::CASES, [
                'userid' => $userid, 'createdby' => $USER->id, 'ownerid' => $ownerid,
                'nativeid' => $nativeid ?: null, 'nativestatus' => $native['status'] ?? -1,
                'checkedat' => $native ? $now : 0, 'kind' => $kind, 'state' => 'open',
                'retention' => 'pending', 'contextid' => $scope->id, 'due' => $input->due,
                'revision' => 1, 'timecreated' => $now, 'timemodified' => $now,
            ]);
            store::audit('created', $id, targetid: $ownerid);
            return (int)$id;
        });
    }

    /**
     * Change the responsible officer and operational due date.
     *
     * @param int $id Case
     * @param int $revision Version seen by the operator
     * @param int $ownerid Responsible officer
     * @param int $due Operational due date
     */
    public static function route(int $id, int $revision, int $ownerid, int $due): void {
        access::require('manage');
        self::validate_routing($ownerid, $due);
        store::atomic(static function () use ($id, $revision, $ownerid, $due): void {
            $case = self::editable($id, $revision);
            self::write($case, ['ownerid' => $ownerid, 'due' => $due]);
            store::audit(
                'routed',
                $id,
                (string)$case->due,
                (string)$due,
                priorid: $case->ownerid,
                targetid: $ownerid,
            );
        });
    }

    /**
     * Record a policy assessment and reopen approvals when a hold is imposed.
     *
     * @param int $id Case
     * @param int $revision Version seen by operator
     * @param string $status Retention outcome
     * @param string $reason Internal decision note
     */
    public static function assess(int $id, int $revision, string $status, string $reason): void {
        access::require('decide');
        $reason = self::reason($reason);
        if (!in_array($status, self::RETENTION, true)) {
            self::fail('invaliddecision');
        }
        store::atomic(static function () use ($id, $revision, $status, $reason): void {
            $case = self::editable($id, $revision);
            if ($status === 'cleared' && self::protected($case)) {
                self::fail('protected');
            }
            $updates = ['retention' => $status];
            if (self::destructive($case->kind) && $case->state === 'approved' && $status !== 'cleared') {
                $updates['state'] = 'review';
                store::audit('transition', $id, 'approved', 'review');
            }
            self::write($case, $updates);
            store::audit('retention', $id, $case->retention, $status, $reason);
        });
    }

    /**
     * Apply an authorized internal disposition with a mandatory reason.
     *
     * @param int $id Case
     * @param int $revision Version seen by operator
     * @param string $state Requested state
     * @param string $reason Internal decision note
     */
    public static function transition(int $id, int $revision, string $state, string $reason): void {
        access::require(in_array($state, ['approved', 'rejected', 'closed'], true) ? 'decide' : 'manage');
        $reason = self::reason($reason);
        store::atomic(static function () use ($id, $revision, $state, $reason): void {
            $case = self::editable($id, $revision);
            if (!in_array($state, self::next($case->state), true)) {
                self::fail('invaliddecision');
            }
            if (self::destructive($case->kind) && ($state === 'approved' || ($state === 'closed' && $case->state === 'approved'))) {
                if (self::protected($case)) {
                    self::fail('protected');
                }
                if ($case->retention !== 'cleared') {
                    self::fail('retentionrequired');
                }
            }
            $updates = ['state' => $state];
            if ($state === 'closed' && $case->nativeid && $case->state === 'approved') {
                $native = bridge::read($case->nativeid);
                $terminal = $case->kind === 'delete' ? ['complete', 'deleted'] : ['complete', 'ready', 'expired'];
                if (
                    !$native || $native['userid'] !== (int)$case->userid || $native['kind'] !== $case->kind ||
                    !in_array(bridge::STATUSES[$native['status']] ?? '', $terminal, true)
                ) {
                    self::fail('nativepending');
                }
                $updates += ['nativestatus' => $native['status'], 'checkedat' => time()];
            }
            self::write($case, $updates);
            store::audit('transition', $id, $case->state, $state, $reason);
        });
    }

    /**
     * Determine possible state movements from the current workflow status.
     *
     * @param string $from Current state
     * @return array
     */
    public static function next(string $from): array {
        return match ($from) {
            'open' => ['review'],
            'review' => ['approved', 'rejected'],
            'approved', 'rejected' => ['review', 'closed'],
            default => [],
        };
    }

    /**
     * Test whether a case concerns removal or de-identification.
     *
     * @param string $kind Case type
     * @return bool
     */
    public static function destructive(string $kind): bool {
        return in_array($kind, ['delete', 'anonymize'], true);
    }

    /**
     * Block when context is absent or its configured effective purpose is protected.
     *
     * @param stdClass $case Case
     * @return bool
     */
    private static function protected(stdClass $case): bool {
        $context = bridge::context((int)$case->contextid, (int)$case->userid);
        return !$context || !empty(bridge::retention($context)['protected']);
    }

    /**
     * Apply a revision check and reject updates to closed or anonymized cases.
     *
     * @param int $id Case
     * @param int $revision Version
     * @return stdClass
     */
    private static function editable(int $id, int $revision): stdClass {
        $case = store::get($id);
        if ($case->revision != $revision || $case->archived || $case->state === 'closed') {
            self::fail('stale');
        }
        return $case;
    }

    /**
     * Persist changes and increment the form revision.
     *
     * @param stdClass $case Current row
     * @param array $updates Changes
     */
    private static function write(stdClass $case, array $updates): void {
        global $DB;
        $DB->update_record(store::CASES, $updates + [
            'id' => $case->id, 'revision' => $case->revision + 1, 'timemodified' => time(),
        ]);
    }

    /**
     * Validate an actor and a plausible administrative due date.
     *
     * @param int $owner Responsible owner
     * @param int $due Timestamp
     */
    private static function validate_routing(int $owner, int $due): void {
        if (!access::allowed($owner, 'manage') || $due <= 0 || $due > time() + 5 * YEARSECS) {
            self::fail('invalidroute');
        }
    }

    /**
     * Store only a bounded plain-text decision note.
     *
     * @param string $note Operator note
     * @return string
     */
    private static function reason(string $note): string {
        $value = trim(clean_param($note, PARAM_TEXT));
        if ($value === '' || \core_text::strlen($value) > 300) {
            self::fail('reasonrequired');
        }
        return $value;
    }

    /**
     * Return a localized workflow error.
     *
     * @param string $key Message ID
     */
    private static function fail(string $key): never {
        throw new moodle_exception($key, access::C);
    }
}
