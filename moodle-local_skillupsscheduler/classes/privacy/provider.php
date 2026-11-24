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

namespace local_skillupsscheduler\privacy;

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
use local_skillupsscheduler\calendar;
use local_skillupsscheduler\scope;
use local_skillupsscheduler\store;

/**
 * Discover, export and erase staff associations in their Moodle contexts.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Tables that contain personal associations. */
    private const TABLES = [
        'slot' => [store::SLOT, ['teacherid']],
        'audit' => [store::AUDIT, ['actorid', 'oldteacher', 'newteacher']],
        'grant' => [store::GRANT, ['userid']],
        'queue' => [store::QUEUE, ['oldteacher', 'newteacher']],
    ];

    /**
     * Get metadata.
     *
     * @param collection $collection Collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::TABLES as $name => [$table, $fields]) {
            $columns = [];
            foreach ($name === 'audit' ? [...$fields, 'reason'] : $fields as $field) {
                $columns[$field] = 'privacy:' . $field;
            }
            $collection->add_database_table($table, $columns, 'privacy:' . $name);
        }
        $collection->add_subsystem_link('core_message', [], 'privacy:messages');
        $collection->add_subsystem_link('core_calendar', [], 'privacy:calendar');
        return $collection;
    }

    /**
     * Get contexts for userid.
     *
     * @param int $userid Moodle user ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contexts = new contextlist();
        if ($DB->record_exists(store::GRANT, ['userid' => $userid])) {
            $contexts->add_system_context();
        }
        foreach (['slot', 'audit', 'queue'] as $name) {
            [$table, $fields] = self::TABLES[$name];
            [$where, $params] = self::person($fields, $userid);
            $source = self::sql_source($name, $table);
            $column = $name === 'queue' ? 's.courseid' : 'p.courseid';
            $contexts->add_from_sql(
                "SELECT DISTINCT c.id FROM $source JOIN {context} c ON c.instanceid = $column
                  WHERE c.contextlevel = :level AND ($where)",
                ['level' => CONTEXT_COURSE] + $params,
            );
        }
        return $contexts;
    }

    /**
     * Get users in context.
     *
     * @param userlist $userlist Users in the requested context
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        foreach (self::TABLES as $name => [$table, $fields]) {
            $filter = self::filter($context, $name);
            if (!$filter) {
                continue;
            }
            [$where, $params] = $filter;
            $source = self::sql_source($name, $table);
            foreach ($fields as $field) {
                $userlist->add_from_sql($field, "SELECT p.$field FROM $source WHERE $where AND p.$field > 0", $params);
            }
        }
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist Approved contexts and user
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            foreach (self::TABLES as $name => [$table, $fields]) {
                $filter = self::filter($context, $name);
                if (!$filter) {
                    continue;
                }
                [$filterwhere, $filterparams] = $filter;
                [$where, $params] = self::person($fields, $userid);
                $source = self::sql_source($name, $table);
                $records = $DB->get_recordset_sql(
                    "SELECT p.* FROM $source WHERE $filterwhere AND ($where)",
                    $filterparams + $params,
                );
                try {
                    foreach ($records as $row) {
                        $export = (object)['id' => $row->id];
                        foreach ($fields as $field) {
                            $export->{$field} = $row->{$field} == $userid;
                        }
                        foreach (['siteid', 'slotid', 'title', 'starts', 'ends', 'operation', 'created', 'revision'] as $field) {
                            if (isset($row->{$field})) {
                                $export->{$field} = $row->{$field};
                            }
                        }
                        if ($name === 'audit' && $row->actorid == $userid) {
                            $export->reason = $row->reason;
                        }
                        writer::with_context($context)->export_data([scope::text('pluginname'), $name, (string)$row->id], $export);
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Delete data for all users in context.
     *
     * @param context $context Moodle context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        self::erase($context, []);
    }

    /**
     * Delete data for user.
     *
     * @param approved_contextlist $contextlist Approved contexts and user
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::erase($context, [(int)$contextlist->get_user()->id]);
        }
    }

    /**
     * Delete data for users.
     *
     * @param approved_userlist $userlist Users in the requested context
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $ids = $userlist->get_userids();
        if ($ids) {
            self::erase($userlist->get_context(), $ids);
        }
    }

    /**
     * Erase.
     *
     * @param context $context Moodle context
     * @param array $userids IDs approved for erasure
     */
    private static function erase(context $context, array $userids): void {
        global $DB;
        store::atomic(static function () use ($DB, $context, $userids): void {
            foreach (self::TABLES as $name => [$table, $fields]) {
                $filter = self::filter($context, $name);
                if (!$filter) {
                    continue;
                }
                [$filterwhere, $filterparams] = $filter;
                $source = self::sql_source($name, $table);
                foreach ($fields as $field) {
                    foreach ($userids ? array_chunk($userids, 300) : [[]] as $part) {
                        $condition = "p.$field > 0";
                        $params = $filterparams;
                        if ($part) {
                            [$in, $values] = $DB->get_in_or_equal($part, SQL_PARAMS_NAMED, 'person');
                            $condition = "p.$field $in";
                            $params += $values;
                        }
                        $rows = $DB->get_recordset_sql("SELECT p.* FROM $source WHERE $filterwhere AND $condition", $params);
                        try {
                            foreach ($rows as $row) {
                                if ($name === 'grant') {
                                    $DB->delete_records($table, ['id' => $row->id]);
                                    continue;
                                }
                                $row->{$field} = 0;
                                if ($name === 'audit' && $field === 'actorid') {
                                    $row->reason = null;
                                }
                                if ($name === 'slot' && $row->status === 'published' && $row->ends > time()) {
                                    $row->status = 'draft';
                                    $row->revision++;
                                    $row->changed = time();
                                    calendar::sync($row);
                                    $row->eventid = 0;
                                    $DB->delete_records(store::QUEUE, ['slotid' => $row->id]);
                                }
                                $DB->update_record($table, $row);
                            }
                        } finally {
                            $rows->close();
                        }
                    }
                }
            }
        });
    }

    /**
     * Sql source.
     *
     * @param string $name Name
     * @param string $table Table
     * @return string
     */
    private static function sql_source(string $name, string $table): string {
        return $name === 'queue' ? "{{$table}} p JOIN {" . store::SLOT . "} s ON s.id = p.slotid" : "{{$table}} p";
    }

    /**
     * Filter.
     *
     * @param context $context Moodle context
     * @param string $name Name
     * @return array|null
     */
    private static function filter(context $context, string $name): ?array {
        if ($name === 'grant') {
            return $context->contextlevel === CONTEXT_SYSTEM ? ['1 = 1', []] : null;
        }
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return null;
        }
        return [($name === 'queue' ? 's' : 'p') . '.courseid = :course', ['course' => $context->instanceid]];
    }

    /**
     * Match the privacy request subject to identity columns.
     *
     * @param array $fields Fields
     * @param int $userid Moodle user ID
     * @return array
     */
    private static function person(array $fields, int $userid): array {
        $clauses = [];
        $params = [];
        foreach ($fields as $field) {
            $clauses[] = "p.$field = :$field";
            $params[$field] = $userid;
        }
        return [implode(' OR ', $clauses), $params];
    }
}
