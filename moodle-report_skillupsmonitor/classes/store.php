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

namespace report_skillupsmonitor;

/**
 * Atomic monitoring samples, debounced incidents, and a deduplicated notification outbox.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** Observation storage. */
    public const SAMPLE = 'report_skillupsmonitor_sample';
    /** Per-check counters and open incident pointer. */
    public const STATE = 'report_skillupsmonitor_checkstate';
    /** Incident timeline. */
    public const INCIDENT = 'report_skillupsmonitor_incident';
    /** Authorized notification outbox. */
    public const NOTICE = 'report_skillupsmonitor_notice';

    /**
     * Fetch one indexed sample, without running any checks.
     */
    public static function latest(): ?\stdClass {
        global $DB;
        $records = $DB->get_records(self::SAMPLE, [], 'slot DESC', '*', 0, 1);
        return reset($records) ?: null;
    }

    /**
     * Coordinator used by cron, with protection against duplicate or concurrent samples.
     */
    public static function sample(): void {
        $lock = util::lock('sampling');
        if (!$lock) {
            return;
        }
        try {
            $now = time();
            $previous = self::latest();
            if ($previous && $previous->slot >= self::slot($now)) {
                return;
            }
            $policy = util::policy();
            $payload = $previous ? self::payload($previous) : null;
            self::record($now, collector::capture($now, $policy, $payload), $policy, $previous);
        } finally {
            $lock->release();
        }
    }

    /**
     * UTC observation bucket.
     */
    public static function slot(int $time): int {
        return intdiv($time, util::SLOT) * util::SLOT;
    }

    /**
     * Decode previously published, non-user-authored snapshot values.
     */
    public static function payload(\stdClass $row): array {
        return json_decode($row->payload, true, 15, JSON_THROW_ON_ERROR);
    }

    /**
     * Publish one sample and all associated transitions under the collector lock.
     */
    public static function record(int $now, array $data, array $policy, ?\stdClass $previous): void {
        global $DB;
        $slot = self::slot($now);
        $gap = $previous && $slot > $previous->slot + util::SLOT;
        $hash = hash('sha256', util::json($policy));
        $tx = $DB->start_delegated_transaction();
        $DB->insert_record(self::SAMPLE, [
            'slot' => $slot, 'captured' => $now, 'readiness' => $data['readiness'],
            'latencyus' => $data['latencyus'], 'payload' => util::json($data),
        ]);
        $recipients = array_values(array_filter(util::recipients(), [util::class, 'valid_recipient']));
        if ($gap && $now - $previous->captured >= $policy['stale']) {
            $id = $DB->insert_record(self::INCIDENT, [
                'checkkey' => 'coverage', 'opened' => $previous->slot + util::SLOT,
                'detected' => $now, 'closed' => $now, 'severity' => 'warning',
            ]);
            self::enqueue($id, 'coverage', $now, util::alerted('coverage') ? $recipients : []);
            util::audit('coverage', $id);
        }
        foreach (collector::CHECKS as $key) {
            self::update_check(
                $key,
                $data['checks'][$key] ?? 'unknown',
                $now,
                $policy,
                $hash,
                (bool)$gap,
                util::alerted($key) ? $recipients : []
            );
        }
        $tx->allow_commit();
    }

    /**
     * Update or extend an incident, preserving unknown intervals and first observed failure.
     */
    private static function update_check(
        string $key,
        string $status,
        int $now,
        array $policy,
        string $hash,
        bool $gap,
        array $recipients
    ): void {
        global $DB;
        $state = $DB->get_record(self::STATE, ['checkkey' => $key]);
        if (!$state) {
            $state = (object)['checkkey' => $key, 'failed' => 0, 'healthy' => 0, 'incidentid' => 0,
                'firstfailed' => 0, 'policyhash' => $hash];
            $state->id = $DB->insert_record(self::STATE, $state);
        }
        if ($gap || $state->policyhash !== $hash) {
            $state->failed = 0;
            $state->healthy = 0;
            $state->firstfailed = 0;
        }
        $state->policyhash = $hash;
        if (in_array($status, ['warning', 'error'], true)) {
            $state->firstfailed = $state->firstfailed ?: $now;
            $state->failed = min(12, $state->failed + 1);
            $state->healthy = 0;
            if (!$state->incidentid && $state->failed >= $policy['failures']) {
                $state->incidentid = $DB->insert_record(self::INCIDENT, [
                    'checkkey' => $key, 'opened' => $state->firstfailed, 'detected' => $now, 'severity' => $status,
                ]);
                self::enqueue($state->incidentid, 'opened', $now, $recipients);
                util::audit('opened', $state->incidentid);
            } else if ($state->incidentid && $status === 'error') {
                $DB->set_field(self::INCIDENT, 'severity', 'error', ['id' => $state->incidentid]);
            }
        } else {
            $state->failed = 0;
            $state->firstfailed = 0;
            $state->healthy = $status === 'ok' ? min(12, $state->healthy + 1) : 0;
            if ($state->incidentid && $state->healthy >= $policy['recoveries']) {
                $DB->set_field(self::INCIDENT, 'closed', $now, ['id' => $state->incidentid]);
                self::enqueue($state->incidentid, 'recovered', $now, $recipients);
                util::audit('recovered', $state->incidentid);
                $state->incidentid = 0;
            }
        }
        $DB->update_record(self::STATE, $state);
    }

    /**
     * Store one delivery per authorized account and incident transition.
     */
    private static function enqueue(int $incident, string $phase, int $now, array $recipients): void {
        global $DB;
        foreach ($recipients as $userid) {
            $DB->insert_record(self::NOTICE, ['incidentid' => $incident, 'userid' => $userid, 'phase' => $phase,
                'state' => 'pending', 'timecreated' => $now, 'timemodified' => $now]);
        }
    }

    /**
     * Record a one-time human acknowledgement without changing an incident's health state.
     */
    public static function acknowledge(int $id): void {
        global $DB, $USER;
        require_capability(util::CAP . ':manage', \context_system::instance());
        $lock = util::lock('sampling', 5);
        if (!$lock) {
            throw new \moodle_exception('busy', util::C);
        }
        try {
            $incident = $DB->get_record(self::INCIDENT, ['id' => $id], '*', MUST_EXIST);
            if (!$incident->timeack) {
                $DB->update_record(self::INCIDENT, ['id' => $id, 'timeack' => time(), 'ackuserid' => $USER->id]);
                util::audit('acknowledged', $id);
            }
        } finally {
            $lock->release();
        }
    }
}
