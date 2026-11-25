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
 * Validate, freeze and approve the editorial campaign lifecycle.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class campaign {
    /** @var array Editorial message types. */
    public const KINDS = ['institutional', 'academic', 'reminder', 'operational', 'period', 'activity', 'campus', 'program'];

    /**
     * Create or revise a draft campaign.
     *
     * @param \context $scope Issuing context
     * @param int $id Campaign or 0
     * @param \stdClass $input Native Moodle form
     * @return int
     */
    public static function save(\context $scope, int $id, \stdClass $input): int {
        global $DB, $USER;
        if (!access::can($scope, 'compose')) {
            access::fail();
        }
        $subject = trim(preg_replace('/[\r\n]+/u', ' ', $input->subject ?? ''));
        $body = trim($input->body ?? '');
        if (
            $subject === '' || $body === '' || \core_text::strlen($subject) > 180 || \core_text::strlen($body) > 12000 ||
            !in_array($input->kind, self::KINDS, true) || !isset(transport::channels()[$input->channel])
        ) {
            access::fail('invalid');
        }
        $selector = audience::normalize((array)$input);
        // Validate authorization and references before persisting the draft.
        audience::ids($scope, $selector, (int)$USER->id, [0]);
        $work = static function () use ($DB, $USER, $scope, $id, $input, $subject, $body, $selector): int {
            return store::transaction(static function () use ($DB, $USER, $scope, $id, $input, $subject, $body, $selector): int {
                $record = $id ? store::get(store::CAMP, $id) : (object)[
                    'scopeid' => $scope->id, 'creator' => $USER->id, 'created' => time(),
                    'reviewer' => 0, 'state' => 'draft', 'revision' => 0, 'wave' => 0,
                ];
                if (
                    $record->scopeid != $scope->id || ($id && ($record->state !== 'draft' ||
                    $record->revision != $input->revision))
                ) {
                    access::fail('stale');
                }
                $record->subject = $subject;
                $record->body = $body;
                $record->kind = $input->kind;
                $record->channel = $input->channel;
                $record->selector = json_encode($selector, JSON_THROW_ON_ERROR);
                $record->sendat = max(0, (int)($input->sendat ?? 0));
                $record->updated = time();
                $record->revision++;
                if ($id) {
                    $DB->update_record(store::CAMP, $record);
                } else {
                    $record->id = $DB->insert_record(store::CAMP, $record);
                }
                store::audit($record, $id ? 'edited' : 'created', (int)$USER->id);
                return (int)$record->id;
            });
        };
        return $id ? store::locked('campaign:' . $id, $work) : $work();
    }

    /**
     * Return the normalized selection of a stored draft.
     *
     * @param \stdClass $record Campaign
     * @return array
     */
    public static function filters(\stdClass $record): array {
        return audience::normalize(json_decode($record->selector, true, 8, JSON_THROW_ON_ERROR));
    }

    /**
     * Apply a published transition and queue only after committing.
     *
     * @param int $id Campaign ID
     * @param int $revision Revision confirmed by the request
     * @param string $action Requested transition
     * @param string $fingerprint Preview digest for submission
     */
    public static function change(int $id, int $revision, string $action, string $fingerprint = ''): void {
        global $DB, $USER;
        $next = store::locked('campaign:' . $id, static function () use ($DB, $USER, $id, $revision, $action, $fingerprint) {
            return store::transaction(static function () use ($DB, $USER, $id, $revision, $action, $fingerprint) {
                $record = store::get(store::CAMP, $id);
                $scope = access::scope((int)$record->scopeid);
                $author = access::can($scope, 'compose');
                $reviewer = access::can($scope, 'approve');
                if (
                    ($action === 'submit' && !$author) || ($action === 'cancel' && !$author && !$reviewer) ||
                    (!in_array($action, ['submit', 'cancel'], true) && !$reviewer)
                ) {
                    access::fail();
                }
                if ($record->revision != $revision) {
                    access::fail('stale');
                }
                $key = ['campaignid' => $id];
                if ($action === 'submit' && $record->state === 'draft') {
                    $targets = audience::ids($scope, self::filters($record), (int)$USER->id);
                    if (!$targets) {
                        access::fail('emptytarget');
                    }
                    if (!hash_equals(audience::fingerprint($targets, $revision), $fingerprint)) {
                        access::fail('stale');
                    }
                    foreach (array_chunk($targets, 250) as $batch) {
                        $DB->insert_records(store::REC, array_map(static fn($userid) => (object)[
                            'campaignid' => $id, 'userid' => $userid, 'failure' => '', 'updated' => time(),
                        ], $batch));
                    }
                    $record->state = 'review';
                    $record->creator = $USER->id;
                } else if ($action === 'approve' && $record->state === 'review') {
                    if (!isset(transport::channels()[$record->channel])) {
                        access::fail('channelunavailable');
                    }
                    $current = audience::ids($scope, self::filters($record), (int)$USER->id);
                    $original = array_map('intval', $DB->get_fieldset_select(
                        store::REC,
                        'userid',
                        'campaignid = :id ORDER BY userid',
                        ['id' => $id]
                    ));
                    if (!$current || $original !== $current) {
                        access::fail('stale');
                    }
                    $record->reviewer = $USER->id;
                    $record->state = 'queued';
                    $record->wave++;
                } else if ($action === 'return' && $record->state === 'review') {
                    $DB->delete_records(store::REC, $key);
                    $record->state = 'draft';
                } else if ($action === 'cancel' && in_array($record->state, ['draft', 'review', 'queued', 'running'], true)) {
                    $DB->set_field(store::REC, 'state', 'cancelled', $key + ['state' => 'pending']);
                    $DB->set_field(store::REC, 'state', 'uncertain', $key + ['state' => 'sending']);
                    $record->state = 'cancelled';
                } else if ($action === 'retry' && $record->state === 'complete') {
                    $eligible = 'campaignid = :id AND state = :state AND attempts < :max';
                    $params = ['id' => $id, 'state' => 'failed', 'max' => 6];
                    if (!$DB->record_exists_select(store::REC, $eligible, $params)) {
                        access::fail('nothingtoretry');
                    }
                    $DB->set_field_select(store::REC, 'nexttry', 0, $eligible, $params);
                    $DB->set_field_select(store::REC, 'state', 'pending', $eligible, $params);
                    $record->state = 'queued';
                    $record->reviewer = $USER->id;
                    $record->wave++;
                } else {
                    access::fail('stale');
                }
                $record->revision++;
                $record->updated = time();
                $DB->update_record(store::CAMP, $record);
                store::audit($record, $action, (int)$USER->id);
                return $record->state === 'queued' ? $record : null;
            });
        });
        if ($next) {
            dispatch::queue($next, (int)$next->sendat);
        }
    }
}
