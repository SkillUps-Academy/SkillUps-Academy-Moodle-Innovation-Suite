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

namespace local_skillupshelpdesk\privacy;

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
use local_skillupshelpdesk\access;
use local_skillupshelpdesk\storage;

/**
 * Privacy export and erasure for system-scoped support records.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Fields that can identify an operator, requester, or recipient. */
    private const USERS = [
        storage::TICKET => ['requesterid', 'assigneeid'],
        storage::POST => ['userid'],
        storage::AUDIT => ['userid', 'formerassignee', 'newassignee'],
        storage::OUTBOX => ['userid'],
    ];

    /**
     * Document data held by the plugin and its private file areas.
     *
     * @param collection $collection Metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [
            storage::TICKET => ['requesterid', 'assigneeid', 'title', 'courseid', 'rating', 'timecreated', 'timemodified',
                'firstdue', 'resolvedue', 'firstresponse', 'resolvedat', 'responsebreach', 'resolvebreach', 'ratedat'],
            storage::POST => ['ticketid', 'userid', 'internal', 'body', 'timecreated'],
            storage::AUDIT => ['ticketid', 'userid', 'formerassignee', 'newassignee', 'action', 'details', 'timecreated'],
            storage::OUTBOX => ['ticketid', 'userid', 'availableat', 'attempts', 'timecreated'],
        ];
        $names = [storage::TICKET => 'ticket', storage::POST => 'post', storage::AUDIT => 'audit', storage::OUTBOX => 'outbox'];
        foreach ($fields as $table => $nameset) {
            $descriptions = [];
            foreach ($nameset as $name) {
                $descriptions[$name] = 'privacy:field:' . $name;
            }
            $collection->add_database_table($table, $descriptions, 'privacy:table:' . $names[$table]);
        }
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        return $collection;
    }

    /**
     * Find the single system context when the user appears in the support tables.
     *
     * @param int $userid Moodle user ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        foreach (self::USERS as $table => $columns) {
            foreach ($columns as $column) {
                if ($DB->record_exists($table, [$column => $userid])) {
                    $list->add_system_context();
                    return $list;
                }
            }
        }
        return $list;
    }

    /**
     * Identify users represented in the system records.
     *
     * @param userlist $userlist Requested context
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::USERS as $table => $columns) {
            foreach ($columns as $column) {
                $userlist->add_from_sql($column, "SELECT DISTINCT $column FROM {{$table}} WHERE $column > 0", []);
            }
        }
    }

    /**
     * Export own requests, messages and operator metadata. Other people's staff notes stay private.
     *
     * @param approved_contextlist $contextlist Approved user contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $output = writer::with_context($context);
            $root = [access::text('pluginname')];
            $tickets = $DB->get_recordset_select(
                storage::TICKET,
                'requesterid = :owner OR assigneeid = :staff',
                ['owner' => $userid, 'staff' => $userid]
            );
            try {
                foreach ($tickets as $ticket) {
                    if ($ticket->requesterid == $userid) {
                        unset($ticket->assigneeid);
                        $output->export_data([...$root, 'requests', (string)$ticket->id], $ticket);
                    } else {
                        $output->export_data([...$root, 'assignments', (string)$ticket->id], (object)[
                            'id' => $ticket->id, 'assigneeid' => $userid, 'statusid' => $ticket->statusid,
                        ]);
                    }
                }
            } finally {
                $tickets->close();
            }
            $records = $DB->get_recordset(storage::POST, ['userid' => $userid], 'id ASC');
            try {
                foreach ($records as $post) {
                    $path = [...$root, 'messages', (string)$post->id];
                    $output->export_data($path, $post);
                    $output->export_area_files($path, access::COMPONENT, 'attachment', $post->id);
                }
            } finally {
                $records->close();
            }
            $changes = $DB->get_recordset_select(
                storage::AUDIT,
                'userid = :actor OR formerassignee = :fromid OR newassignee = :toid',
                ['actor' => $userid, 'fromid' => $userid, 'toid' => $userid]
            );
            try {
                foreach ($changes as $change) {
                    $output->export_data([...$root, 'audit', (string)$change->id], (object)[
                        'id' => $change->id, 'ticketid' => $change->ticketid, 'action' => $change->action,
                        'timecreated' => $change->timecreated, 'actor' => $change->userid == $userid,
                        'formerassignee' => $change->formerassignee == $userid, 'newassignee' => $change->newassignee == $userid,
                    ]);
                }
            } finally {
                $changes->close();
            }
            foreach ($DB->get_records(storage::OUTBOX, ['userid' => $userid]) as $entry) {
                $output->export_data([...$root, 'notifications', (string)$entry->id], $entry);
            }
        }
    }

    /**
     * Erase ticket content, areas and operational records in the approved system context.
     *
     * @param context $context Approved context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $records = $DB->get_recordset(storage::TICKET, [], 'id ASC', 'id');
        try {
            foreach ($records as $record) {
                storage::locked('ticket:' . $record->id, static function () use ($record): void {
                    storage::delete((int)$record->id);
                });
            }
        } finally {
            $records->close();
        }
        $DB->delete_records(storage::AUDIT, ['ticketid' => 0]);
    }

    /**
     * Erase one approved user's support data.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase([(int)$contextlist->get_user()->id]);
                return;
            }
        }
    }

    /**
     * Erase users approved for the Helpdesk system records.
     *
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            self::erase($userlist->get_userids());
        }
    }

    /**
     * Purge requester tickets and remove other author or technician associations.
     *
     * @param array $users Approved IDs
     */
    private static function erase(array $users): void {
        global $DB;
        foreach (array_chunk($users, 300) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'erase');
            $records = $DB->get_recordset_select(storage::TICKET, "requesterid $in", $params, 'id ASC', 'id');
            try {
                foreach ($records as $record) {
                    storage::locked('ticket:' . $record->id, static function () use ($record): void {
                        storage::delete((int)$record->id);
                    });
                }
            } finally {
                $records->close();
            }
            $posts = $DB->get_recordset_select(storage::POST, "userid $in", $params, 'id ASC', 'id,ticketid');
            try {
                foreach ($posts as $post) {
                    storage::locked('ticket:' . $post->ticketid, static function () use ($DB, $post): void {
                        storage::transaction(static function () use ($DB, $post): void {
                            $context = \context_system::instance();
                            get_file_storage()->delete_area_files($context->id, access::COMPONENT, 'attachment', $post->id);
                            $DB->update_record(storage::POST, ['id' => $post->id, 'userid' => 0, 'body' => '']);
                        });
                    });
                }
            } finally {
                $posts->close();
            }
            $DB->set_field_select(storage::TICKET, 'assigneeid', 0, "assigneeid $in", $params);
            foreach (self::USERS[storage::AUDIT] as $column) {
                $DB->set_field_select(storage::AUDIT, $column, 0, "$column $in", $params);
            }
            $DB->delete_records_select(storage::OUTBOX, "userid $in", $params);
        }
    }
}
