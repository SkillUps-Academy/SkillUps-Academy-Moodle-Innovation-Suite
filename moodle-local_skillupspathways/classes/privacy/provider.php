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

namespace local_skillupspathways\privacy;

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
use local_skillupspathways\access;
use local_skillupspathways\store;

/**
 * Personal data is scoped to the itinerary entry course.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Personal data tables, export labels and stored fields. */
    private const DATA = [
        store::PROGRESS => ['progress', 'versionid', 'states', 'passed', 'requiredcount', 'completedat', 'timechecked'],
        store::HISTORY => ['history', 'versionid', 'nodekey', 'oldstate', 'newstate', 'timecreated'],
        store::AUDIT => ['audit', 'operation', 'timecreated'],
    ];

    /**
     * Declare stored learner and administrative data.
     *
     * @param collection $collection Metadata
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::DATA as $table => $fields) {
            $label = array_shift($fields);
            $columns = [];
            foreach (['pathid', 'userid', ...$fields] as $field) {
                $columns[$field] = 'privacy:metadata:' . $field;
            }
            $collection->add_database_table($table, $columns, 'privacy:metadata:' . $label);
        }
        return $collection;
    }

    /**
     * List entry courses containing an individual's data.
     *
     * @param int $userid User
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $path = store::PATH;
        foreach (array_keys(self::DATA) as $table) {
            $list->add_from_sql(
                "SELECT DISTINCT c.id
                   FROM {context} c JOIN {{$path}} p ON p.courseid = c.instanceid JOIN {{$table}} r ON r.pathid = p.id
                  WHERE c.contextlevel = :level AND r.userid = :userid",
                ['level' => CONTEXT_COURSE, 'userid' => $userid]
            );
        }
        return $list;
    }

    /**
     * List people with stored data in one entry course.
     *
     * @param userlist $userlist Course request
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $path = store::PATH;
        foreach (array_keys(self::DATA) as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT DISTINCT r.userid FROM {{$table}} r JOIN {{$path}} p ON p.id = r.pathid
                  WHERE p.courseid = :courseid AND r.userid > 0",
                ['courseid' => $context->instanceid]
            );
        }
    }

    /**
     * Export one person's data within approved entry courses.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $path = store::PATH;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $writer = writer::with_context($context);
            $params = ['courseid' => $context->instanceid, 'userid' => $contextlist->get_user()->id];
            foreach (self::DATA as $table => $fields) {
                $records = $DB->get_recordset_sql(
                    "SELECT r.* FROM {{$table}} r JOIN {{$path}} p ON p.id = r.pathid
                      WHERE p.courseid = :courseid AND r.userid = :userid ORDER BY r.id",
                    $params
                );
                try {
                    foreach ($records as $row) {
                        $writer->export_data([access::text('pluginname'), $fields[0], (string)$row->id], $row);
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Erase all people's data in an approved course.
     *
     * @param context $context Approved context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel === CONTEXT_COURSE) {
            self::erase((int)$context->instanceid, null);
        }
    }

    /**
     * Erase one person's data within approved courses.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                self::erase((int)$context->instanceid, [(int)$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Erase data for an approved list of people.
     *
     * @param approved_userlist $userlist Approved users and context
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE && $userlist->get_userids()) {
            self::erase((int)$context->instanceid, $userlist->get_userids());
        }
    }

    /**
     * Remove progress and timelines, and anonymize audit actors.
     *
     * @param int $courseid Entry course
     * @param array|null $users IDs, or null for everyone
     */
    private static function erase(int $courseid, ?array $users): void {
        global $DB;
        $paths = $DB->get_fieldset_select(store::PATH, 'id', 'courseid = :courseid', ['courseid' => $courseid]);
        foreach ($paths as $pathid) {
            store::atomic("path:$pathid", static function () use ($DB, $pathid, $users): void {
                foreach ($users === null ? [null] : array_chunk($users, 300) as $batch) {
                    $where = 'pathid = :pathid AND userid > 0';
                    $params = ['pathid' => $pathid];
                    if ($batch !== null) {
                        [$in, $extra] = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED, 'person');
                        $where .= " AND userid $in";
                        $params += $extra;
                    }
                    $DB->delete_records_select(store::HISTORY, $where, $params);
                    $DB->delete_records_select(store::PROGRESS, $where, $params);
                    $DB->set_field_select(store::AUDIT, 'userid', 0, $where, $params);
                }
            });
        }
    }
}
