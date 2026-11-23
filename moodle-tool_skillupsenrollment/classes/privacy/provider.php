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

namespace tool_skillupsenrollment\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_skillupsenrollment\jobs;
use tool_skillupsenrollment\support;

/**
 * Manage operator and participant data stored by the enrollment ledger.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Declare plugin-owned data and the native enrollment subsystem.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(jobs::JOB, [
            'userid' => 'privacy:operator', 'timecreated' => 'privacy:time',
        ], 'privacy:job');
        $collection->add_database_table(jobs::ROW, [
            'userid' => 'privacy:participant', 'action' => 'privacy:detail',
            'courseid' => 'privacy:detail', 'roleid' => 'privacy:detail', 'groupid' => 'privacy:detail',
            'payload' => 'privacy:detail', 'timemodified' => 'privacy:time',
        ], 'privacy:row');
        $collection->add_subsystem_link('core_enrol', [], 'privacy:enrol');
        return $collection;
    }

    /**
     * Resolve a user's system context.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists(jobs::JOB, ['userid' => $userid]) || $DB->record_exists(jobs::ROW, ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Enumerate attributed operators and people in the ledger.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ([jobs::JOB, jobs::ROW] as $table) {
                $userlist->add_from_sql('userid', "SELECT DISTINCT userid FROM {{$table}} WHERE userid > 0", []);
            }
        }
    }

    /**
     * Export the approved user's own participation and job metadata.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $writer = writer::with_context($context);
            $path = [support::text('pluginname')];
            foreach ($DB->get_records(jobs::JOB, ['userid' => $userid], 'id', 'id,state,timecreated,runat') as $job) {
                $writer->export_data([...$path, 'operations', (string)$job->id], $job);
            }
            $fields = 'id,jobid,courseid,instanceid,roleid,groupid,action,state,errorcode,timemodified,payload';
            foreach ($DB->get_records(jobs::ROW, ['userid' => $userid], 'id', $fields) as $row) {
                $row->payload = json_decode($row->payload, true, 10, JSON_THROW_ON_ERROR);
                $writer->export_data([...$path, 'enrollments', (string)$row->id], $row);
            }
        }
    }

    /**
     * Erase all identifiers in the approved system context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            self::erase(null);
        }
    }

    /**
     * Erase one approved user's references.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase([(int)$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Erase approved users' references.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM && $userlist->get_userids()) {
            self::erase(array_map('intval', $userlist->get_userids()));
        }
    }

    /**
     * Cancel pending actions on erased identities without changing actual enrollments.
     */
    private static function erase(?array $users): void {
        global $DB;
        $lock = support::lock('workers', 15);
        if (!$lock) {
            throw new \moodle_exception('busy', support::C);
        }
        try {
            $tx = $DB->start_delegated_transaction();
            foreach ($users === null ? [null] : array_chunk($users, 500) as $chunk) {
                $where = 'userid > 0';
                $params = [];
                if ($chunk !== null) {
                    [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'owner');
                    $where = "userid $in";
                }
                $DB->set_field_select(jobs::JOB, 'cancelled', 1, "$where AND state IN ('draft', 'queued')", $params);
                $DB->set_field_select(jobs::JOB, 'userid', 0, $where, $params);
                $DB->set_field_select(jobs::ROW, 'state', 'cancelled', "$where AND state IN ('pending', 'failed')", $params);
                $DB->set_field_select(jobs::ROW, 'payload', '{}', $where, $params);
                $DB->set_field_select(jobs::ROW, 'userid', 0, $where, $params);
            }
            $tx->allow_commit();
        } finally {
            $lock->release();
        }
    }
}
