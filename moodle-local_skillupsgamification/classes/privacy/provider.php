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

namespace local_skillupsgamification\privacy;

use context;
use core\task\manager;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupsgamification\access;
use local_skillupsgamification\store;
use local_skillupsgamification\task\award_completion;

/**
 * Course-scoped privacy export and erasure.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /** @var array Tables containing course participants and operators. */
    private const TABLES = [store::PLAYER => 'player', store::LEDGER => 'ledger', store::GRANT => 'grant', store::AUDIT => 'audit'];

    /**
     * Document personal data including the immutable award ledger and ranking choices.
     *
     * @param collection $collection Metadata
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [
            'player' => ['consent', 'excluded', 'alias'],
            'ledger' => ['ruleid', 'kind', 'sourceid', 'teamid', 'points', 'xp', 'timeearned'],
            'grant' => ['goalid', 'teamid', 'cost', 'state', 'timecreated', 'timefulfilled'],
            'audit' => ['action', 'targetid', 'timecreated'],
        ];
        foreach (self::TABLES as $table => $name) {
            $descriptions = [];
            foreach (['courseid', 'userid', ...$fields[$name]] as $field) {
                $descriptions[$field] = 'privacy:field:' . $field;
            }
            $collection->add_database_table($table, $descriptions, 'privacy:table:' . $name);
        }
        $collection->add_subsystem_link('core_task', [], 'privacy:tasks');
        return $collection;
    }

    /**
     * Find contexts containing the learner or operator.
     *
     * @param int $userid Moodle user
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::TABLES as $table => $name) {
            $list->add_from_sql(
                "SELECT DISTINCT c.id FROM {context} c JOIN {{$table}} g ON g.courseid = c.instanceid
                  WHERE c.contextlevel = :level AND g.userid = :participant",
                ['level' => CONTEXT_COURSE, 'participant' => $userid]
            );
        }
        foreach (manager::get_adhoc_tasks(award_completion::class) as $task) {
            if ($task->get_userid() == $userid) {
                $context = \context_course::instance((int)$task->get_custom_data()->courseid, IGNORE_MISSING);
                if ($context) {
                    $list->add_from_sql('SELECT id FROM {context} WHERE id = :id', ['id' => $context->id]);
                }
            }
        }
        return $list;
    }

    /**
     * Identify positive user IDs belonging to the approved course.
     *
     * @param userlist $userlist Requested course users
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        foreach (self::TABLES as $table => $name) {
            $userlist->add_from_sql('userid', "SELECT DISTINCT userid FROM {{$table}} WHERE courseid = :id AND userid > 0", [
                'id' => $context->instanceid,
            ]);
        }
        foreach (manager::get_adhoc_tasks(award_completion::class) as $task) {
            if ($task->get_custom_data()->courseid == $context->instanceid && $task->get_userid()) {
                $userlist->add_user((int)$task->get_userid());
            }
        }
    }

    /**
     * Export the scoped personal records and queued evidence references.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        $tasks = manager::get_adhoc_tasks(award_completion::class, false, true);
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $root = [access::text('pluginname')];
            $output = writer::with_context($context);
            foreach (self::TABLES as $table => $name) {
                $criteria = ['courseid' => $context->instanceid, 'userid' => $userid];
                $records = $DB->get_recordset($table, $criteria, 'id ASC');
                try {
                    foreach ($records as $row) {
                        $output->export_data([...$root, $name, (string)$row->id], $row);
                    }
                } finally {
                    $records->close();
                }
            }
            foreach ($tasks as $task) {
                $reference = $task->get_custom_data();
                if ($reference->courseid == $context->instanceid && $task->get_userid() == $userid) {
                    $output->export_data([...$root, 'queued', (string)$task->get_id()], $reference);
                }
            }
        }
    }

    /**
     * Remove attribution and outstanding awards from the approved course.
     *
     * @param context $context Approved course context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel === CONTEXT_COURSE) {
            self::erase((int)$context->instanceid, null);
        }
    }

    /**
     * Erase one user's gamification records in approved courses.
     *
     * @param approved_contextlist $contextlist Approved user and contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                self::erase((int)$context->instanceid, [(int)$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Erase all approved users in one course.
     *
     * @param approved_userlist $userlist Approved people
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE && $userlist->get_userids()) {
            self::erase((int)$context->instanceid, $userlist->get_userids());
        }
    }

    /**
     * Remove earned data, preserving anonymous counts of already delivered physical inventory.
     *
     * @param int $courseid Course ID
     * @param array|null $users User IDs, or all
     */
    private static function erase(int $courseid, ?array $users): void {
        global $DB;
        store::change($courseid, static function () use ($DB, $courseid, $users): void {
            foreach (manager::get_adhoc_tasks(award_completion::class) as $task) {
                if ($task->get_custom_data()->courseid == $courseid && ($users === null || in_array($task->get_userid(), $users))) {
                    manager::delete_adhoc_task((int)$task->get_id());
                }
            }
            foreach ($users === null ? [null] : array_chunk($users, 300) as $chunk) {
                $criteria = 'courseid = :course AND userid > 0';
                $params = ['course' => $courseid];
                if ($chunk !== null) {
                    [$in, $extra] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'erase');
                    $criteria .= " AND userid $in";
                    $params += $extra;
                }
                $grants = $DB->get_recordset_select(store::GRANT, "$criteria AND state = :done", $params + ['done' => 'fulfilled']);
                try {
                    foreach ($grants as $grant) {
                        $DB->update_record(store::GRANT, ['id' => $grant->id, 'userid' => -(int)$grant->id]);
                    }
                } finally {
                    $grants->close();
                }
                foreach ([store::PLAYER, store::LEDGER, store::GRANT] as $table) {
                    $DB->delete_records_select($table, $criteria, $params);
                }
                $DB->set_field_select(store::AUDIT, 'userid', 0, $criteria, $params);
            }
        });
    }
}
