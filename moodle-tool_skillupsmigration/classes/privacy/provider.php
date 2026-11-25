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

namespace tool_skillupsmigration\privacy;

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
use tool_skillupsmigration\processor;
use tool_skillupsmigration\support;

/**
 * Export and remove approved local operator data and explicit account mappings.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadataprovider, requestprovider {
    /** @var array Actor-bearing persisted tables. */
    private const TABLES = [support::JOB, support::ARTIFACT, support::AUDIT];

    /**
     * Declare identifiable operation data.
     *
     * @param collection $collection Collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::TABLES as $table) {
            $collection->add_database_table(
                $table,
                ['actor' => 'privacy:actor'],
                'privacy:' . substr($table, strlen(support::C) + 1)
            );
        }
        $collection->add_database_table(support::ITEM, ['options' => 'privacy:options'], 'privacy:item');
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        return $collection;
    }

    /**
     * Return exact rows that reference a currently known Moodle username.
     *
     * @param string $table Source artifact or destination item
     * @param string $name Normalized account username
     * @return array Matching rows
     */
    private static function mappings(string $table, string $name): array {
        global $DB;
        if ($name === '') {
            return [];
        }
        $literal = json_encode($name, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $search = $DB->sql_like('options', ':name');
        $items = $DB->get_records_select($table, $search, ['name' => '%' . $DB->sql_like_escape($literal) . '%']);
        $field = $table === support::ARTIFACT ? 'sourceuser' : 'targetuser';
        return array_filter($items, static function ($row) use ($name, $field): bool {
            return in_array($name, array_column(support::decode($row->options)['staff'] ?? [], $field), true);
        });
    }

    /**
     * Find the system context for a referenced actor or mapped account.
     *
     * @param int $userid Moodle user
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        foreach (self::TABLES as $table) {
            if ($DB->record_exists($table, ['actor' => $userid])) {
                $list->add_system_context();
                return $list;
            }
        }
        $name = \core_user::get_user($userid)->username ?? '';
        if (self::mappings(support::ARTIFACT, $name) || self::mappings(support::ITEM, $name)) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Identify the users referenced by system-scoped records.
     *
     * @param userlist $userlist Users in a Moodle context
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql('actor', "SELECT DISTINCT actor FROM {{$table}} WHERE actor > 0");
        }
        $mapped = [];
        foreach ([support::ARTIFACT => 'sourceuser', support::ITEM => 'targetuser'] as $table => $field) {
            $records = $DB->get_recordset($table, [], '', 'id,options');
            try {
                foreach ($records as $record) {
                    foreach (array_column(support::decode($record->options)['staff'] ?? [], $field) as $name) {
                        $user = \core_user::get_user_by_username($name, 'id');
                        if ($user) {
                            $mapped[$user->id] = (int)$user->id;
                        }
                    }
                }
            } finally {
                $records->close();
            }
        }
        $userlist->add_users(array_values($mapped));
    }

    /**
     * Export operator metadata and individual mapping references.
     *
     * @param approved_contextlist $contextlist Approved account and contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            foreach (self::TABLES as $table) {
                foreach ($DB->get_records($table, ['actor' => $user->id]) as $row) {
                    $data = array_intersect_key((array)$row, array_flip(['id', 'courseid', 'state', 'created', 'action', 'jobid']));
                    writer::with_context($context)->export_data([support::C, $table, (string)$row->id], (object)$data);
                }
            }
            foreach (self::mappings(support::ITEM, $user->username) as $row) {
                $data = (object)['jobid' => $row->jobid, 'courseid' => $row->courseid, 'state' => $row->state];
                writer::with_context($context)->export_data([support::C, 'mapping', (string)$row->id], $data);
            }
        }
    }

    /**
     * Delete plugin data for an approved system context without deleting any Moodle course.
     *
     * @param context $context Approved scope
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach ($DB->get_records(support::JOB, [], 'id', 'id') as $job) {
            support::lock('job:' . $job->id, static function () use ($DB, $job): void {
                foreach ($DB->get_records(support::ITEM, ['jobid' => $job->id]) as $item) {
                    processor::clean($item);
                }
                $DB->delete_records(support::AUDIT, ['jobid' => $job->id]);
                $DB->delete_records(support::ITEM, ['jobid' => $job->id]);
                $DB->delete_records(support::JOB, ['id' => $job->id]);
            });
        }
        foreach ($DB->get_records(support::ARTIFACT, [], 'id', 'id') as $artifact) {
            self::drop_artifact((int)$artifact->id);
        }
    }

    /**
     * Remove one approved Moodle user's data.
     *
     * @param approved_contextlist $contextlist Approval
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::erase((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Remove all approved accounts from the system context.
     *
     * @param approved_userlist $userlist Approval
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $id) {
                self::erase((int)$id);
            }
        }
    }

    /**
     * Clean a private origin artifact under its worker lock.
     *
     * @param int $id Source artifact
     */
    private static function drop_artifact(int $id): void {
        global $DB;
        support::lock('build:' . $id, static function () use ($DB, $id): void {
            get_file_storage()->delete_area_files(\context_system::instance()->id, support::C, 'outgoing', $id);
            $DB->delete_records(support::ARTIFACT, ['id' => $id]);
        });
    }

    /**
     * Cancel pending data associated with one approved actor or mapping.
     *
     * @param int $userid Subject account
     */
    private static function erase(int $userid): void {
        global $DB;
        $name = \core_user::get_user($userid)->username ?? '';
        $owned = $DB->get_records(support::ARTIFACT, ['actor' => $userid], 'id', 'id');
        foreach (array_keys($owned + self::mappings(support::ARTIFACT, $name)) as $id) {
            self::drop_artifact((int)$id);
        }
        $jobs = $DB->get_fieldset_select(support::JOB, 'id', 'actor = :id', ['id' => $userid]);
        $matched = self::mappings(support::ITEM, $name);
        foreach (array_unique(array_merge($jobs, array_column($matched, 'jobid'))) as $id) {
            support::lock('job:' . $id, static function () use ($DB, $id, $userid, $name): void {
                $job = $DB->get_record(support::JOB, ['id' => $id]);
                if (!$job) {
                    return;
                }
                $owned = $job->actor == $userid;
                if ($owned) {
                    $job->actor = 0;
                }
                if (!in_array($job->state, ['complete', 'cancelled'], true)) {
                    $job->state = 'cancelled';
                    $job->wave++;
                }
                foreach ($DB->get_records(support::ITEM, ['jobid' => $id]) as $row) {
                    $opts = support::decode($row->options);
                    $opts['staff'] = $owned ? [] : array_values(array_filter(
                        $opts['staff'] ?? [],
                        static fn($entry) => $entry['targetuser'] !== $name
                    ));
                    $row->options = support::encode($opts);
                    if (!in_array($row->state, ['done', 'blocked', 'uncertain'], true)) {
                        $row->state = $row->state === 'restoring' ? 'uncertain' : 'cancelled';
                    }
                    processor::clean($row);
                    $DB->update_record(support::ITEM, $row);
                }
                $DB->update_record(support::JOB, $job);
            });
        }
        $DB->set_field(support::AUDIT, 'actor', 0, ['actor' => $userid]);
    }
}
