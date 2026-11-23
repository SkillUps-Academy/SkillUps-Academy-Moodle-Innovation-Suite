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

namespace local_skillupscertify\privacy;

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
use local_skillupscertify\access;
use local_skillupscertify\issuer;
use local_skillupscertify\store;

/**
 * Export and erase personal data scoped to an issuing course.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Tables with personal data and the related fields. */
    private const DATA = [
        store::ISSUE => ['issue', 'snapshot', 'status', 'tokenhash', 'pdfhash', 'reason', 'timeissued', 'timerevoked'],
        store::ITEM => ['item', 'batchid', 'issueid', 'state', 'timeupdated'],
        store::BATCH => ['batch', 'state', 'timecreated', 'timemodified'],
        store::AUDIT => ['audit', 'action', 'objectid', 'timecreated'],
    ];

    /**
     * Declare personal data and File API storage.
     *
     * @param collection $collection Registry of metadata
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::DATA as $table => $fields) {
            $label = array_shift($fields);
            $columns = [];
            foreach (['courseid', 'userid', ...$fields] as $field) {
                $columns[$field] = 'privacy:metadata:' . $field;
            }
            $collection->add_database_table($table, $columns, 'privacy:metadata:' . $label);
        }
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        $collection->add_subsystem_link('core_task', [], 'privacy:metadata:tasks');
        return $collection;
    }

    /**
     * Find courses in which this person has private records.
     *
     * @param int $userid Moodle user
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (array_keys(self::DATA) as $table) {
            $list->add_from_sql(
                "SELECT DISTINCT ctx.id FROM {context} ctx JOIN {{$table}} r ON r.courseid = ctx.instanceid
                  WHERE ctx.contextlevel = :level AND r.userid = :userid",
                ['level' => CONTEXT_COURSE, 'userid' => $userid]
            );
        }
        return $list;
    }

    /**
     * Identify people whose data is present.
     *
     * @param userlist $userlist Moodle course context
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        foreach (array_keys(self::DATA) as $table) {
            $userlist->add_from_sql('userid', "SELECT DISTINCT userid FROM {{$table}} WHERE courseid = :id AND userid > 0", [
                'id' => $context->instanceid,
            ]);
        }
    }

    /**
     * Export one authorized person's metadata and PDFs.
     *
     * @param approved_contextlist $contextlist Approved contexts and user
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $output = writer::with_context($context);
            $criteria = ['courseid' => $context->instanceid, 'userid' => $contextlist->get_user()->id];
            foreach (self::DATA as $table => $fields) {
                $records = $DB->get_recordset($table, $criteria, 'id ASC');
                try {
                    foreach ($records as $record) {
                        $subcontext = [access::text('pluginname'), $fields[0], (string)$record->id];
                        if ($table === store::ISSUE) {
                            $output->export_area_files($subcontext, access::C, issuer::AREA, $record->id);
                            unset($record->tokenhash, $record->activekey);
                        }
                        $output->export_data($subcontext, $record);
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Erase all issued personal data in a course.
     *
     * @param context $context Approved context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $users = new userlist($context, access::C);
        self::get_users_in_context($users);
        foreach ($users->get_userids() as $userid) {
            self::erase($context, (int)$userid);
        }
    }

    /**
     * Erase one user's records from approved course contexts.
     *
     * @param approved_contextlist $contextlist Approved user and contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                self::erase($context, (int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Erase approved people from one course.
     *
     * @param approved_userlist $userlist Approved people
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase($context, (int)$userid);
            }
        }
    }

    /**
     * Remove recipient documents and anonymize the actor in audit events.
     *
     * @param context $context Issuing course
     * @param int $userid Moodle user
     */
    private static function erase(context $context, int $userid): void {
        global $DB;
        store::atomic(issuer::holder_lock($context->instanceid, $userid), static function () use ($DB, $context, $userid): void {
            $criteria = ['courseid' => $context->instanceid, 'userid' => $userid];
            $files = get_file_storage();
            $ids = $DB->get_fieldset_select(store::ISSUE, 'id', 'courseid = :courseid AND userid = :userid', $criteria);
            foreach ($ids as $id) {
                $files->delete_area_files($context->id, access::C, issuer::AREA, $id);
            }
            $DB->delete_records(store::ISSUE, $criteria);
            $DB->delete_records(store::ITEM, $criteria);
            $batches = $DB->get_fieldset_select(store::BATCH, 'id', 'courseid = :courseid AND userid = :userid', $criteria);
            foreach ($batches as $id) {
                $DB->delete_records(store::ITEM, ['batchid' => $id]);
                $DB->delete_records(store::BATCH, ['id' => $id]);
            }
            $DB->set_field(store::AUDIT, 'userid', 0, $criteria);
        });
    }
}
