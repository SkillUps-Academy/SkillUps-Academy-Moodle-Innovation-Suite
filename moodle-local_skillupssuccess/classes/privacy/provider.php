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

namespace local_skillupssuccess\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupssuccess\store;
use local_skillupssuccess\util;

/**
 * Moodle privacy metadata, context discovery, export and deletion.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe retained identifying records and their fields.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(store::OBS, [
            'userid' => 'privacy:user', 'factors' => 'privacy:factors', 'gradepct' => 'privacy:factors',
            'probability' => 'privacy:factors', 'captured' => 'privacy:time', 'risk' => 'privacy:factors',
        ], 'privacy:observations');
        $collection->add_database_table(store::CASES, [
            'userid' => 'privacy:user', 'tutorid' => 'privacy:user', 'state' => 'privacy:state',
        ], 'privacy:cases');
        $collection->add_database_table(store::ENTRY, [
            'userid' => 'privacy:user', 'tutorid' => 'privacy:user', 'note' => 'privacy:note', 'timecreated' => 'privacy:time',
        ], 'privacy:entries');
        $collection->add_database_table(store::SAMPLE, [
            'userid' => 'privacy:user', 'assessorid' => 'privacy:user', 'outcome' => 'privacy:factors',
            'modelalert' => 'privacy:factors', 'due' => 'privacy:time',
        ], 'privacy:samples');
        return $collection;
    }

    /**
     * Enumerate user references, including a historical assigned tutor or assessor.
     */
    private static function sources(): array {
        $case = store::CASES;
        $obs = store::OBS;
        $sample = store::SAMPLE;
        $entry = store::ENTRY;
        return [
            ["FROM {{$case}} t", 't.courseid', 't.userid'],
            ["FROM {{$case}} t", 't.courseid', 't.tutorid'],
            ["FROM {{$sample}} t", 't.courseid', 't.userid'],
            ["FROM {{$sample}} t", 't.courseid', 't.assessorid'],
            ["FROM {{$obs}} t", 't.courseid', 't.userid'],
            ["FROM {{$entry}} t JOIN {{$case}} c ON c.id = t.caseid", 'c.courseid', 't.userid'],
            ["FROM {{$entry}} t JOIN {{$case}} c ON c.id = t.caseid", 'c.courseid', 't.tutorid'],
        ];
    }

    /**
     * Course IDs shared by the privacy feature and user-deletion observer.
     */
    public static function courseids(int $userid): array {
        global $DB;
        $courses = [];
        foreach (self::sources() as [$db, $course, $field]) {
            $courses = array_merge($courses, $DB->get_fieldset_sql(
                "SELECT DISTINCT $course $db WHERE $field = :userid",
                ['userid' => $userid]
            ));
        }
        return array_values(array_unique($courses));
    }

    /**
     * Locate contexts holding the approved subject’s plugin data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $courses = self::courseids($userid);
        if ($courses) {
            global $DB;
            [$sql, $params] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED, 'course');
            $list->add_from_sql(
                "SELECT id FROM {context} WHERE contextlevel = :level AND instanceid $sql",
                $params + ['level' => CONTEXT_COURSE]
            );
        }
        return $list;
    }

    /**
     * Identify users with a current or past reference in the supplied course.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE) {
            foreach (self::sources() as [$db, $course, $field]) {
                $userlist->add_from_sql(
                    'account',
                    "SELECT DISTINCT $field AS account $db WHERE $course = :course AND $field > 0",
                    ['course' => $context->instanceid]
                );
            }
        }
    }

    /**
     * Export subject data or actor metadata without other authors' identities.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $owner = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $writer = writer::with_context($context);
            $root = [util::str('privacy:path')];
            $base = ['courseid' => $context->instanceid, 'userid' => $owner];
            foreach ([store::OBS => 'observations', store::SAMPLE => 'outcomes', store::CASES => 'cases'] as $table => $type) {
                foreach ($DB->get_records($table, $base) as $record) {
                    unset($record->userid, $record->tutorid, $record->assessorid);
                    $path = array_merge($root, [$type, (string)$record->id]);
                    $writer->export_data($path, $record);
                    if ($table === store::CASES) {
                        $entryfields = 'id,action,note,timecreated';
                        $entries = $DB->get_records(store::ENTRY, ['caseid' => $record->id], 'id ASC', $entryfields);
                        $writer->export_data(array_merge($path, ['interventions']), (object)['items' => array_values($entries)]);
                    }
                }
            }
            $entry = store::ENTRY;
            $cases = store::CASES;
            $actor = $DB->get_records_sql(
                "SELECT e.id, e.action, e.timecreated FROM {{$entry}} e
                JOIN {{$cases}} c ON c.id = e.caseid WHERE c.courseid = :course AND (e.userid = :author OR e.tutorid = :assignee)",
                ['course' => $context->instanceid, 'author' => $owner, 'assignee' => $owner]
            );
            foreach ($actor as $action) {
                $writer->export_data(array_merge($root, ['staffactivity', (string)$action->id]), $action);
            }
            foreach (
                $DB->get_records(
                    store::SAMPLE,
                    ['courseid' => $context->instanceid, 'assessorid' => $owner],
                    'id ASC',
                    'id,reviewed'
                ) as $assessment
            ) {
                $writer->export_data(array_merge($root, ['reviewactivity', (string)$assessment->id]), $assessment);
            }
        }
    }

    /**
     * Erase plugin data for the approved course.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_COURSE) {
            deletion::by_course($context->instanceid);
        }
    }

    /**
     * Erase one user for each approved course.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                deletion::by_course($context->instanceid, $contextlist->get_user()->id);
            }
        }
    }

    /**
     * Erase users approved under the course context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_COURSE) {
            foreach ($userlist->get_userids() as $userid) {
                deletion::by_course($userlist->get_context()->instanceid, $userid);
            }
        }
    }
}
