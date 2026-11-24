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

namespace mod_skillupsattendance\privacy;

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
use mod_skillupsattendance\activity;
use mod_skillupsattendance\store;

/**
 * Discover, export and erase personal data in its native activity context.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Personal associations and whether their activity is reached through a session. */
    private const ROLES = [
        store::SESSION => ['session', false, ['teacherid']],
        store::MARK => ['mark', true, ['userid']],
        store::JUST => ['just', true, ['userid', 'reviewer']],
        store::AUDIT => ['audit', false, ['userid', 'actorid']],
        store::ALERT => ['alert', false, ['userid']],
        store::FAIL => ['fail', true, ['userid']],
    ];

    /**
     * Declare the stored attendance data, evidence and messaging subsystem.
     *
     * @param collection $collection Metadata
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [
            'session' => ['groupid', 'name', 'starts', 'ends', 'modality'],
            'mark' => ['sessionid', 'state', 'timein', 'timeexit', 'source', 'modified'],
            'just' => ['sessionid', 'reason', 'statement', 'response', 'state', 'timecreated', 'timereviewed'],
            'audit' => ['sessionid', 'operation', 'beforestate', 'afterstate', 'timecreated'],
            'alert' => ['rulekey', 'active', 'modified'],
            'fail' => ['sessionid', 'failures', 'expires'],
        ];
        foreach (self::ROLES as $table => [$label, , $roles]) {
            $entries = [];
            foreach ([...$roles, ...$fields[$label]] as $field) {
                $entries[$field] = 'privacy:field:' . $field;
            }
            $collection->add_database_table($table, $entries, 'privacy:table:' . $label);
        }
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        $collection->add_subsystem_link('core_message', [], 'privacy:messages');
        return $collection;
    }

    /**
     * Find every activity context containing the subject's attendance data.
     *
     * @param int $userid Subject
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        if ($userid <= 0) {
            return $list;
        }
        foreach (self::ROLES as $table => [, $indirect, $roles]) {
            [$source, $instance] = self::source($table, $indirect);
            [$predicate, $params] = self::predicate($roles, $userid);
            $sql = "SELECT c.id FROM {context} c JOIN {course_modules} cm ON cm.id = c.instanceid
                JOIN {modules} md ON md.id = cm.module WHERE c.contextlevel = :modlevel AND md.name = :modname
                AND EXISTS (SELECT 1 FROM $source WHERE $instance = cm.instance AND $predicate)";
            $list->add_from_sql($sql, $params + ['modlevel' => CONTEXT_MODULE, 'modname' => 'skillupsattendance']);
        }
        return $list;
    }

    /**
     * Find users represented in the specified module scope.
     *
     * @param userlist $userlist Approved-context list request
     */
    public static function get_users_in_context(userlist $userlist): void {
        $instance = self::instance($userlist->get_context());
        if (!$instance) {
            return;
        }
        foreach (self::ROLES as $table => [, $indirect, $roles]) {
            [$source, $scope] = self::source($table, $indirect);
            foreach ($roles as $field) {
                $sql = "SELECT DISTINCT d.$field AS userid FROM $source WHERE $scope = :instance AND d.$field > 0";
                $userlist->add_from_sql('userid', $sql, ['instance' => $instance]);
            }
        }
    }

    /**
     * Export records authorized for this subject, their evidence and their own decisions.
     *
     * @param approved_contextlist $contextlist Authorized module contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instance = self::instance($context);
            if (!$instance || $userid <= 0) {
                continue;
            }
            foreach (self::ROLES as $table => [$label, $indirect, $roles]) {
                [$source, $scope] = self::source($table, $indirect);
                [$predicate, $params] = self::predicate($roles, $userid);
                $rows = $DB->get_recordset_sql(
                    "SELECT d.* FROM $source WHERE $scope = :instance AND $predicate ORDER BY d.id",
                    $params + ['instance' => $instance],
                );
                try {
                    foreach ($rows as $row) {
                        $owns = isset($row->userid) && (int)$row->userid === $userid;
                        if (!$owns) {
                            unset($row->statement, $row->reason);
                        }
                        foreach ($roles as $field) {
                            if ((int)$row->$field !== $userid) {
                                unset($row->$field);
                            }
                        }
                        unset($row->pin, $row->qrkey, $row->serieskey);
                        $path = [activity::text('pluginname'), activity::text('privacy:table:' . $label), (string)$row->id];
                        $writer = writer::with_context($context);
                        $writer->export_data($path, $row);
                        if ($table === store::JUST && $owns) {
                            $writer->export_area_files($path, activity::C, 'evidence', $row->id);
                        }
                    }
                } finally {
                    $rows->close();
                }
            }
        }
    }

    /**
     * Remove all personal records in an authorized activity while preserving its schedule.
     *
     * @param context $context Module context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        self::erase($context, null);
    }

    /**
     * Remove a user's own attendance data and identifying operator references.
     *
     * @param approved_contextlist $contextlist Authorization
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::erase($context, (int)$contextlist->get_user()->id);
        }
    }

    /**
     * Remove data for the approved users in the listed activity.
     *
     * @param approved_userlist $userlist Authorization
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            self::erase($userlist->get_context(), (int)$userid);
        }
    }

    /**
     * Resolve only a context belonging to this installed Moodle activity type.
     *
     * @param context $context Candidate context
     * @return int Attendance instance or zero
     */
    private static function instance(context $context): int {
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return 0;
        }
        $cm = get_coursemodule_from_id('skillupsattendance', $context->instanceid);
        return $cm ? (int)$cm->instance : 0;
    }

    /**
     * Delete scoped evidence and anonymize historic operator references.
     *
     * @param context $context Activity context
     * @param int|null $userid Subject or all users
     */
    private static function erase(context $context, ?int $userid): void {
        global $DB;
        $instance = self::instance($context);
        if (!$instance || ($userid !== null && $userid <= 0)) {
            return;
        }
        store::atomic($instance, static function () use ($DB, $context, $instance, $userid): void {
            $scope = $userid === null ? [] : ['userid' => $userid];
            $sessions = $DB->get_fieldset_select(store::SESSION, 'id', 'attendanceid = :activity', ['activity' => $instance]);
            foreach (array_chunk($sessions, 400) as $chunk) {
                [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'session');
                $where = "sessionid $in";
                if ($userid !== null) {
                    $where .= ' AND userid = :userid';
                    $params += $scope;
                }
                foreach ($DB->get_fieldset_select(store::JUST, 'id', $where, $params) as $id) {
                    get_file_storage()->delete_area_files($context->id, activity::C, 'evidence', $id);
                }
                foreach ([store::MARK, store::FAIL, store::JUST] as $table) {
                    $DB->delete_records_select($table, $where, $params);
                }
                if ($userid !== null) {
                    $review = "sessionid $in AND reviewer = :userid";
                    $DB->set_field_select(store::JUST, 'response', null, $review, $params);
                    $DB->set_field_select(store::JUST, 'reviewer', 0, $review, $params);
                }
            }
            $activity = ['attendanceid' => $instance];
            $DB->delete_records(store::AUDIT, $activity + $scope);
            $DB->delete_records(store::ALERT, $activity + $scope);
            $DB->set_field(store::SESSION, 'teacherid', 0, $activity + ($userid === null ? [] : ['teacherid' => $userid]));
            if ($userid !== null) {
                $DB->set_field(store::AUDIT, 'actorid', 0, $activity + ['actorid' => $userid]);
            }
        });
    }

    /**
     * Build a safe scope join using declared table constants.
     *
     * @param string $table Local table
     * @param bool $indirect Whether the table references a session
     * @return array
     */
    private static function source(string $table, bool $indirect): array {
        if (!$indirect) {
            return ["{{$table}} d", 'd.attendanceid'];
        }
        $sessions = store::SESSION;
        return ["{{$table}} d JOIN {{$sessions}} s ON s.id = d.sessionid", 's.attendanceid'];
    }

    /**
     * Build a personal-data filter with bound parameters.
     *
     * @param array $roles Identity fields
     * @param int $userid Target subject
     * @return array
     */
    private static function predicate(array $roles, int $userid): array {
        $clauses = [];
        $params = [];
        foreach ($roles as $role) {
            $clauses[] = "d.$role = :$role";
            $params[$role] = $userid;
        }
        return ['(' . implode(' OR ', $clauses) . ')', $params];
    }
}
