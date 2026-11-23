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

namespace local_skillupsgradebookguardian\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupsgradebookguardian\access;
use local_skillupsgradebookguardian\ledger;

/**
 * Gradebook Guardian records operator attribution within the course.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /**
     * Document fields written by this plugin.
     *
     * @param collection $collection Metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [];
        foreach (['courseid', 'userid', 'parentid', 'kind', 'payload', 'timecreated'] as $field) {
            $fields[$field] = 'privacy:metadata:' . $field;
        }
        $collection->add_database_table(ledger::TABLE, $fields, 'privacy:metadata:ledger');
        return $collection;
    }

    /**
     * Locate courses with records attributed to the operator.
     *
     * @param int $userid User ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $table = ledger::TABLE;
        $list->add_from_sql(
            "SELECT DISTINCT ctx.id FROM {context} ctx JOIN {{$table}} log ON log.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND log.userid = :userid",
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $list;
    }

    /**
     * Identify attributed operators in a course.
     *
     * @param userlist $userlist Target context and user list
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_COURSE) {
            $table = ledger::TABLE;
            $userlist->add_from_sql(
                'userid',
                "SELECT DISTINCT userid FROM {{$table}} WHERE courseid = :courseid AND userid > 0",
                ['courseid' => $context->instanceid]
            );
        }
    }

    /**
     * Export the operator's course ledger entries.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $records = $DB->get_recordset(
                ledger::TABLE,
                ['courseid' => $context->instanceid, 'userid' => $contextlist->get_user()->id],
                'id ASC'
            );
            try {
                foreach ($records as $record) {
                    $record->payload = json_decode($record->payload, true, 32, JSON_THROW_ON_ERROR);
                    writer::with_context($context)->export_data([access::text('pluginname'), (string)$record->id], $record);
                }
            } finally {
                $records->close();
            }
        }
    }

    /**
     * Remove all plugin records in the course context.
     *
     * @param context $context Course context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_COURSE) {
            ledger::locked((int)$context->instanceid, static function () use ($DB, $context): void {
                $DB->delete_records(ledger::TABLE, ['courseid' => $context->instanceid]);
            });
        }
    }

    /**
     * Remove operator attribution from the user's approved courses.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::anonymize($context, [(int)$contextlist->get_user()->id]);
        }
    }

    /**
     * Remove operator attribution from users approved for this course.
     *
     * @param approved_userlist $userlist Approved operators
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::anonymize($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Clear actor identifiers without modifying the native gradebook.
     *
     * @param context $context Course context
     * @param array $users Actor IDs
     */
    private static function anonymize(context $context, array $users): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE || !$users) {
            return;
        }
        ledger::locked((int)$context->instanceid, static function () use ($DB, $context, $users): void {
            foreach (array_chunk($users, 400) as $chunk) {
                [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'actor');
                $DB->set_field_select(
                    ledger::TABLE,
                    'userid',
                    0,
                    "courseid = :courseid AND userid $in",
                    $params + ['courseid' => $context->instanceid]
                );
            }
        });
    }
}
