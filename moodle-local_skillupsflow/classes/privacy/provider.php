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

namespace local_skillupsflow\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupsflow\definition;
use local_skillupsflow\store;

/**
 * Site-context privacy for workflow authors, targets and reviewers.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Tables and account fields used for GDPR discovery.
     */
    private const USERS = [
        'local_skillupsflow_ver' => 'userid', 'local_skillupsflow_target' => 'userid',
        'local_skillupsflow_run' => 'userid', 'local_skillupsflow_log' => 'actorid',
    ];

    /**
     * Declare the data associated with a workflow.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_skillupsflow_ver', [
            'userid' => 'privacy:userid', 'definition' => 'privacy:definition', 'timecreated' => 'privacy:time',
        ], 'privacy:version');
        $collection->add_database_table('local_skillupsflow_target', ['userid' => 'privacy:userid'], 'privacy:flow');
        $collection->add_database_table('local_skillupsflow_run', [
            'userid' => 'privacy:userid', 'courseid' => 'privacy:course', 'status' => 'privacy:status',
            'timecreated' => 'privacy:time',
        ], 'privacy:run');
        $collection->add_database_table('local_skillupsflow_log', [
            'actorid' => 'privacy:actor', 'outcome' => 'privacy:outcome', 'timecreated' => 'privacy:time',
        ], 'privacy:log');
        $collection->add_subsystem_link('core_message', [], 'messageprovider:workflow');
        return $collection;
    }

    /**
     * All personal workflow data resides at system level.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $context = new contextlist();
        foreach (self::USERS as $table => $field) {
            if ($DB->record_exists($table, [$field => $userid])) {
                $context->add_system_context();
                break;
            }
        }
        return $context;
    }

    /**
     * Enumerate users referenced by site-level workflow configuration or history.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::USERS as $table => $field) {
            $userlist->add_from_sql($field, "SELECT DISTINCT $field FROM {{$table}} WHERE $field > 0", []);
        }
    }

    /**
     * Export only the approved user's role or target relationship for each record.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            foreach (self::USERS as $table => $field) {
                $records = $DB->get_recordset($table, [$field => $userid], 'id');
                try {
                    foreach ($records as $row) {
                        $export = ['id' => $row->id, $field => $userid];
                        foreach (['flowid', 'versionid', 'revision', 'runid', 'courseid', 'status', 'outcome'] as $item) {
                            if (isset($row->$item)) {
                                $export[$item] = $row->$item;
                            }
                        }
                        if (isset($row->timecreated)) {
                            $export['created'] = transform::datetime($row->timecreated);
                        }
                        writer::with_context($context)->export_data(
                            ['SkillUps Flow Automation', $table, (string)$row->id],
                            (object)$export
                        );
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Delete all personal workflow data from its system context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $flows = $DB->get_records('local_skillupsflow_flow', null, 'id', 'id');
        foreach ($flows as $flow) {
            $lock = store::lock($flow->id, 30);
            try {
                $transaction = $DB->start_delegated_transaction();
                $DB->delete_records_select(
                    'local_skillupsflow_log',
                    'runid IN (SELECT id FROM {local_skillupsflow_run} WHERE flowid = ?)',
                    [$flow->id]
                );
                $DB->delete_records('local_skillupsflow_run', ['flowid' => $flow->id]);
                $DB->delete_records_select(
                    'local_skillupsflow_target',
                    'versionid IN (SELECT id FROM {local_skillupsflow_ver} WHERE flowid = ?)',
                    [$flow->id]
                );
                $DB->delete_records('local_skillupsflow_ver', ['flowid' => $flow->id]);
                $DB->delete_records('local_skillupsflow_flow', ['id' => $flow->id]);
                $transaction->allow_commit();
            } finally {
                $lock->release();
            }
        }
    }

    /**
     * Erase personal associations for an approved account.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Erase accounts approved for system-context deletion.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase((int)$userid);
            }
        }
    }

    /**
     * Redact fixed targets and revoke revisions to prevent substitution into timed runs.
     */
    private static function erase(int $userid): void {
        global $DB;
        $sql = "SELECT DISTINCT v.id, v.flowid
                  FROM {local_skillupsflow_ver} v
             LEFT JOIN {local_skillupsflow_target} t ON t.versionid = v.id AND t.userid = :target
                 WHERE v.userid = :author OR t.id IS NOT NULL";
        foreach ($DB->get_records_sql($sql, ['target' => $userid, 'author' => $userid]) as $item) {
            $lock = store::lock($item->flowid, 30);
            try {
                if (!$version = $DB->get_record('local_skillupsflow_ver', ['id' => $item->id])) {
                    continue;
                }
                $graph = definition::parse($version->definition);
                if ($graph['trigger']['userid'] === $userid) {
                    $graph['trigger']['userid'] = 0;
                }
                foreach ($graph['nodes'] as &$node) {
                    if ($node['userid'] === $userid) {
                        $node['userid'] = 0;
                    }
                }
                unset($node);
                $tx = $DB->start_delegated_transaction();
                $DB->update_record('local_skillupsflow_ver', (object)[
                    'id' => $item->id, 'userid' => (int)$version->userid === $userid ? 0 : $version->userid,
                    'revoked' => 1, 'definition' => json_encode($graph, JSON_THROW_ON_ERROR),
                ]);
                $DB->delete_records('local_skillupsflow_target', ['versionid' => $item->id, 'userid' => $userid]);
                $DB->set_field('local_skillupsflow_flow', 'nextdue', 0, ['active' => $item->id]);
                $DB->set_field('local_skillupsflow_flow', 'active', 0, ['active' => $item->id]);
                self::redact_runs('versionid', $item->id);
                $tx->allow_commit();
            } finally {
                $lock->release();
            }
        }
        $flowids = $DB->get_fieldset_select('local_skillupsflow_run', 'DISTINCT flowid', 'userid = ?', [$userid]);
        foreach ($flowids as $flowid) {
            $lock = store::lock($flowid, 30);
            try {
                $tx = $DB->start_delegated_transaction();
                self::redact_runs('userid', $userid, $flowid);
                $DB->set_field('local_skillupsflow_run', 'userid', 0, ['userid' => $userid, 'flowid' => $flowid]);
                $tx->allow_commit();
            } finally {
                $lock->release();
            }
        }
        $DB->set_field('local_skillupsflow_log', 'actorid', 0, ['actorid' => $userid]);
    }

    /**
     * Cancel affected pending runs without restarting approved operations.
     */
    private static function redact_runs(string $field, int $value, ?int $flowid = null): void {
        global $DB;
        $sql = "$field = ? AND status IN (?, ?, ?)";
        $params = [$value, ...store::OPEN];
        if ($flowid !== null) {
            $sql .= ' AND flowid = ?';
            $params[] = $flowid;
        }
        $DB->set_field_select('local_skillupsflow_run', 'status', 'cancelled', $sql, $params);
    }
}
