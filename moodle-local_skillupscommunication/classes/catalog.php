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
 * Manage category-scoped templates, programs, campuses and custom account sets.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /**
     * Save an authorized catalogue entry and replace explicit set members atomically.
     *
     * @param \context $scope Owning context
     * @param string $type Catalogue type
     * @param int $id Existing entry, or 0
     * @param \stdClass $data Moodle form data
     */
    public static function save(\context $scope, string $type, int $id, \stdClass $data): void {
        global $CFG, $DB, $USER;
        if (!access::can($scope, 'configure') || !in_array($type, ['template', 'segment'], true)) {
            access::fail();
        }
        $table = $type === 'template' ? store::TEMPLATE : store::SEG;
        $record = $id ? store::get($table, $id) : (object)['scopeid' => $scope->id];
        if ($record->scopeid != $scope->id) {
            access::fail();
        }
        $record->name = trim($data->name ?? '');
        if ($record->name === '' || \core_text::strlen($record->name) > 100) {
            access::fail('invalid');
        }
        $record->updated = time();
        $accounts = [];
        if ($type === 'template') {
            $record->authorid = $USER->id;
            $record->subject = trim($data->subject ?? '');
            $record->body = trim($data->body ?? '');
            $record->kind = $data->kind;
            if (
                $record->subject === '' || $record->body === '' || \core_text::strlen($record->subject) > 180 ||
                \core_text::strlen($record->body) > 12000 || !in_array($record->kind, campaign::KINDS, true)
            ) {
                access::fail('invalid');
            }
        } else {
            $record->creator = $USER->id;
            $kind = $record->kind ?? $data->segmentkind;
            if (!in_array($kind, ['program', 'campus', 'set'], true)) {
                access::fail('invalid');
            }
            $record->kind = $kind;
            $record->targetcontext = 0;
            if ($kind === 'set') {
                $lines = preg_split('/\R/u', trim($data->usernames ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                $usernames = array_unique(array_filter(array_map('trim', $lines)));
                if (!$usernames || count($usernames) > store::limit()) {
                    access::fail('audiencelimit');
                }
                foreach (array_chunk($usernames, 200) as $part) {
                    [$matches, $params] = $DB->get_in_or_equal($part, SQL_PARAMS_NAMED, 'account');
                    $found = $DB->get_fieldset_select(
                        'user',
                        'id',
                        "mnethostid = :host AND username $matches",
                        $params + ['host' => $CFG->mnet_localhost_id]
                    );
                    $validated = $found ? audience::ids($scope, [], (int)$USER->id, array_map('intval', $found)) : [];
                    if (count($part) !== count($validated)) {
                        access::fail('invalidmembers');
                    }
                    $accounts = array_merge($accounts, $validated);
                }
            } else {
                $target = access::scope((int)($data->targetcontext ?? 0));
                if (
                    $target->contextlevel !== CONTEXT_COURSECAT || !access::contains($scope, $target) ||
                    !access::can($target, 'configure')
                ) {
                    access::fail();
                }
                $record->targetcontext = $target->id;
            }
        }
        store::locked('catalog:' . $scope->id, static function () use ($DB, $table, $type, $record, $id, $accounts): void {
            store::transaction(static function () use ($DB, $table, $type, $record, $id, $accounts): void {
                $key = ['scopeid' => $record->scopeid, 'name' => $record->name];
                if ($type === 'segment') {
                    $key['kind'] = $record->kind;
                }
                $match = $DB->get_field($table, 'id', $key);
                if ($match && $match != $id) {
                    access::fail('duplicate');
                }
                if ($id) {
                    $DB->update_record($table, $record);
                } else {
                    $record->id = $DB->insert_record($table, $record);
                }
                if ($type === 'segment' && $record->kind === 'set') {
                    $DB->delete_records(store::MEMBER, ['segmentid' => $record->id]);
                    foreach (array_chunk($accounts, 250) as $batch) {
                        $DB->insert_records(store::MEMBER, array_map(static fn($userid) => (object)[
                            'userid' => $userid, 'segmentid' => $record->id,
                        ], $batch));
                    }
                }
            });
        });
    }
}
