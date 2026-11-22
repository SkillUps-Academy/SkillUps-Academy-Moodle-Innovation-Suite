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

namespace report_skillupsinsight\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use report_skillupsinsight\report;
use report_skillupsinsight\util;

/**
 * Manage personal report definitions and schedules through Moodle privacy requests.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe identifiable records owned by the plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(report::PRESET, [
            'userid' => 'privacy:metadata:userid', 'name' => 'privacy:metadata:settings',
            'config' => 'privacy:metadata:settings', 'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:preset');
        $collection->add_database_table(report::WATCH, [
            'userid' => 'privacy:metadata:userid', 'metric' => 'privacy:metadata:settings',
            'target' => 'privacy:metadata:settings', 'nextrun' => 'privacy:metadata:status',
            'lastchecked' => 'privacy:metadata:status',
        ], 'privacy:metadata:watch');
        return $collection;
    }

    /**
     * A saved personal report is stored at system level.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists(report::PRESET, ['userid' => $userid]) || $DB->record_exists(report::WATCH, ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Enumerate owners referenced in the system context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ([report::PRESET, report::WATCH] as $table) {
                $userlist->add_from_sql('userid', "SELECT DISTINCT userid FROM {{$table}}", []);
            }
        }
    }

    /**
     * Export only owned personal settings, with no course metrics or names included.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            foreach ([report::PRESET => 'reports', report::WATCH => 'schedules'] as $table => $name) {
                foreach ($DB->get_records($table, ['userid' => $user->id]) as $record) {
                    unset($record->userid);
                    writer::with_context($context)->export_data(
                        [util::str('privacy:path'), $name, (string)$record->id],
                        $record
                    );
                }
            }
        }
    }

    /**
     * Erase all approved personal definitions and watches.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            foreach ($DB->get_fieldset_sql('SELECT DISTINCT userid FROM {' . report::PRESET . '}') as $id) {
                self::erase((int)$id);
            }
        }
    }

    /**
     * Erase one owner's personal records in the system context.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Erase data for the approved owners.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                self::erase((int)$userid);
            }
        }
    }

    /**
     * Synchronize erasure with notification dispatch and preset changes.
     */
    public static function erase(int $userid): void {
        global $DB;
        $lock = util::acquire('owner_' . $userid);
        try {
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records(report::WATCH, ['userid' => $userid]);
            $DB->delete_records(report::PRESET, ['userid' => $userid]);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }
}
