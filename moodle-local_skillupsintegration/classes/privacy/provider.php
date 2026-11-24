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

namespace local_skillupsintegration\privacy;

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
use local_skillupsintegration\access;
use local_skillupsintegration\store;

/**
 * Provide Moodle Privacy API discovery and scoped access to integration data.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Personal associations by table. */
    private const ROLES = [
        store::SRC => ['ownerid', 'pushuserid'],
        store::RUN => ['actorid', 'approverid'],
        store::ITEM => ['userid'],
        store::LINK => ['targetid'],
        store::AUDIT => ['actorid'],
    ];

    /**
     * Declare plugin data and remote processing links.
     *
     * @param collection $collection Metadata
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $tables = [
            store::SRC => ['src', 'name', 'type', 'enabled', 'options'],
            store::RUN => ['run', 'sourceid', 'origin', 'state', 'requestkey', 'requesthash', 'continuation', 'timecreated'],
            store::ITEM => ['item', 'runid', 'externalkey', 'payload', 'state', 'errorcode', 'subject'],
            store::LINK => ['link', 'sourceid', 'entity', 'externalkey', 'finger'],
            store::AUDIT => ['audit', 'sourceid', 'runid', 'action', 'timecreated'],
        ];
        foreach ($tables as $table => $labels) {
            $name = array_shift($labels);
            $fields = [];
            foreach ([...self::ROLES[$table], ...$labels] as $field) {
                $fields[$field] = 'privacy:field:' . $field;
            }
            $collection->add_database_table($table, $fields, 'privacy:table:' . $name);
        }
        $collection->add_external_location_link('providers', [], 'privacy:external');
        return $collection;
    }

    /**
     * List approved-scope candidates with an operational identity or staged user data.
     *
     * @param int $userid Moodle user
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        if ($userid <= 0) {
            return $list;
        }
        foreach (self::ROLES as $table => $fields) {
            [$where, $params] = self::selection($table, $fields, $userid);
            $list->add_from_sql(
                "SELECT c.id FROM {context} c WHERE c.contextlevel = :scope AND EXISTS
                    (SELECT 1 FROM {{$table}} d WHERE $where)",
                $params + ['scope' => CONTEXT_SYSTEM],
            );
        }
        return $list;
    }

    /**
     * Identify users associated with system-context records.
     *
     * @param userlist $userlist Scope and results
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::ROLES as $table => $fields) {
            foreach ($fields as $field) {
                $where = $table === store::LINK ? "$field > 0 AND entity = :entity" : "$field > 0";
                $params = $table === store::LINK ? ['entity' => 'user'] : [];
                $userlist->add_from_sql($field, "SELECT DISTINCT $field FROM {{$table}} WHERE $where", $params);
            }
        }
    }

    /**
     * Export only the subject's personal associations and own staged account data.
     *
     * @param approved_contextlist $contextlist Authorized contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM || $userid <= 0) {
                continue;
            }
            foreach (self::ROLES as $table => $fields) {
                [$where, $params] = self::selection($table, $fields, $userid);
                $scope = [
                    store::SRC => ['src', 'name', 'type', 'enabled'],
                    store::RUN => ['run', 'sourceid', 'origin', 'state', 'timecreated'],
                    store::ITEM => ['item', 'runid', 'entity', 'state', 'payload'],
                    store::LINK => ['link', 'sourceid', 'entity'],
                    store::AUDIT => ['audit', 'sourceid', 'runid', 'action', 'timecreated'],
                ][$table];
                $section = array_shift($scope);
                $records = $DB->get_recordset_select($table, $where, $params, 'id ASC');
                try {
                    foreach ($records as $record) {
                        $safe = array_intersect_key((array)$record, array_flip($scope));
                        foreach ($fields as $field) {
                            if ((int)$record->$field === $userid) {
                                $safe[$field] = $userid;
                            }
                        }
                        writer::with_context($context)->export_data(
                            [access::text('pluginname'), access::text('privacy:table:' . $section), (string)$record->id],
                            (object)$safe,
                        );
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Delete all system-scope plugin data and disable all related credentials.
     *
     * @param context $context Approved context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        self::exclusive(static function (): void {
            global $DB;
            foreach ([store::ITEM, store::RUN, store::LINK, store::AUDIT] as $table) {
                $DB->delete_records($table);
            }
            $sources = store::SRC;
            $DB->execute("UPDATE {{$sources}} SET enabled = 0, webhook = 0, ownerid = 0, pushuserid = 0,
                revision = revision + 1");
        });
    }

    /**
     * Remove personal records within the authorized Moodle system context.
     *
     * @param approved_contextlist $contextlist Authorized scope
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Remove personal records for multiple approved system-scope users.
     *
     * @param approved_userlist $userlist Authorized subjects
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase((int)$userid);
            }
        }
    }

    /**
     * Remove staged data and strip traceable operator identifiers.
     *
     * @param int $userid Approved subject
     */
    private static function erase(int $userid): void {
        if ($userid <= 0) {
            return;
        }
        self::exclusive(static function () use ($userid): void {
            global $DB;
            $DB->delete_records(store::ITEM, ['userid' => $userid]);
            $DB->delete_records(store::LINK, ['targetid' => $userid, 'entity' => 'user']);
            $DB->set_field(store::AUDIT, 'actorid', 0, ['actorid' => $userid]);
            $sources = store::SRC;
            $DB->execute("UPDATE {{$sources}} SET ownerid = 0, webhook = 0, revision = revision + 1
                WHERE ownerid = :userid", ['userid' => $userid]);
            $DB->execute("UPDATE {{$sources}} SET pushuserid = 0, revision = revision + 1
                WHERE pushuserid = :userid", ['userid' => $userid]);
            $runs = store::RUN;
            foreach (['actorid', 'approverid'] as $field) {
                $DB->execute(
                    "UPDATE {{$runs}} SET state = :state, continuation = NULL
                    WHERE $field = :userid AND state IN ('queued', 'ready', 'applying')",
                    ['state' => 'superseded', 'userid' => $userid]
                );
                $DB->set_field($runs, $field, 0, [$field => $userid]);
            }
        });
    }

    /**
     * Serialize erasure with stage and target mutations.
     *
     * @param callable $operation Database changes
     */
    private static function exclusive(callable $operation): void {
        store::transaction($operation);
    }

    /**
     * Construct a scoped predicate for database personal data.
     *
     * @param string $table Owned table
     * @param array $fields User fields
     * @param int $userid Subject
     * @return array
     */
    private static function selection(string $table, array $fields, int $userid): array {
        $params = [];
        $clauses = [];
        foreach ($fields as $field) {
            $clauses[] = "$field = :$field";
            $params[$field] = $userid;
        }
        $where = '(' . implode(' OR ', $clauses) . ')';
        return $table === store::LINK ? ["$where AND entity = :entity", $params + ['entity' => 'user']] : [$where, $params];
    }
}
