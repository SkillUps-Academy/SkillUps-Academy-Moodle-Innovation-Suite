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

namespace report_skillupsaccessibility\privacy;

use core\context;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use report_skillupsaccessibility\review;
use report_skillupsaccessibility\scope;
use report_skillupsaccessibility\store;

/**
 * Implement export and deletion of reviewer identities and attached evidence.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata, request_provider {
    /** @var array Personal columns by plugin table. */
    private const RECORDS = [
        'job' => [store::JOB, ['actorid']],
        'finding' => [store::FINDING, ['assignee']],
        'review' => [store::REVIEW, ['authorid', 'assignee']],
    ];

    /**
     * Declare the personal data used for review and accountability.
     *
     * @param collection $collection Moodle metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::RECORDS as $key => [$table, $columns]) {
            $fields = [];
            foreach ($key === 'review' ? [...$columns, 'notes'] : $columns as $field) {
                $fields[$field] = 'privacy:' . $field;
            }
            $collection->add_database_table($table, $fields, 'privacy:' . $key);
        }
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        return $collection;
    }

    /**
     * Discover course contexts holding data for a user.
     *
     * @param int $userid Subject account
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $result = new contextlist();
        foreach (self::RECORDS as $key => [$table, $columns]) {
            $from = self::from($key, $table);
            $course = self::course_column($key);
            foreach ($columns as $field) {
                $result->add_from_sql(
                    "SELECT DISTINCT c.id FROM $from JOIN {context} c ON c.instanceid = $course
                      WHERE c.contextlevel = :context AND p.$field = :userid",
                    ['context' => CONTEXT_COURSE, 'userid' => $userid],
                );
            }
        }
        return $result;
    }

    /**
     * Find accounts referenced in an approved course context.
     *
     * @param userlist $userlist Moodle context and user list
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        foreach (self::RECORDS as $key => [$table, $columns]) {
            $from = self::from($key, $table);
            $course = self::course_column($key);
            foreach ($columns as $field) {
                $userlist->add_from_sql(
                    $field,
                    "SELECT p.$field FROM $from WHERE $course = :course AND p.$field > 0",
                    ['course' => $context->instanceid],
                );
            }
        }
    }

    /**
     * Export approved personal data and evidence authored by the user.
     *
     * @param approved_contextlist $contextlist Moodle approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            foreach (self::RECORDS as $key => [$table, $columns]) {
                $from = self::from($key, $table);
                $course = self::course_column($key);
                $conditions = [];
                $args = ['course' => $context->instanceid];
                foreach ($columns as $field) {
                    $conditions[] = "p.$field = :$field";
                    $args[$field] = $userid;
                }
                $predicate = implode(' OR ', $conditions);
                $rows = $DB->get_recordset_sql("SELECT p.* FROM $from WHERE $course = :course AND ($predicate)", $args);
                try {
                    foreach ($rows as $row) {
                        $path = [scope::text('pluginname'), $key, (string)$row->id];
                        $record = (object)[
                            'id' => $row->id, 'status' => $row->state ?? $row->status, 'created' => $row->created ?? 0,
                        ];
                        foreach ($columns as $field) {
                            $record->{$field} = $row->{$field} == $userid;
                        }
                        if ($key === 'review' && $row->authorid == $userid) {
                            $record->notes = $row->notes;
                            writer::with_context($context)->export_area_files($path, scope::C, review::AREA, $row->id);
                        }
                        writer::with_context($context)->export_data($path, $record);
                    }
                } finally {
                    $rows->close();
                }
            }
        }
    }

    /**
     * Erase all personal references in the specified course.
     *
     * @param context $context Moodle context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        self::erase($context, []);
    }

    /**
     * Erase personal references in the user's approved course contexts.
     *
     * @param approved_contextlist $contextlist Approved scope
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::erase($context, [(int)$contextlist->get_user()->id]);
        }
    }

    /**
     * Erase references for approved users.
     *
     * @param approved_userlist $userlist Approved accounts
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_userids()) {
            self::erase($userlist->get_context(), $userlist->get_userids());
        }
    }

    /**
     * Anonymize operator associations and remove their notes and attachments.
     *
     * @param context $context Course context
     * @param array $users Empty for all
     */
    private static function erase(context $context, array $users): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        store::lock((int)$context->instanceid, static function () use ($DB, $context, $users): void {
            store::transaction(static function () use ($DB, $context, $users): void {
                foreach (self::RECORDS as $key => [$table, $columns]) {
                    $from = self::from($key, $table);
                    $course = self::course_column($key);
                    foreach ($columns as $field) {
                        foreach ($users ? array_chunk($users, 300) : [[]] as $group) {
                            $predicate = "p.$field > 0";
                            $args = ['course' => $context->instanceid];
                            if ($group) {
                                [$in, $params] = $DB->get_in_or_equal($group, SQL_PARAMS_NAMED, 'person');
                                $predicate = "p.$field $in";
                                $args += $params;
                            }
                            $set = $DB->get_recordset_sql("SELECT p.id FROM $from WHERE $course = :course AND $predicate", $args);
                            try {
                                foreach ($set as $item) {
                                    $data = (object)['id' => $item->id, $field => 0];
                                    if ($key === 'review' && $field === 'authorid') {
                                        get_file_storage()->delete_area_files($context->id, scope::C, review::AREA, $item->id);
                                        $data->notes = null;
                                    }
                                    $DB->update_record($table, $data);
                                }
                            } finally {
                                $set->close();
                            }
                        }
                    }
                }
            });
        });
    }

    /**
     * Join a finding to the course via its source.
     *
     * @param string $key Record kind
     * @param string $table Plugin table
     * @return string
     */
    private static function from(string $key, string $table): string {
        return $key === 'finding' ? "{{$table}} p JOIN {" . store::ASSET . "} a ON a.id = p.assetid" : "{{$table}} p";
    }

    /**
     * Resolve the course field in the authorized query.
     *
     * @param string $key Record kind
     * @return string
     */
    private static function course_column(string $key): string {
        return $key === 'finding' ? 'a.courseid' : 'p.courseid';
    }
}
