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

namespace local_skillupscompetency\privacy;

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
use local_skillupscompetency\access;
use local_skillupscompetency\assessment;
use local_skillupscompetency\store;

/**
 * Export and erase personal data in the Moodle course context.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadataprovider, requestprovider {
    /** @var array Assessment fields containing personal information. */
    private const FIELDS = ['userid', 'authorid', 'reviewerid', 'observations', 'feedback', 'rubricdata', 'levelvalue'];

    /**
     * Declare personal assessment data and its Moodle subsystems.
     *
     * @param collection $collection Collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $columns = [];
        foreach (self::FIELDS as $key) {
            $columns[$key] = 'privacy:field:' . $key;
        }
        $collection->add_database_table(store::ASSESS, $columns, 'privacy:assessment');
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        $collection->add_subsystem_link('core_competency', [], 'privacy:core');
        return $collection;
    }

    /**
     * Find the course contexts containing the user.
     *
     * @param int $userid Learner ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $table = store::ASSESS;
        $list->add_from_sql(
            "SELECT DISTINCT c.id FROM {{$table}} a JOIN {context} c ON c.instanceid = a.courseid
                           WHERE c.contextlevel = :ctx
                             AND (a.userid = :subject OR a.authorid = :author OR a.reviewerid = :reviewer)",
            ['ctx' => CONTEXT_COURSE, 'subject' => $userid, 'author' => $userid, 'reviewer' => $userid]
        );
        return $list;
    }

    /**
     * Find the affected users in the course context.
     *
     * @param userlist $userlist Users in an approved context
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $table = store::ASSESS;
        foreach (['userid', 'authorid', 'reviewerid'] as $key) {
            $userlist->add_from_sql(
                $key,
                "SELECT $key FROM {{$table}} WHERE courseid = :course AND $key > 0",
                ['course' => $context->instanceid]
            );
        }
    }

    /**
     * Export assessment data and files within approved course contexts.
     *
     * @param approved_contextlist $contextlist Approved subjects and contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $rows = $DB->get_recordset_select(
                store::ASSESS,
                'courseid = :course AND (userid = :subject OR authorid = :author OR reviewerid = :reviewer)',
                ['course' => $context->instanceid, 'subject' => $userid, 'author' => $userid, 'reviewer' => $userid]
            );
            try {
                foreach ($rows as $item) {
                    $own = $item->userid == $userid;
                    $author = $item->authorid == $userid;
                    $reviewer = $item->reviewerid == $userid;
                    $approved = $item->status === 'approved';
                    $path = [access::text('pluginname'), (string)$item->id];
                    $output = (object)[
                        'competency' => $item->competencyid, 'status' => $item->status, 'created' => $item->created,
                        'reviewed' => $item->reviewed, 'author' => $author, 'reviewer' => $reviewer, 'subject' => $own,
                    ];
                    if (($own && ($item->kind === 'self' || $approved)) || $author) {
                        $output->observations = $item->observations;
                        $output->level = $item->levelvalue;
                        $output->rubric = $item->rubricdata;
                        writer::with_context($context)->export_area_files($path, access::C, assessment::AREA, $item->id);
                    }
                    if ($reviewer || ($own && ($item->kind === 'self' || $approved))) {
                        $output->feedback = $item->feedback;
                    }
                    writer::with_context($context)->export_data($path, $output);
                }
            } finally {
                $rows->close();
            }
        }
    }

    /**
     * Remove the course assessments and files after a privacy approval.
     *
     * @param context $context Authorized Moodle context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_COURSE) {
            store::write(static function () use ($DB, $context): void {
                get_file_storage()->delete_area_files($context->id, access::C, assessment::AREA);
                $DB->delete_records(store::ASSESS, ['courseid' => $context->instanceid]);
            });
        }
    }

    /**
     * Apply the approved erasure for one subject.
     *
     * @param approved_contextlist $contextlist Approved subjects and contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::erase($context, [(int)$contextlist->get_user()->id]);
        }
    }

    /**
     * Apply approved erasure for multiple subjects.
     *
     * @param approved_userlist $userlist Users in an approved context
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::erase($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Remove records or unlink authors and reviewers in the approved course.
     *
     * @param context $context Authorized Moodle context
     * @param array $users Approved user IDs
     */
    private static function erase(context $context, array $users): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE || !$users) {
            return;
        }
        store::write(static function () use ($DB, $context, $users): void {
            foreach (array_chunk($users, 200) as $part) {
                [$subjects, $sp] = $DB->get_in_or_equal($part, SQL_PARAMS_NAMED, 'subject');
                [$authors, $ap] = $DB->get_in_or_equal($part, SQL_PARAMS_NAMED, 'author');
                [$reviewers, $rp] = $DB->get_in_or_equal($part, SQL_PARAMS_NAMED, 'reviewer');
                $rows = $DB->get_recordset_select(
                    store::ASSESS,
                    "courseid = :course AND (userid $subjects OR authorid $authors OR reviewerid $reviewers)",
                    ['course' => $context->instanceid] + $sp + $ap + $rp
                );
                try {
                    foreach ($rows as $row) {
                        $author = in_array($row->authorid, $part);
                        $subject = in_array($row->userid, $part);
                        if ($subject || $author) {
                            get_file_storage()->delete_area_files($context->id, access::C, assessment::AREA, $row->id);
                        }
                        if ($subject) {
                            $DB->delete_records(store::ASSESS, ['id' => $row->id]);
                            continue;
                        }
                        if ($author) {
                            $row->authorid = 0;
                            $row->observations = null;
                            $row->rubricdata = null;
                        }
                        if (in_array($row->reviewerid, $part)) {
                            $row->reviewerid = 0;
                            $row->feedback = null;
                        }
                        $DB->update_record(store::ASSESS, $row);
                    }
                } finally {
                    $rows->close();
                }
            }
        });
    }
}
