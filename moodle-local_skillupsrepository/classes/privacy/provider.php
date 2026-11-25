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

namespace local_skillupsrepository\privacy;

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
use local_skillupsrepository\access;
use local_skillupsrepository\files;
use local_skillupsrepository\observer;
use local_skillupsrepository\store;

/**
 * Report, export and erase approved personal data in Moodle category contexts.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements core_userlist_provider, metadataprovider, requestprovider {
    /** @var array Subject reference columns indexed by table. */
    private const USERS = [
        store::ASSET => ['ownerid'], store::REVISION => ['authorid', 'reviewerid'],
        store::USAGE => ['userid'], store::AUDIT => ['userid'],
    ];

    /**
     * Describe stored personal data and native file storage.
     *
     * @param collection $collection Native metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::USERS as $table => $fields) {
            if ($table === store::REVISION) {
                $fields = array_merge($fields, ['title', 'description', 'credit', 'sourceurl', 'reviewnote']);
            }
            $labels = [];
            foreach ($fields as $field) {
                $labels[$field] = 'privacy:field:' . $field;
            }
            $collection->add_database_table($table, $labels, 'privacy:' . substr($table, strlen(access::COMPONENT) + 1));
        }
        $collection->add_subsystem_link('core_files', [], 'privacy:files');
        return $collection;
    }

    /**
     * Produce a table expression and the source category field.
     *
     * @param string $table Owned table
     * @return array
     */
    private static function source(string $table): array {
        if ($table === store::ASSET) {
            return ['{' . store::ASSET . '} r', 'r.categoryid'];
        }
        return ['{' . $table . '} r JOIN {' . store::ASSET . '} a ON a.id = r.assetid', 'a.categoryid'];
    }

    /**
     * Find the owning category contexts with records about the account.
     *
     * @param int $userid Moodle account ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::USERS as $table => $fields) {
            [$from, $category] = self::source($table);
            foreach ($fields as $field) {
                $list->add_from_sql(
                    "SELECT DISTINCT c.id FROM $from JOIN {context} c ON c.instanceid = $category
                      WHERE c.contextlevel = :level AND r.$field = :userid",
                    ['level' => CONTEXT_COURSECAT, 'userid' => $userid]
                );
            }
        }
        return $list;
    }

    /**
     * Find the users represented in the chosen category.
     *
     * @param userlist $userlist Native request list
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSECAT) {
            return;
        }
        foreach (self::USERS as $table => $fields) {
            [$from, $category] = self::source($table);
            foreach ($fields as $field) {
                $userlist->add_from_sql(
                    $field,
                    "SELECT DISTINCT r.$field FROM $from WHERE $category = :category AND r.$field > 0",
                    ['category' => $context->instanceid]
                );
            }
        }
    }

    /**
     * Obtain records where a specific approved user contributed or acted.
     *
     * @param string $table Owned table
     * @param int $categoryid Owning category
     * @param int $userid Approved user
     * @return \moodle_recordset
     */
    private static function records(string $table, int $categoryid, int $userid): \moodle_recordset {
        global $DB;
        [$from, $category] = self::source($table);
        $params = ['category' => $categoryid];
        $conditions = [];
        foreach (self::USERS[$table] as $field) {
            $conditions[] = "r.$field = :$field";
            $params[$field] = $userid;
        }
        $subjectsql = implode(' OR ', $conditions);
        return $DB->get_recordset_sql("SELECT r.* FROM $from WHERE $category = :category AND ($subjectsql) ORDER BY r.id", $params);
    }

    /**
     * Export owned revisions and the user's own operational metadata.
     *
     * @param approved_contextlist $contextlist Approved contexts and subject
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSECAT) {
                continue;
            }
            foreach (self::USERS as $table => $fields) {
                $records = self::records($table, $context->instanceid, $userid);
                try {
                    foreach ($records as $row) {
                        $path = [get_string('pluginname', access::COMPONENT), $table, (string)$row->id];
                        $data = ['asset' => $row->assetid ?? $row->id];
                        foreach ($fields as $field) {
                            $data[$field] = $row->$field == $userid;
                        }
                        if ($table === store::REVISION) {
                            $data += array_intersect_key((array)$row, array_flip(['state', 'number', 'created', 'reviewed']));
                            if ($row->authorid == $userid) {
                                $data += array_intersect_key((array)$row, array_flip([
                                    'title', 'description', 'credit', 'tags', 'keywords', 'sourceurl', 'license',
                                ]));
                                writer::with_context($context)->export_area_files(
                                    $path,
                                    access::COMPONENT,
                                    files::AREA,
                                    $row->id
                                );
                            }
                            if ($row->reviewerid == $userid) {
                                $data['reviewnote'] = $row->reviewnote;
                            }
                        } else if ($table === store::AUDIT || $table === store::USAGE) {
                            $data += array_intersect_key((array)$row, array_flip(['action', 'revisionid', 'created']));
                        }
                        writer::with_context($context)->export_data($path, (object)$data);
                    }
                } finally {
                    $records->close();
                }
            }
        }
    }

    /**
     * Erase all library records for an approved category.
     *
     * @param context $context Approved Moodle category context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            observer::purge($context->instanceid);
        }
    }

    /**
     * Erase an approved user's data.
     *
     * @param approved_contextlist $contextlist Approved contexts and subject
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::erase($context, (int)$contextlist->get_user()->id);
        }
    }

    /**
     * Erase the approved subjects within their approved category.
     *
     * @param approved_userlist $userlist Approved subjects
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            self::erase($userlist->get_context(), (int)$userid);
        }
    }

    /**
     * Remove owned assets and scrub secondary contributions and operation actors.
     *
     * @param context $context Moodle category context
     * @param int $userid User approved for erasure
     */
    private static function erase(context $context, int $userid): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSECAT) {
            return;
        }
        store::write($context->instanceid, static function () use ($DB, $context, $userid): void {
            $fs = get_file_storage();
            $params = ['ownerid' => $userid, 'categoryid' => $context->instanceid];
            $owned = 'assetid IN (SELECT id FROM {' . store::ASSET . '} WHERE ownerid = :ownerid AND categoryid = :categoryid)';
            $items = $DB->get_recordset_select(store::REVISION, $owned, $params, 'id ASC', 'id');
            try {
                foreach ($items as $item) {
                    $fs->delete_area_files($context->id, access::COMPONENT, files::AREA, $item->id);
                }
            } finally {
                $items->close();
            }
            foreach ([store::USAGE, store::AUDIT, store::REVISION] as $table) {
                $DB->delete_records_select($table, $owned, $params);
            }
            $DB->delete_records(store::ASSET, $params);
            $affected = [];
            $records = self::records(store::REVISION, $context->instanceid, $userid);
            try {
                foreach ($records as $row) {
                    if ($row->authorid == $userid) {
                        $fs->delete_area_files($context->id, access::COMPONENT, files::AREA, $row->id);
                        $clear = [
                            'title', 'filename', 'mimetype', 'collection', 'area', 'program',
                            'level', 'credit', 'tags', 'keywords',
                        ];
                        foreach ($clear as $field) {
                            $row->$field = '';
                        }
                        $row->state = 'erased';
                        $row->authorid = 0;
                        $row->reviewerid = 0;
                        $row->allowcopy = 0;
                        $row->allowdownload = 0;
                        $row->bytes = 0;
                        $row->sourceurl = null;
                        $row->description = null;
                        $row->reviewnote = null;
                        $affected[$row->assetid] = true;
                    } else if ($row->reviewerid == $userid) {
                        $row->reviewerid = 0;
                        $row->reviewnote = null;
                    }
                    $DB->update_record(store::REVISION, $row);
                }
            } finally {
                $records->close();
            }
            foreach (array_keys($affected) as $assetid) {
                $asset = store::get(store::ASSET, (int)$assetid);
                $conditions = ['assetid' => $assetid, 'state' => 'approved'];
                $published = $DB->get_records(store::REVISION, $conditions, 'number DESC', 'id', 0, 1);
                $asset->publishedid = $published ? reset($published)->id : 0;
                $asset->generation++;
                $DB->update_record(store::ASSET, $asset);
            }
            $where = 'userid = :userid AND assetid IN (SELECT id FROM {' . store::ASSET . '} WHERE categoryid = :cat)';
            $bindings = ['userid' => $userid, 'cat' => $context->instanceid];
            foreach ([store::AUDIT, store::USAGE] as $table) {
                $DB->set_field_select($table, 'userid', 0, $where, $bindings);
            }
        });
    }
}
