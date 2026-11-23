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

namespace tool_skillupscoursefactory\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_skillupscoursefactory\jobs;
use tool_skillupscoursefactory\support;

/**
 * Export and erase operation ownership and pending enrollment references.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Declare operational identifiers.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(jobs::JOB, ['userid' => 'privacy:userid', 'timecreated' => 'privacy:time'], 'privacy:job');
        $collection->add_database_table(jobs::ROW, ['payload' => 'privacy:staff'], 'privacy:row');
        $collection->add_subsystem_link('core_enrol', [], 'privacy:enrol');
        return $collection;
    }

    /**
     * Find whether this operator or employee appears in operational metadata.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        [$where, $params] = self::match_staff($userid);
        if ($DB->record_exists(jobs::JOB, ['userid' => $userid]) || $DB->record_exists_select(jobs::ROW, $where, $params)) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Enumerate recorded operators and course employee references.
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid FROM {' . jobs::JOB . '} WHERE userid > 0', []);
        $rows = $DB->get_recordset_select(jobs::ROW, self::match_staff()[0], self::match_staff()[1], '', 'id,payload');
        try {
            foreach ($rows as $row) {
                $data = json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR);
                $userlist->add_users(array_column($data['staff'] ?? [], 'userid'));
            }
        } finally {
            $rows->close();
        }
    }

    /**
     * Export only actor-owned job metadata and the employee's own assignment references.
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
            [$where, $params] = self::match_staff($userid);
            $rows = $DB->get_recordset_select(jobs::ROW, $where, $params, 'id ASC', 'id,jobid,courseid,payload');
            try {
                foreach ($rows as $row) {
                    $data = json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR);
                    $roles = [];
                    foreach ($data['staff'] ?? [] as $staff) {
                        if ($staff['userid'] === $userid) {
                            $roles[] = $staff['role'];
                        }
                    }
                    if ($roles) {
                        $writer->export_data([...$path, 'assignments', (string)$row->id], (object)[
                            'jobid' => $row->jobid, 'courseid' => $row->courseid, 'roles' => $roles,
                        ]);
                    }
                }
            } finally {
                $rows->close();
            }
        }
    }

    /**
     * Remove personal metadata in the system context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            self::erase(null);
        }
    }

    /**
     * Remove references for the approved person.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Remove references for the approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase((int)$userid);
            }
        }
    }

    /**
     * Remove owner and staff references without mutating provisioned courses.
     */
    private static function erase(?int $userid): void {
        global $DB;
        $lock = support::lock('workers', 15);
        if (!$lock) {
            throw new \moodle_exception('busy', support::C);
        }
        try {
            $where = $userid === null ? 'userid > 0' : 'userid = :owner';
            $params = $userid === null ? [] : ['owner' => $userid];
            $DB->set_field_select(jobs::JOB, 'cancelled', 1, "$where AND state IN ('draft', 'queued')", $params);
            $DB->set_field_select(jobs::JOB, 'userid', 0, $where, $params);
            [$where, $params] = self::match_staff($userid);
            $rows = $DB->get_recordset_select(jobs::ROW, $where, $params, 'id ASC', 'id,payload,state');
            try {
                foreach ($rows as $row) {
                    $data = json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR);
                    $remaining = $userid === null ? [] : array_filter(
                        $data['staff'] ?? [],
                        static fn($item) => $item['userid'] !== $userid
                    );
                    if (count($remaining) === count($data['staff'] ?? [])) {
                        continue;
                    }
                    $data['staff'] = array_values($remaining);
                    $edit = ['id' => $row->id, 'payload' => support::json($data)];
                    if (in_array($row->state, ['pending', 'failed'], true)) {
                        $edit += ['state' => 'cancelled', 'errorcode' => 'cancelledrow'];
                    }
                    $DB->update_record(jobs::ROW, $edit);
                }
            } finally {
                $rows->close();
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Narrow metadata scans to stored numeric enrollment IDs.
     */
    private static function match_staff(?int $userid = null): array {
        global $DB;
        $needle = '"userid":' . ($userid === null ? '' : $userid . ',');
        return [$DB->sql_like('payload', ':staff'), ['staff' => '%' . $DB->sql_like_escape($needle) . '%']];
    }
}
