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
 * Process small recipient batches while protecting against duplicate handoffs.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dispatch {
    /**
     * Schedule a campaign wave through Moodle's Adhoc Task API.
     *
     * @param \stdClass $campaign Campaign state
     * @param int $when First eligible timestamp
     */
    public static function queue(\stdClass $campaign, int $when): void {
        $task = new task\send_batch();
        $task->set_custom_data(['id' => (int)$campaign->id, 'wave' => (int)$campaign->wave]);
        $task->set_next_run_time(max(time(), $when));
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Determine the next unfinished recipient schedule.
     *
     * @param int $id Campaign ID
     * @return int|null Unix timestamp or null when no pending work remains
     */
    public static function next(int $id): ?int {
        global $DB;
        $filter = ['campaignid' => $id, 'state' => 'pending'];
        if (!$DB->record_exists(store::REC, $filter)) {
            return null;
        }
        return (int)$DB->get_field_sql(
            'SELECT MIN(nexttry) FROM {' . store::REC . '} WHERE campaignid = :campaignid AND state = :state',
            $filter
        );
    }

    /**
     * Run one bounded wave. External handoff always happens outside a DB transaction.
     *
     * @param int $id Campaign ID
     * @param int $wave Expected wave counter
     * @param callable|null $sender Override for synthetic test transports
     */
    public static function run(int $id, int $wave, ?callable $sender = null): void {
        global $DB;
        $sender = $sender ?? [transport::class, 'send'];
        $next = store::locked('campaign:' . $id, static function () use ($DB, $id, $wave, $sender): ?array {
            $job = $DB->get_record(store::CAMP, ['id' => $id]);
            if (!$job || $job->wave != $wave || !in_array($job->state, ['queued', 'running'], true) || $job->sendat > time()) {
                return null;
            }
            $sending = ['campaignid' => $id, 'state' => 'sending'];
            $DB->set_field(store::REC, 'failure', 'interrupted', $sending);
            $DB->set_field(store::REC, 'state', 'uncertain', $sending);
            $scope = access::scope((int)$job->scopeid);
            $approved = self::actor($scope, (int)$job->creator, 'compose') && self::actor($scope, (int)$job->reviewer, 'approve');
            if (!$approved) {
                self::stop_pending($id);
                self::finish($job, 'scopechanged');
                return null;
            }
            $job->state = 'running';
            $DB->update_record(store::CAMP, $job);
            $batch = $DB->get_records_select(
                store::REC,
                'campaignid = :id AND state = :state AND nexttry <= :ready',
                ['id' => $id, 'state' => 'pending', 'ready' => time()],
                'nexttry, id',
                '*',
                0,
                store::batch()
            );
            if ($batch) {
                $ids = array_map('intval', array_column($batch, 'userid'));
                try {
                    $filters = campaign::filters($job);
                    $bycreator = audience::ids($scope, $filters, (int)$job->creator, $ids);
                    $byapprover = audience::ids($scope, $filters, (int)$job->reviewer, $ids);
                    $eligible = array_fill_keys(array_intersect($bycreator, $byapprover), true);
                } catch (\moodle_exception $error) {
                    self::stop_pending($id);
                    self::finish($job, 'scopechanged');
                    return null;
                }
                foreach ($batch as $row) {
                    $target = isset($eligible[$row->userid]) ? $DB->get_record('user', [
                        'id' => $row->userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1,
                    ]) : null;
                    if (!$target || $target->auth === 'nologin') {
                        $row->state = 'skipped';
                        $row->failure = 'scopechanged';
                        $row->updated = time();
                        $DB->update_record(store::REC, $row);
                        continue;
                    }
                    self::handoff($job, $row, $target, $sender);
                }
            }
            $when = self::next($id);
            if ($when === null) {
                self::finish($job, 'finished');
                return null;
            }
            $job->wave++;
            $job->updated = time();
            $DB->update_record(store::CAMP, $job);
            return [$job, $when];
        });
        if ($next) {
            self::queue(...$next);
        }
    }

    /**
     * Check an approving or authoring account still holds authority.
     *
     * @param \context $scope Campaign scope
     * @param int $userid Original actor
     * @param string $action Required capability
     * @return bool
     */
    private static function actor(\context $scope, int $userid, string $action): bool {
        global $DB;
        $actor = $userid ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1]) : null;
        return $actor && $actor->auth !== 'nologin' && access::can($scope, $action, $userid);
    }

    /**
     * Mark waiting targets as skipped after an authorization change.
     *
     * @param int $id Campaign ID
     */
    private static function stop_pending(int $id): void {
        global $DB;
        $conditions = ['campaignid' => $id, 'state' => 'pending'];
        $DB->set_field(store::REC, 'failure', 'scopechanged', $conditions);
        $DB->set_field(store::REC, 'state', 'skipped', $conditions);
    }

    /**
     * Claim an attempt persistently before handing off its message.
     *
     * @param \stdClass $job Active campaign
     * @param \stdClass $row Unique recipient
     * @param \stdClass $target Verified Moodle account
     * @param callable $sender Transport
     */
    private static function handoff(\stdClass $job, \stdClass $row, \stdClass $target, callable $sender): void {
        global $DB;
        $row->state = 'sending';
        $row->attempts++;
        $row->updated = time();
        $DB->update_record(store::REC, $row);
        try {
            [$state, $messageid, $reason] = $sender($job, $target);
        } catch (\Throwable $error) {
            [$state, $messageid, $reason] = ['uncertain', 0, 'handoffunknown'];
        }
        if ($state === 'retry') {
            $state = $row->attempts < 3 ? 'pending' : 'failed';
            $row->nexttry = time() + 60 * (2 ** $row->attempts);
        }
        $row->state = $state;
        $row->messageid = $messageid;
        $row->failure = $reason;
        $row->updated = time();
        $DB->update_record(store::REC, $row);
    }

    /**
     * Mark a campaign terminal and record the transition.
     *
     * @param \stdClass $job Campaign
     * @param string $reason Audit key
     */
    private static function finish(\stdClass $job, string $reason): void {
        global $DB;
        $job->state = 'complete';
        $job->revision++;
        $job->updated = time();
        $DB->update_record(store::CAMP, $job);
        store::audit($job, $reason, 0);
    }
}
