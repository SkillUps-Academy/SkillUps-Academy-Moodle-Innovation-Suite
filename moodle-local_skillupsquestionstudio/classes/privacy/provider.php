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

namespace local_skillupsquestionstudio\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\erasure;
use local_skillupsquestionstudio\work;

/**
 * Personal data linked to course projects, documents, drafts and review.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe owned tables and optional data transfers.
     */
    public static function get_metadata(collection $collection): collection {
        foreach (['project', 'source', 'job', 'item', 'rev', 'variant', 'pub'] as $table) {
            $collection->add_database_table(access::C . '_' . $table, [
                'userid' => 'privacy:account',
            ] + match ($table) {
                'source' => ['name' => 'privacy:document', 'content' => 'privacy:document'],
                'rev' => ['contents' => 'privacy:question'],
                'item' => ['reviewer' => 'privacy:account'],
                'job' => ['request' => 'privacy:request'],
                default => [],
            }, 'privacy:records');
        }
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        $collection->add_subsystem_link('core_ai', [], 'privacy:ai');
        $collection->add_external_location_link('aiprovider', ['excerpt' => 'privacy:document'], 'privacy:ai');
        return $collection;
    }

    /**
     * Locate all course contexts with this account's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::scopes() as [$table, $from, $field]) {
            $list->add_from_sql("SELECT DISTINCT c.id $from JOIN {context} c ON c.instanceid = p.courseid
                WHERE c.contextlevel = :level AND a.$field = :owner", ['level' => CONTEXT_COURSE, 'owner' => $userid]);
        }
        return $list;
    }

    /**
     * Enumerate account IDs referenced in a course.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        foreach (self::scopes() as [$table, $from, $field]) {
            $userlist->add_from_sql('accountid', "SELECT DISTINCT a.$field AS accountid $from
                WHERE p.courseid = :course AND a.$field > 0", ['course' => $context->instanceid]);
        }
    }

    /**
     * Export the approved account's contributions without other contributors' associations.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            foreach (self::scopes() as [$table, $from, $field]) {
                $records = $DB->get_recordset_sql(
                    "SELECT a.* $from WHERE p.courseid = :course AND a.$field = :owner",
                    ['course' => $context->instanceid, 'owner' => $user->id]
                );
                try {
                    foreach ($records as $record) {
                        unset($record->userid, $record->reviewer, $record->hash, $record->fingerprint);
                        writer::with_context($context)->export_data(['SkillUps Question Studio', $table . '_' . $field,
                            (string)$record->id], $record);
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Clear all plugin projects in an approved course context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_COURSE) {
            foreach ($DB->get_records(access::C . '_project', ['courseid' => $context->instanceid], 'id', 'id') as $p) {
                erasure::project($p->id, $context->instanceid);
            }
        }
    }

    /**
     * Anonymize attribution and erase source text owned by the approved user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                self::erase((int)$contextlist->get_user()->id, (int)$context->instanceid);
            }
        }
    }

    /**
     * Erase personal records for all approved accounts in the supplied course.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_COURSE) {
            foreach ($userlist->get_userids() as $id) {
                self::erase((int)$id, (int)$userlist->get_context()->instanceid);
            }
        }
    }

    /**
     * Map each personal identifier to its course without touching core records.
     */
    private static function scopes(): array {
        $prefix = access::C;
        $project = "JOIN {{$prefix}_project} p ON p.id = a.projectid";
        $revision = "JOIN {{$prefix}_item} i ON i.id = a.itemid JOIN {{$prefix}_project} p ON p.id = i.projectid";
        $receipt = "JOIN {{$prefix}_rev} r ON r.id = a.revisionid JOIN {{$prefix}_item} i ON i.id = r.itemid
            JOIN {{$prefix}_project} p ON p.id = i.projectid";
        $scopes = ['project' => "FROM {{$prefix}_project} a JOIN {{$prefix}_project} p ON p.id = a.id"];
        foreach (['source', 'job', 'item', 'variant'] as $type) {
            $scopes[$type] = "FROM {{$prefix}_$type} a $project";
        }
        $scopes['rev'] = "FROM {{$prefix}_rev} a $revision";
        $scopes['pub'] = "FROM {{$prefix}_pub} a $receipt";
        $result = [['item', $scopes['item'], 'reviewer']];
        foreach ($scopes as $type => $sql) {
            $result[] = [$type, $sql, 'userid'];
        }
        return $result;
    }

    /**
     * Retain institutional assessment content but clear individual ownership and pending work.
     */
    private static function erase(int $userid, int $courseid): void {
        global $DB;
        foreach ($DB->get_records(access::C . '_project', ['courseid' => $courseid], 'id', 'id,courseid') as $project) {
            $lock = access::lock($project->id, 30);
            try {
                foreach ($DB->get_records(access::C . '_source', ['projectid' => $project->id, 'userid' => $userid]) as $source) {
                    work::delete_upload($courseid, $source->id);
                    $DB->update_record(access::C . '_source', (object)[
                        'id' => $source->id, 'userid' => 0, 'name' => '', 'hash' => '', 'content' => null, 'state' => 'deleted',
                    ]);
                }
                $DB->delete_records(access::C . '_job', ['projectid' => $project->id, 'userid' => $userid]);
                $DB->set_field(access::C . '_project', 'userid', 0, ['id' => $project->id, 'userid' => $userid]);
                foreach (self::scopes() as [$table, $from, $field]) {
                    if (in_array($table, ['project', 'source', 'job'], true)) {
                        continue;
                    }
                    $ids = $DB->get_fieldset_sql("SELECT a.id $from WHERE p.id = ? AND a.$field = ?", [$project->id, $userid]);
                    foreach (array_chunk($ids, 200) as $chunk) {
                        [$sql, $params] = $DB->get_in_or_equal($chunk);
                        $DB->set_field_select(access::C . '_' . $table, $field, 0, 'id ' . $sql, $params);
                    }
                }
            } finally {
                $lock->release();
            }
        }
    }
}
