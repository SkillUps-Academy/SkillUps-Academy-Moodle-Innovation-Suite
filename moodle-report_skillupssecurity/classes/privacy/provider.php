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

namespace report_skillupssecurity\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use report_skillupssecurity\access;
use report_skillupssecurity\store;

/**
 * Expose and erase personal audit data in the system context.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Personal identifier fields and table descriptions. */
    private const TABLES = [
        store::FINDING => ['finding', 'userid'],
        store::HISTORY => ['history', 'actorid'],
        store::PRIVILEGE => ['privilege', 'userid'],
        store::ACTIVITY => ['activity', 'actorid', 'subjectid'],
        store::OUTBOX => ['outbox', 'userid'],
    ];

    /**
     * Declare personal data and the messaging subsystem.
     *
     * @param collection $collection Metadata
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [
            'finding' => ['ruleid', 'contextid', 'evidence', 'reviewstate', 'firstseen', 'lastseen'],
            'history' => ['findingid', 'action', 'note', 'timecreated'],
            'privilege' => ['scanid', 'contextid', 'roleid', 'capabilities', 'lastaccess', 'suspended'],
            'activity' => ['contextid', 'objectid', 'action', 'detail', 'timecreated'],
            'outbox' => ['scanid', 'state', 'timecreated', 'timesent'],
        ];
        foreach (self::TABLES as $table => $keys) {
            $label = array_shift($keys);
            $columns = [];
            foreach ([...$keys, ...$fields[$label]] as $key) {
                $columns[$key] = 'privacy:metadata:' . $key;
            }
            $collection->add_database_table($table, $columns, 'privacy:metadata:' . $label);
        }
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:message');
        return $collection;
    }

    /**
     * Determine whether the subject appears in any system evidence.
     *
     * @param int $userid User
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::TABLES as $table => $definition) {
            foreach (array_slice($definition, 1) as $field) {
                $list->add_from_sql(
                    "SELECT c.id FROM {context} c WHERE c.contextlevel = :scope
                     AND EXISTS (SELECT 1 FROM {{$table}} d WHERE d.$field = :userid)",
                    ['scope' => CONTEXT_SYSTEM, 'userid' => $userid]
                );
            }
        }
        return $list;
    }

    /**
     * List subjects and report operators in an approved system context.
     *
     * @param userlist $userlist User lookup
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::TABLES as $table => $definition) {
            foreach (array_slice($definition, 1) as $field) {
                $userlist->add_from_sql($field, "SELECT DISTINCT $field FROM {{$table}} WHERE $field > 0", []);
            }
        }
    }

    /**
     * Export records linked to the approved subject without exposing other identities.
     *
     * @param approved_contextlist $contextlist Approved request scope
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            foreach (self::TABLES as $table => $definition) {
                $label = array_shift($definition);
                foreach ($definition as $field) {
                    $records = $DB->get_recordset($table, [$field => $userid], 'id ASC');
                    try {
                        foreach ($records as $record) {
                            foreach (['actorid', 'subjectid', 'userid'] as $key) {
                                if (isset($record->$key) && $record->$key != $userid) {
                                    unset($record->$key);
                                }
                            }
                            unset($record->note, $record->fingerprint, $record->objectid, $record->findingid);
                            $path = [access::text('pluginname'), $label, (string)$record->id];
                            writer::with_context($context)->export_data($path, $record);
                        }
                    } finally {
                        $records->close();
                    }
                }
            }
        }
    }

    /**
     * Erase all personal data within the system context.
     *
     * @param context $context Approved context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            self::erase(null);
        }
    }

    /**
     * Erase one user's approved data.
     *
     * @param approved_contextlist $contextlist Approved scope
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Erase approved users from the system scope.
     *
     * @param approved_userlist $userlist Approved user IDs
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase((int)$userid);
            }
        }
    }

    /**
     * Delete observed personal evidence and anonymize retained audit metadata.
     *
     * @param int|null $userid User, or null for all
     */
    private static function erase(?int $userid): void {
        store::atomic(static function () use ($userid): void {
            global $DB;
            $params = $userid === null ? [] : ['userid' => $userid];
            $where = static fn(string $field): string => $userid === null ? "$field > 0" : "$field = :userid";
            $findings = $DB->get_fieldset_select(store::FINDING, 'id', $where('userid'), $params);
            foreach (array_chunk($findings, 200) as $group) {
                $DB->delete_records_list(store::HISTORY, 'findingid', $group);
            }
            foreach ([store::FINDING, store::PRIVILEGE, store::OUTBOX] as $table) {
                $DB->delete_records_select($table, $where('userid'), $params);
            }
            $DB->set_field_select(store::HISTORY, 'note', null, $where('actorid'), $params);
            $DB->set_field_select(store::HISTORY, 'actorid', 0, $where('actorid'), $params);
            $DB->set_field_select(store::ACTIVITY, 'actorid', 0, $where('actorid'), $params);
            $DB->set_field_select(store::ACTIVITY, 'subjectid', 0, $where('subjectid'), $params);
        });
    }
}
