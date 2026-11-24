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

namespace local_skillupsprivacy\privacy;

use core\context;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupsprivacy\access;
use local_skillupsprivacy\store;

/**
 * Provide native privacy discovery, export and erasure for plugin-owned data.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Roles connecting personal data to a user. */
    private const IDENTIFIERS = [
        store::CASES => ['userid', 'createdby', 'ownerid'],
        store::AUDIT => ['actorid', 'priorid', 'targetid'],
        store::NOTICE => ['userid'],
    ];

    /**
     * Declare tables and the Moodle messaging subsystem.
     *
     * @param collection $collection Metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $definitions = [
            store::CASES => ['case', 'nativeid', 'nativestatus', 'kind', 'state', 'retention', 'contextid', 'due',
                'timecreated', 'timemodified', 'checkedat', 'revision', 'archived'],
            store::AUDIT => ['audit', 'caseid', 'operation', 'previous', 'result', 'note', 'timecreated'],
            store::NOTICE => ['notice', 'caseid', 'due', 'level', 'status', 'timecreated'],
        ];
        foreach ($definitions as $table => $fields) {
            $label = array_shift($fields);
            $columns = [];
            foreach ([...self::IDENTIFIERS[$table], ...$fields] as $field) {
                $columns[$field] = 'privacy:metadata:' . $field;
            }
            $collection->add_database_table($table, $columns, 'privacy:metadata:' . $label);
        }
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:message');
        return $collection;
    }

    /**
     * Find the system context for a subject or operator.
     *
     * @param int $userid User
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        if ($userid <= 0) {
            return $list;
        }
        foreach (self::IDENTIFIERS as $table => $fields) {
            [$where, $params] = self::matches($fields, $userid);
            $list->add_from_sql("SELECT c.id FROM {context} c WHERE c.contextlevel = :scope
                AND EXISTS (SELECT 1 FROM {{$table}} d WHERE $where)", $params + ['scope' => CONTEXT_SYSTEM]);
        }
        return $list;
    }

    /**
     * List users whose data is stored at system scope.
     *
     * @param userlist $userlist Users in context
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::IDENTIFIERS as $table => $fields) {
            foreach ($fields as $field) {
                $userlist->add_from_sql($field, "SELECT DISTINCT $field FROM {{$table}} WHERE $field > 0", []);
            }
        }
    }

    /**
     * Export case summaries and the subject's own actions and reminder claims.
     *
     * @param approved_contextlist $contextlist Approved contexts and subject
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM || $userid <= 0) {
                continue;
            }
            foreach (self::IDENTIFIERS as $table => $fields) {
                [$where, $params] = self::matches($fields, $userid);
                $records = $DB->get_recordset_select($table, $where, $params, 'id ASC');
                try {
                    foreach ($records as $row) {
                        if ($table === store::CASES && (int)$row->userid !== $userid) {
                            $row = (object)[
                                'id' => $row->id, 'timecreated' => $row->timecreated,
                                'createdby' => $row->createdby, 'ownerid' => $row->ownerid,
                            ];
                        }
                        foreach ($fields as $field) {
                            if (isset($row->$field) && (int)$row->$field !== $userid) {
                                unset($row->$field);
                            }
                        }
                        unset($row->nativeid, $row->contextid, $row->note);
                        $section = match ($table) {
                            store::CASES => access::text('view_cases'),
                            store::AUDIT => access::text('history'),
                            default => access::text('reminders'),
                        };
                        writer::with_context($context)->export_data([access::text('pluginname'), $section, (string)$row->id], $row);
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Honor native bulk erasure at system context.
     *
     * @param context $context Context approved for erasure
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            store::atomic(static function (): void {
                global $DB;
                foreach ([store::NOTICE, store::AUDIT, store::CASES] as $table) {
                    $DB->delete_records($table);
                }
            });
        }
    }

    /**
     * Erase an approved subject's plugin records in system context.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Erase multiple approved users' plugin records.
     *
     * @param approved_userlist $userlist Approved users in context
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase((int)$userid);
            }
        }
    }

    /**
     * Archive subject cases after removing identifying links and case evidence.
     *
     * @param int $userid Approved subject
     */
    private static function erase(int $userid): void {
        if ($userid <= 0) {
            return;
        }
        store::atomic(static function () use ($userid): void {
            global $DB;
            $cases = $DB->get_fieldset_select(store::CASES, 'id', 'userid = :subject', ['subject' => $userid]);
            foreach (array_chunk($cases, 200) as $group) {
                $DB->delete_records_list(store::NOTICE, 'caseid', $group);
                $DB->delete_records_list(store::AUDIT, 'caseid', $group);
                [$in, $params] = $DB->get_in_or_equal($group, SQL_PARAMS_NAMED, 'case');
                $table = store::CASES;
                $DB->execute(
                    "UPDATE {{$table}} SET userid = 0, ownerid = 0, createdby = 0, contextid = :system,
                            nativeid = NULL, nativestatus = -1, checkedat = 0, archived = 1, revision = revision + 1
                      WHERE id $in",
                    $params + ['system' => \context_system::instance()->id],
                );
            }
            $DB->delete_records(store::NOTICE, ['userid' => $userid]);
            $DB->set_field(store::AUDIT, 'note', null, ['actorid' => $userid]);
            foreach (['actorid', 'priorid', 'targetid'] as $field) {
                $DB->set_field(store::AUDIT, $field, 0, [$field => $userid]);
            }
            foreach (['ownerid', 'createdby'] as $field) {
                $table = store::CASES;
                $DB->execute("UPDATE {{$table}} SET $field = 0, revision = revision + 1 WHERE $field = :user", ['user' => $userid]);
            }
        });
    }

    /**
     * Combine identifying roles with unique Moodle placeholders.
     *
     * @param array $fields Validated identifier fields
     * @param int $userid User
     * @return array
     */
    private static function matches(array $fields, int $userid): array {
        $clauses = [];
        $params = [];
        foreach ($fields as $field) {
            $clauses[] = "$field = :$field";
            $params[$field] = $userid;
        }
        return [implode(' OR ', $clauses), $params];
    }
}
