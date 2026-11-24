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

namespace local_skillupsexam\privacy;

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
use local_skillupsexam\scope;
use local_skillupsexam\store;

/**
 * Export and remove personal data in approved Moodle course contexts.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadataprovider, requestprovider {
    /** @var array Participant and actor references. */
    private const USERS = [
        store::EXAM => ['teacherid', 'supervisor'],
        store::SEAT => ['userid'],
        store::MARK => ['userid', 'actorid'],
        store::INCIDENT => ['relatedid', 'authorid'],
        store::AUDIT => ['subjectid', 'actorid'],
        store::QUEUE => ['cursorid'],
    ];
    /** @var array Other potentially personal data by table. */
    private const DETAILS = [
        store::EXAM => ['instruction'],
        store::MARK => ['status', 'note', 'marked'],
        store::INCIDENT => ['severity', 'details', 'created'],
        store::AUDIT => ['action', 'reason', 'created', 'beforedata', 'afterdata'],
    ];

    /**
     * Declare stored data and Moodle messaging.
     *
     * @param collection $collection Moodle metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::USERS as $table => $fields) {
            $keys = array_merge($fields, self::DETAILS[$table] ?? []);
            $labels = array_combine($keys, array_map(static fn($key) => 'privacy:field:' . $key, $keys));
            $collection->add_database_table($table, $labels, 'privacy:table:' . substr($table, strlen(store::EXAM) - 4));
        }
        $collection->add_subsystem_link('core_message', [], 'privacy:message');
        return $collection;
    }

    /**
     * Build a course-scoped relation for the selected table.
     *
     * @param string $table Owned table name
     * @return array SQL source and course field
     */
    private static function source(string $table): array {
        return $table === store::EXAM ? ['{' . $table . '} r', 'r.courseid'] :
            ['{' . $table . '} r JOIN {' . store::EXAM . '} e ON e.id = r.examid', 'e.courseid'];
    }

    /**
     * Look up all course contexts containing the requested user's data.
     *
     * @param int $userid Moodle user ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::USERS as $table => $keys) {
            [$source, $course] = self::source($table);
            foreach ($keys as $key) {
                $list->add_from_sql("SELECT DISTINCT c.id FROM $source JOIN {context} c ON c.instanceid = $course
                    WHERE c.contextlevel = :level AND r.$key = :userid", ['level' => CONTEXT_COURSE, 'userid' => $userid]);
            }
        }
        return $list;
    }

    /**
     * Find owners and contributors in the approved course.
     *
     * @param userlist $userlist Collection for the selected context
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        foreach (self::USERS as $table => $keys) {
            [$source, $course] = self::source($table);
            foreach ($keys as $key) {
                $userlist->add_from_sql(
                    $key,
                    "SELECT DISTINCT r.$key FROM $source WHERE $course = :course AND r.$key > 0",
                    ['course' => $context->instanceid]
                );
            }
        }
    }

    /**
     * Stream records in a single course involving approved user IDs.
     *
     * @param string $table Owned table
     * @param int $courseid Moodle course ID
     * @param int[] $users Privacy subjects
     * @return \moodle_recordset
     */
    private static function records(string $table, int $courseid, array $users): \moodle_recordset {
        global $DB;
        [$source, $course] = self::source($table);
        $conditions = [];
        $params = ['courseid' => $courseid];
        foreach (self::USERS[$table] as $key) {
            [$sql, $arguments] = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED, $key);
            $conditions[] = "r.$key $sql";
            $params += $arguments;
        }
        return $DB->get_recordset_sql(
            "SELECT r.* FROM $source WHERE $course = :courseid AND (" . implode(' OR ', $conditions) . ') ORDER BY r.id',
            $params,
        );
    }

    /**
     * Export records describing the user's participation without including another user's ID.
     *
     * @param approved_contextlist $contextlist Approved user and contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            foreach (self::USERS as $table => $keys) {
                $rows = self::records($table, $context->instanceid, [$userid]);
                try {
                    foreach ($rows as $row) {
                        $data = ['examid' => $row->examid ?? $row->id];
                        foreach ($keys as $key) {
                            $data[$key] = $row->$key == $userid;
                        }
                        if ($table === store::EXAM) {
                            $data += ['name' => $row->name, 'starts' => $row->starts, 'ends' => $row->ends];
                        } else if ($table === store::MARK && $row->userid == $userid) {
                            $data += ['status' => $row->status, 'marked' => $row->marked, 'note' => $row->note];
                        } else if ($table === store::INCIDENT && $row->relatedid == $userid) {
                            $data += ['severity' => $row->severity, 'created' => $row->created, 'details' => $row->details];
                        } else if ($table === store::AUDIT) {
                            $data += ['action' => $row->action, 'created' => $row->created];
                            if (!$row->subjectid && $row->actorid == $userid) {
                                $data['reason'] = $row->reason;
                            }
                        }
                        $path = [scope::text('pluginname'), $table, (string)$row->id];
                        writer::with_context($context)->export_data($path, (object)$data);
                    }
                } finally {
                    $rows->close();
                }
            }
        }
    }

    /**
     * Remove all plugin records for the approved context.
     *
     * @param context $context Moodle course context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel === CONTEXT_COURSE) {
            store::remove_course($context->instanceid);
        }
    }

    /**
     * Erase an approved user's data.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::erase($context, [(int)$contextlist->get_user()->id]);
        }
    }

    /**
     * Erase data for an approved list of users.
     *
     * @param approved_userlist $userlist Approved course and users
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::erase($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Remove participant records and unlink actors while retaining structural examination records.
     *
     * @param context $context Approved course context
     * @param int[] $users Approved users
     */
    private static function erase(context $context, array $users): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE || !$users) {
            return;
        }
        store::write(static function () use ($DB, $context, $users): void {
            foreach (array_chunk(array_map('intval', $users), 200) as $chunk) {
                foreach (self::USERS as $table => $keys) {
                    $rows = self::records($table, $context->instanceid, $chunk);
                    try {
                        foreach ($rows as $row) {
                            if (in_array($table, [store::SEAT, store::MARK], true) && in_array((int)$row->userid, $chunk, true)) {
                                $DB->delete_records($table, ['id' => $row->id]);
                                continue;
                            }
                            $data = ['id' => $row->id];
                            foreach ($keys as $key) {
                                if (in_array((int)$row->$key, $chunk, true)) {
                                    $data[$key] = 0;
                                }
                            }
                            $extra = match ($table) {
                                store::EXAM => ['instruction' => null],
                                store::MARK => ['note' => null],
                                store::INCIDENT => ['details' => null],
                                store::AUDIT => ['reason' => null, 'beforedata' => null, 'afterdata' => null],
                                store::QUEUE => ['status' => 'obsolete'],
                                default => [],
                            };
                            $DB->update_record($table, (object)($data + $extra));
                        }
                    } finally {
                        $rows->close();
                    }
                }
            }
        });
    }
}
