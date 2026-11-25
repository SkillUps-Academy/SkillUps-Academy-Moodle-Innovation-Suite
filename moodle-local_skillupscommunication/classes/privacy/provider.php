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

namespace local_skillupscommunication\privacy;

use core\context;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadataprovider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as requestprovider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupscommunication\access;
use local_skillupscommunication\observer;
use local_skillupscommunication\store;

/**
 * Describe, export and erase personal campaign data by Moodle context.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadataprovider, requestprovider {
    /** @var array Subject reference fields. */
    private const USERS = [
        store::CAMP => ['creator', 'reviewer'], store::REC => ['userid'], store::LOG => ['userid'],
        store::TEMPLATE => ['authorid'], store::SEG => ['creator'], store::MEMBER => ['userid'],
    ];

    /**
     * Declare personal data and Moodle Messaging delegation.
     *
     * @param collection $collection Metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::USERS as $table => $fields) {
            if (in_array($table, [store::CAMP, store::TEMPLATE], true)) {
                $fields = array_merge($fields, ['subject', 'body']);
            }
            if ($table === store::REC) {
                $fields = array_merge($fields, ['state', 'messageid']);
            }
            $labels = [];
            foreach ($fields as $field) {
                $labels[$field] = 'privacy:field:' . $field;
            }
            $collection->add_database_table($table, $labels, 'privacy:' . substr($table, strlen(access::C) + 1));
        }
        $collection->add_subsystem_link('core_message', [], 'privacy:message');
        return $collection;
    }

    /**
     * Resolve a data source and the institutional context ID field.
     *
     * @param string $table Owned table
     * @return array
     */
    private static function source(string $table): array {
        $from = '{' . $table . '} r';
        if (in_array($table, [store::REC, store::LOG], true)) {
            return [$from . ' JOIN {' . store::CAMP . '} b ON b.id = r.campaignid', 'b.scopeid'];
        }
        if ($table === store::MEMBER) {
            return [$from . ' JOIN {' . store::SEG . '} b ON b.id = r.segmentid', 'b.scopeid'];
        }
        return [$from, 'r.scopeid'];
    }

    /**
     * Look up contexts where this account contributed, acted or was selected.
     *
     * @param int $userid User ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::USERS as $table => $fields) {
            [$source, $scope] = self::source($table);
            foreach ($fields as $field) {
                $list->add_from_sql(
                    "SELECT DISTINCT c.id FROM $source JOIN {context} c ON c.id = $scope WHERE r.$field = :id",
                    ['id' => $userid]
                );
            }
        }
        return $list;
    }

    /**
     * Find represented users in the approved context.
     *
     * @param userlist $userlist Scoped user list
     */
    public static function get_users_in_context(userlist $userlist): void {
        foreach (self::USERS as $table => $fields) {
            [$source, $scope] = self::source($table);
            foreach ($fields as $field) {
                $userlist->add_from_sql(
                    $field,
                    "SELECT DISTINCT r.$field FROM $source WHERE $scope = :scope AND r.$field > 0",
                    ['scope' => $userlist->get_context()->id]
                );
            }
        }
    }

    /**
     * Export subject records without unrelated recipients, identities or message content.
     *
     * @param approved_contextlist $contextlist Approved contexts and user
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            foreach (self::USERS as $table => $fields) {
                [$source, $scope] = self::source($table);
                foreach ($fields as $field) {
                    $rows = $DB->get_recordset_sql(
                        "SELECT r.* FROM $source WHERE $scope = :scope AND r.$field = :subject ORDER BY r.id",
                        ['scope' => $context->id, 'subject' => $userid]
                    );
                    try {
                        foreach ($rows as $row) {
                            $keys = ['campaignid', 'segmentid', 'state', 'updated', 'created', 'action', 'attempts', 'messageid'];
                            if (in_array($field, ['creator', 'authorid'], true)) {
                                array_push($keys, 'subject', 'body', 'name');
                            }
                            $data = array_intersect_key((array)$row, array_flip($keys));
                            $path = [get_string('pluginname', access::C), $table, $field, (string)$row->id];
                            writer::with_context($context)->export_data($path, (object)$data);
                        }
                    } finally {
                        $rows->close();
                    }
                }
            }
        }
    }

    /**
     * Delete this plugin's data after a native all-user context approval.
     *
     * @param context $context Authorized context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if (in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT], true)) {
            observer::purge($context->id);
        }
    }

    /**
     * Delete an approved subject's data.
     *
     * @param approved_contextlist $contextlist Approved scope and user
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::erase($context->id, (int)$contextlist->get_user()->id);
        }
    }

    /**
     * Delete approved subjects in a single context.
     *
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            self::erase($userlist->get_context()->id, (int)$userid);
        }
    }

    /**
     * Remove recipient records, revoke active authorizations and anonymize audit references.
     *
     * @param int $scopeid Approved Moodle context
     * @param int $userid Approved subject
     */
    private static function erase(int $scopeid, int $userid): void {
        global $DB;
        $camp = store::CAMP;
        $recipient = store::REC;
        $ids = $DB->get_fieldset_sql(
            "SELECT c.id FROM {{$camp}} c WHERE c.scopeid = :scope AND
              (c.creator = :creator OR c.reviewer = :reviewer OR
                 EXISTS (SELECT 1 FROM {{$recipient}} r WHERE r.campaignid = c.id AND r.userid = :recipient))",
            ['scope' => $scopeid, 'creator' => $userid, 'reviewer' => $userid, 'recipient' => $userid]
        );
        foreach ($ids as $id) {
            store::locked('campaign:' . $id, static function () use ($DB, $id, $userid): void {
                store::transaction(static function () use ($DB, $id, $userid): void {
                    $job = $DB->get_record(store::CAMP, ['id' => $id]);
                    if (!$job) {
                        return;
                    }
                    $DB->delete_records(store::REC, ['campaignid' => $id, 'userid' => $userid]);
                    $created = $job->creator == $userid;
                    $approved = $job->reviewer == $userid;
                    if (!$created && !$approved) {
                        return;
                    }
                    if ($created) {
                        $job->creator = 0;
                        $job->subject = get_string('unknownuser', access::C);
                        $job->body = '';
                    }
                    if ($approved) {
                        $job->reviewer = 0;
                    }
                    if ($job->state !== 'complete' && $job->state !== 'cancelled') {
                        $job->state = 'cancelled';
                        $DB->set_field(store::REC, 'state', 'uncertain', ['campaignid' => $id, 'state' => 'sending']);
                        $DB->set_field(store::REC, 'state', 'cancelled', ['campaignid' => $id, 'state' => 'pending']);
                    }
                    $job->revision++;
                    $job->updated = time();
                    $DB->update_record(store::CAMP, $job);
                });
            });
        }
        $where = 'userid = :subject AND campaignid IN (SELECT id FROM {' . store::CAMP . '} WHERE scopeid = :scope)';
        $params = ['scope' => $scopeid, 'subject' => $userid];
        $DB->set_field_select(store::LOG, 'userid', 0, $where, $params);
        $DB->delete_records_select(
            store::MEMBER,
            'userid = :subject AND segmentid IN (SELECT id FROM {' . store::SEG . '} WHERE scopeid = :scope)',
            $params
        );
        $DB->set_field(store::SEG, 'creator', 0, ['scopeid' => $scopeid, 'creator' => $userid]);
        $DB->delete_records(store::TEMPLATE, ['scopeid' => $scopeid, 'authorid' => $userid]);
    }
}
