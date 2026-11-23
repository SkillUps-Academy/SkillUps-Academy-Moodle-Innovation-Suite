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

namespace tool_skillupsenrollment;

/**
 * Reconcile Moodle enrollment intent with the confirmed preview.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mutation {
    /**
     * Determine if the native state already satisfies the selected intent.
     */
    public static function satisfied(string $operation, array $current): bool {
        if (!$current['enrolled']) {
            return false;
        }
        return match ($operation) {
            'enrol', 'role' => $current['role'] && $current['group'],
            'suspend' => $current['status'] === ENROL_USER_SUSPENDED,
            'reactivate' => $current['status'] === ENROL_USER_ACTIVE,
            default => false,
        };
    }

    /**
     * Apply exactly one validated row, then commit its ledger state with it.
     */
    public static function apply(\stdClass $record, int $actor): void {
        global $DB;
        $row = [];
        foreach (['courseid', 'userid', 'instanceid', 'roleid', 'groupid'] as $field) {
            $row[$field] = (int)$record->$field;
        }
        $row['action'] = $record->action;
        $snapshot = json_decode($record->payload, true, 10, JSON_THROW_ON_ERROR);
        $intent = validator::observe($row, $actor, $snapshot['method']);
        $current = $intent['before'];
        $operation = $row['action'];
        if (self::satisfied($operation, $current)) {
            jobs::mark((int)$record->id, 'noop');
            return;
        }
        if ($snapshot['before'] !== $current) {
            throw new \moodle_exception('changed', support::C);
        }

        $tx = $DB->start_delegated_transaction();
        try {
            $plugin = $intent['plugin'];
            $instance = $intent['instance'];
            $userid = $row['userid'];
            if ($operation === 'suspend' || $operation === 'reactivate') {
                $desired = $operation === 'suspend' ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
                $plugin->update_user_enrol($instance, $userid, $desired);
            } else {
                if (!$current['enrolled']) {
                    $plugin->enrol_user($instance, $userid, $row['roleid'], 0, 0, ENROL_USER_ACTIVE, false);
                } else if (!$current['role']) {
                    role_assign($row['roleid'], $userid, $intent['context']->id);
                }
                if (!$current['group'] && !groups_add_member($intent['group'], $intent['user'])) {
                    throw new \moodle_exception('groupfailed', support::C);
                }
            }
            jobs::mark((int)$record->id, 'applied');
            $tx->allow_commit();
        } catch (\Throwable $error) {
            $tx->rollback($error);
        }
    }
}
