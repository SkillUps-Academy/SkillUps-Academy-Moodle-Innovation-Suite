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

namespace local_skillupscopilot\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_skillupscopilot\access;
use local_skillupscopilot\chat;
use local_skillupscopilot\sources;

/**
 * Discover, export and erase personal chat and metering data.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe database use and optional provider transfers.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_skillupscopilot_thread', [
            'userid' => 'privacy:userid', 'courseid' => 'privacy:courseid',
            'timecreated' => 'privacy:time', 'timemodified' => 'privacy:time',
        ], 'privacy:thread');
        $collection->add_database_table('local_skillupscopilot_turn', [
            'threadid' => 'privacy:thread', 'prompt' => 'privacy:prompt', 'answer' => 'privacy:answer',
            'manifest' => 'privacy:manifest', 'rating' => 'privacy:rating', 'timecreated' => 'privacy:time',
            'provider' => 'privacy:provider', 'status' => 'privacy:status', 'mode' => 'privacy:mode',
            'lang' => 'privacy:lang', 'timemodified' => 'privacy:time',
            'requestkey' => 'privacy:requestkey', 'providerrev' => 'privacy:providerrev',
        ], 'privacy:turn');
        $collection->add_database_table('local_skillupscopilot_meter', [
            'userid' => 'privacy:userid', 'courseid' => 'privacy:courseid', 'timecreated' => 'privacy:time',
            'timemodified' => 'privacy:time', 'provider' => 'privacy:provider', 'turnid' => 'privacy:turn',
            'status' => 'privacy:status', 'inputtokens' => 'privacy:tokens', 'outputtokens' => 'privacy:tokens',
        ], 'privacy:meter');
        $collection->add_external_location_link('configuredai', [
            'prompt' => 'privacy:prompt', 'history' => 'privacy:history', 'passages' => 'privacy:manifest',
        ], 'privacy:external');
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        return $collection;
    }

    /**
     * Return contexts with either history or metering records.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (['local_skillupscopilot_thread', 'local_skillupscopilot_meter'] as $table) {
            $list->add_from_sql(
                "SELECT DISTINCT ctx.id
                   FROM {context} ctx
                   JOIN {{$table}} data ON
                        (ctx.contextlevel = :system AND data.courseid = 0) OR
                        (ctx.contextlevel = :course AND ctx.instanceid = data.courseid)
                  WHERE data.userid = :userid",
                ['system' => CONTEXT_SYSTEM, 'course' => CONTEXT_COURSE, 'userid' => $userid]
            );
        }
        return $list;
    }

    /**
     * Enumerate data owners for a Moodle privacy request.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $courseid = self::courseid($userlist->get_context());
        if ($courseid === null) {
            return;
        }
        foreach (['local_skillupscopilot_thread', 'local_skillupscopilot_meter'] as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT DISTINCT userid FROM {{$table}} WHERE courseid = :courseid",
                ['courseid' => $courseid]
            );
        }
    }

    /**
     * Export approved private data in bounded per-record writes.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::courseid($context);
            if ($courseid === null) {
                continue;
            }
            $where = ['userid' => $user->id, 'courseid' => $courseid];
            $export = writer::with_context($context);
            $threads = $DB->get_recordset('local_skillupscopilot_thread', $where, 'id');
            try {
                foreach ($threads as $thread) {
                    $turns = $DB->get_recordset('local_skillupscopilot_turn', ['threadid' => $thread->id], 'id');
                    try {
                        foreach ($turns as $turn) {
                            $titles = in_array($turn->mode, access::modes($courseid, $user), true) ?
                                sources::titles($turn->manifest, $courseid, $user) : null;
                            $export->export_data(['SkillUps AI Copilot', 'Conversations', (string)$thread->id,
                                (string)$turn->id], (object)[
                                    'prompt' => $turn->prompt,
                                    'answer' => $titles === null ? null : $turn->answer,
                                    'sources' => $titles, 'provider' => $turn->provider, 'status' => $turn->status,
                                    'rating' => $turn->rating, 'mode' => $turn->mode, 'language' => $turn->lang,
                                    'created' => transform::datetime($turn->timecreated),
                                ]);
                        }
                    } finally {
                        $turns->close();
                    }
                }
            } finally {
                $threads->close();
            }
            $meter = $DB->get_recordset('local_skillupscopilot_meter', $where, 'id');
            try {
                foreach ($meter as $entry) {
                    $export->export_data(['SkillUps AI Copilot', 'Usage', (string)$entry->id], (object)[
                        'provider' => $entry->provider, 'status' => $entry->status,
                        'inputtokens' => $entry->inputtokens, 'outputtokens' => $entry->outputtokens,
                        'created' => transform::datetime($entry->timecreated),
                    ]);
                }
            } finally {
                $meter->close();
            }
        }
    }

    /**
     * Delete all personal data within an approved context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        $courseid = self::courseid($context);
        if ($courseid !== null) {
            self::erase(['courseid' => $courseid]);
        }
    }

    /**
     * Delete personal data only for the approved account and contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::courseid($context);
            if ($courseid !== null) {
                self::erase(['courseid' => $courseid, 'userid' => $contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Delete approved users' data in the supplied context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $courseid = self::courseid($userlist->get_context());
        if ($courseid !== null) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase(['courseid' => $courseid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Translate the site context into course ID zero.
     */
    private static function courseid(\context $context): ?int {
        return match ($context->contextlevel) {
            CONTEXT_SYSTEM => 0, CONTEXT_COURSE => (int)$context->instanceid, default => null,
        };
    }

    /**
     * Erase both conversation and metering records in the approved scope.
     */
    private static function erase(array $scope): void {
        global $DB;
        chat::delete($scope);
        $DB->delete_records('local_skillupscopilot_meter', $scope);
    }
}
