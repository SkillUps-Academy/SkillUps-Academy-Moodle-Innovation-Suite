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

namespace local_skillupsaigrading\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupsaigrading\access;
use local_skillupsaigrading\workflow;

/**
 * Privacy integration scoped to activity contexts.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe owned data and optional transfers.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(workflow::JOB, [
            'studentid' => 'privacy:metadata:subject', 'userid' => 'privacy:metadata:actor',
            'excerpt' => 'privacy:metadata:excerpt', 'rubric' => 'privacy:metadata:rubric',
            'result' => 'privacy:metadata:result', 'human' => 'privacy:metadata:human',
            'provider' => 'privacy:metadata:provider', 'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:job');
        $collection->add_database_table(workflow::DECISION, [
            'userid' => 'privacy:metadata:actor', 'action' => 'privacy:metadata:action',
            'feedback' => 'privacy:metadata:feedback', 'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:decision');
        $collection->add_external_location_link('provider', [
            'excerpt' => 'privacy:metadata:excerpt', 'rubric' => 'privacy:metadata:rubric',
        ], 'privacy:metadata:connector');
        return $collection;
    }

    /**
     * Find module contexts where the account was a subject or reviewer.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $jobs = workflow::JOB;
        $decisions = workflow::DECISION;
        $list = new contextlist();
        $list->add_from_sql(
            "SELECT DISTINCT c.id FROM {context} c JOIN {{$jobs}} j ON j.cmid = c.instanceid
            LEFT JOIN {{$decisions}} d ON d.jobid = j.id
            WHERE c.contextlevel = :level AND (j.studentid = :student OR j.userid = :actor OR d.userid = :reviewer)",
            ['level' => CONTEXT_MODULE, 'student' => $userid, 'actor' => $userid, 'reviewer' => $userid]
        );
        return $list;
    }

    /**
     * Include both students and teacher accounts with attributable operations.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $jobs = workflow::JOB;
        $decisions = workflow::DECISION;
        $params = ['cmid' => $context->instanceid];
        foreach (['studentid', 'userid'] as $column) {
            $userlist->add_from_sql($column, "SELECT DISTINCT $column FROM {{$jobs}} WHERE cmid = :cmid AND $column > 0", $params);
        }
        $userlist->add_from_sql('userid', "SELECT DISTINCT d.userid FROM {{$decisions}} d JOIN {{$jobs}} j ON j.id = d.jobid
            WHERE j.cmid = :cmid AND d.userid > 0", $params);
    }

    /**
     * Separate student evaluation exports from teacher activity metadata.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $id = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $root = [get_string('privacy:path', access::C)];
            foreach ($DB->get_records(workflow::JOB, ['cmid' => $context->instanceid, 'studentid' => $id]) as $job) {
                $path = array_merge($root, [(string)$job->id]);
                $decisions = array_values($DB->get_records(
                    workflow::DECISION,
                    ['jobid' => $job->id],
                    'id ASC',
                    'id, action, feedback, timecreated'
                ));
                unset($job->userid, $job->studentid, $job->signature, $job->nonce, $job->providerrev);
                writer::with_context($context)->export_data($path, $job);
                writer::with_context($context)->export_data(array_merge($path, ['decisions']), (object)['items' => $decisions]);
            }
            $jobs = workflow::JOB;
            $decisions = workflow::DECISION;
            foreach (
                $DB->get_records(
                    workflow::JOB,
                    ['cmid' => $context->instanceid, 'userid' => $id],
                    'id ASC',
                    'id, provider, model, modelversion, state, timecreated'
                ) as $job
            ) {
                writer::with_context($context)->export_data(array_merge($root, ['requests', (string)$job->id]), $job);
            }
            foreach (
                $DB->get_records_sql(
                    "SELECT d.id, d.action, d.timecreated FROM {{$decisions}} d
                JOIN {{$jobs}} j ON j.id = d.jobid WHERE j.cmid = :cmid AND d.userid = :userid",
                    ['cmid' => $context->instanceid, 'userid' => $id]
                ) as $decision
            ) {
                writer::with_context($context)->export_data(array_merge($root, ['actions', (string)$decision->id]), $decision);
            }
        }
    }

    /**
     * Delete review data for an entire approved module context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_MODULE) {
            self::erase((int)$context->instanceid, null);
        }
    }

    /**
     * Apply an approved request in each module context.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_MODULE) {
                self::erase((int)$context->instanceid, (int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Apply approved per-user erasure.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_MODULE) {
            foreach ($userlist->get_userids() as $id) {
                self::erase((int)$userlist->get_context()->instanceid, (int)$id);
            }
        }
    }

    /**
     * Drop student records, cancel pending transfers and remove teacher attribution.
     */
    private static function erase(int $cmid, ?int $userid): void {
        global $DB;
        $lock = access::lock($cmid, 60);
        try {
            $conditions = ['cmid' => $cmid];
            if ($userid !== null) {
                $conditions['studentid'] = $userid;
            }
            foreach ($DB->get_records(workflow::JOB, $conditions, 'id ASC', 'id') as $job) {
                workflow::erase((int)$job->id);
            }
            if ($userid === null) {
                return;
            }
            $jobs = workflow::JOB;
            $decisions = workflow::DECISION;
            $DB->set_field_select(
                workflow::DECISION,
                'feedback',
                '',
                "userid = :userid AND jobid IN (SELECT id FROM {{$jobs}} WHERE cmid = :cmid)",
                ['userid' => $userid, 'cmid' => $cmid]
            );
            $DB->set_field_select(
                workflow::DECISION,
                'userid',
                0,
                "userid = :userid AND jobid IN (SELECT id FROM {{$jobs}} WHERE cmid = :cmid)",
                ['userid' => $userid, 'cmid' => $cmid]
            );
            foreach ($DB->get_records(workflow::JOB, ['cmid' => $cmid, 'userid' => $userid]) as $job) {
                $changes = ['id' => $job->id, 'userid' => 0, 'excerpt' => null];
                if (in_array($job->state, ['queued', 'working'], true)) {
                    $changes['state'] = 'failed';
                }
                $DB->update_record(workflow::JOB, $changes);
            }
        } finally {
            $lock->release();
        }
    }
}
