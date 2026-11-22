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

namespace report_skillupsquality\privacy;

use core\lock\lock_config;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use report_skillupsquality\scope;
use report_skillupsquality\store;

/**
 * Describe, export and erase data attributable to audit operators.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Declare the requesters and plan note authors.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(store::RUN, [
            'userid' => 'privacy:metadata:userid', 'timecreated' => 'privacy:metadata:time',
        ], 'privacy:metadata:run');
        $collection->add_database_table(store::PLAN, [
            'userid' => 'privacy:metadata:userid', 'timemodified' => 'privacy:metadata:time', 'notes' => 'privacy:metadata:notes',
        ], 'privacy:metadata:plan');
        return $collection;
    }

    /**
     * Resolve course contexts for a requester or note author.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contexts = new contextlist();
        foreach ([store::RUN, store::PLAN] as $table) {
            $contexts->add_from_sql(
                "SELECT c.id
                   FROM {context} c
                   JOIN {{$table}} r ON r.courseid = c.instanceid
                  WHERE c.contextlevel = :courselevel AND r.userid = :author",
                ['courselevel' => CONTEXT_COURSE, 'author' => $userid],
            );
        }
        return $contexts;
    }

    /**
     * Enumerate operator IDs inside the approved course context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE) {
            foreach ([store::RUN, store::PLAN] as $table) {
                $userlist->add_from_sql(
                    'userid',
                    "SELECT DISTINCT userid FROM {{$table}} WHERE courseid = :course AND userid > 0",
                    ['course' => $context->instanceid],
                );
            }
        }
    }

    /**
     * Export the user's audit requests and last-authored plans.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $target = ['courseid' => $context->instanceid, 'userid' => $userid];
            $writer = writer::with_context($context);
            $runs = $DB->get_records(store::RUN, $target, 'id', 'id,state,timecreated');
            foreach ($runs as $run) {
                $writer->export_data([scope::text('privacy:path'), 'audits', (string)$run->id], $run);
            }
            $plans = $DB->get_records(store::PLAN, $target, 'id', 'id,state,notes,due,timemodified');
            foreach ($plans as $plan) {
                $writer->export_data([scope::text('privacy:path'), 'plans', (string)$plan->id], $plan);
            }
        }
    }

    /**
     * Anonymize all operators in one approved course.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_COURSE) {
            self::erase((int)$context->instanceid, null);
        }
    }

    /**
     * Anonymize one approved operator and erase their authored notes.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                self::erase((int)$context->instanceid, [(int)$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Erase data for the approved operators within the course.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE && $userlist->get_userids()) {
            self::erase((int)$context->instanceid, array_map('intval', $userlist->get_userids()));
        }
    }

    /**
     * Synchronize erasure with the audit worker and plan editor.
     */
    private static function erase(int $courseid, ?array $ids): void {
        global $DB;
        $factory = lock_config::get_lock_factory(scope::COMPONENT);
        $course = $factory->get_lock('course_' . $courseid, 10);
        if (!$course) {
            throw new \moodle_exception('busy', scope::COMPONENT);
        }
        $plan = null;
        try {
            $plan = $factory->get_lock('plan_' . $courseid, 10);
            if (!$plan) {
                throw new \moodle_exception('busy', scope::COMPONENT);
            }
            $transaction = $DB->start_delegated_transaction();
            foreach ($ids === null ? [null] : array_chunk($ids, 500) as $chunk) {
                $where = 'courseid = :course AND userid > 0';
                $params = ['course' => $courseid];
                if ($chunk !== null) {
                    [$in, $users] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'author');
                    $where .= ' AND userid ' . $in;
                    $params += $users;
                }
                $DB->set_field_select(store::RUN, 'userid', 0, $where, $params);
                $DB->set_field_select(store::PLAN, 'notes', '', $where, $params);
                $DB->set_field_select(store::PLAN, 'userid', 0, $where, $params);
            }
            $transaction->allow_commit();
        } finally {
            if ($plan) {
                $plan->release();
            }
            $course->release();
        }
    }
}
