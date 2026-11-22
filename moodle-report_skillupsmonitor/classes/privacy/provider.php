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

namespace report_skillupsmonitor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use report_skillupsmonitor\store;
use report_skillupsmonitor\util;

/**
 * Exports and erases identifiable records in the system context.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the plugin's personal data.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(store::NOTICE, [
            'userid' => 'privacy:metadata:userid', 'phase' => 'privacy:metadata:phase',
            'state' => 'privacy:metadata:state', 'timecreated' => 'privacy:metadata:time',
            'timemodified' => 'privacy:metadata:time',
        ], 'privacy:metadata:notice');
        $collection->add_database_table(store::INCIDENT, [
            'ackuserid' => 'privacy:metadata:userid', 'timeack' => 'privacy:metadata:time',
        ], 'privacy:metadata:incident');
        $collection->add_database_table('config_plugins', [
            'value' => 'privacy:metadata:recipients',
        ], 'privacy:metadata:recipients');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:messaging');
        return $collection;
    }

    /**
     * User data is only associated with the system context.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if (
            in_array($userid, util::configured_recipients(), true) ||
                $DB->record_exists(store::NOTICE, ['userid' => $userid]) ||
                $DB->record_exists(store::INCIDENT, ['ackuserid' => $userid])
        ) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Enumerate stored recipient and actor IDs.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_users(util::configured_recipients());
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid FROM {' . store::NOTICE . '}', []);
        $userlist->add_from_sql('ackuserid', 'SELECT DISTINCT ackuserid FROM {' . store::INCIDENT . '} WHERE ackuserid > 0', []);
    }

    /**
     * Export the approved user's routing, notices and incident reviews.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $path = [util::str('privacy:path')];
            $writer = writer::with_context($context);
            if (in_array($userid, util::configured_recipients(), true)) {
                $writer->export_data($path, (object)['recipient' => true]);
            }
            $notices = $DB->get_records(store::NOTICE, ['userid' => $userid], 'id', 'id,phase,state,timecreated,timemodified');
            foreach ($notices as $row) {
                $writer->export_data([...$path, 'notifications', (string)$row->id], $row);
            }
            foreach ($DB->get_records(store::INCIDENT, ['ackuserid' => $userid], 'id', 'id,checkkey,timeack') as $row) {
                $writer->export_data([...$path, 'reviews', (string)$row->id], $row);
            }
        }
    }

    /**
     * Erase personal data for the system context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            self::erase(null);
        }
    }

    /**
     * Erase approved data for one user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase([(int)$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Erase approved data for selected users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM && $userlist->get_userids()) {
            self::erase(array_map('intval', $userlist->get_userids()));
        }
    }

    /**
     * Wait for sampling and delivery before dropping identities.
     */
    private static function erase(?array $ids): void {
        global $DB;
        $sampling = util::lock('sampling', 10);
        if (!$sampling) {
            throw new \moodle_exception('busy', util::C);
        }
        try {
            $delivery = util::lock('delivery', 10);
            if (!$delivery) {
                throw new \moodle_exception('busy', util::C);
            }
            try {
                $transaction = $DB->start_delegated_transaction();
                if ($ids === null) {
                    $DB->delete_records(store::NOTICE);
                    $DB->set_field_select(store::INCIDENT, 'ackuserid', 0, 'ackuserid > 0', []);
                    set_config('recipients', '', util::C);
                } else {
                    foreach (array_chunk($ids, 500) as $chunk) {
                        [$sql, $params] = $DB->get_in_or_equal($chunk);
                        $DB->delete_records_select(store::NOTICE, "userid $sql", $params);
                        $DB->set_field_select(store::INCIDENT, 'ackuserid', 0, "ackuserid $sql", $params);
                    }
                    $recipients = util::configured_recipients();
                    $remaining = array_diff($recipients, $ids);
                    if ($remaining !== $recipients) {
                        set_config('recipients', implode(',', $remaining), util::C);
                    }
                }
                $transaction->allow_commit();
            } finally {
                $delivery->release();
            }
        } finally {
            $sampling->release();
        }
    }
}
