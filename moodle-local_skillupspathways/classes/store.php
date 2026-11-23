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

namespace local_skillupspathways;

use core\lock\lock_config;
use moodle_exception;
use stdClass;

/**
 * Course-scoped drafts, frozen versions and atomic commits.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Itinerary table. */
    public const PATH = 'local_skillupspathways_path';

    /** @var string Immutable revisions. */
    public const VERSION = 'local_skillupspathways_version';

    /** @var string One pinned learner version per path. */
    public const PROGRESS = 'local_skillupspathways_progress';

    /** @var string Learner transitions. */
    public const HISTORY = 'local_skillupspathways_history';

    /** @var string Course administration audit. */
    public const AUDIT = 'local_skillupspathways_audit';

    /**
     * Get an itinerary belonging to the current entry course.
     *
     * @param int $courseid Entry course
     * @param int $pathid Path
     * @return stdClass
     */
    public static function path(int $courseid, int $pathid): stdClass {
        global $DB;
        $path = $DB->get_record(self::PATH, ['id' => $pathid, 'courseid' => $courseid]);
        if (!$path) {
            access::deny();
        }
        return $path;
    }

    /**
     * Read an immutable version in its owning itinerary.
     *
     * @param int $pathid Path
     * @param int $versionid Published version ID
     * @return stdClass
     */
    public static function version(int $pathid, int $versionid): stdClass {
        global $DB;
        return $DB->get_record(self::VERSION, ['id' => $versionid, 'pathid' => $pathid], '*', MUST_EXIST);
    }

    /**
     * Create or save a draft with optimistic concurrency per itinerary.
     *
     * @param int $courseid Entry course
     * @param int $id Path, or zero to create
     * @param int $expected Draft revision from the form
     * @param stdClass $input Submitted fields
     * @param bool $publish Save and publish as a new immutable version
     * @return int Path ID
     */
    public static function save(int $courseid, int $id, int $expected, stdClass $input, bool $publish): int {
        global $DB;
        access::manage($courseid);
        $name = trim($input->name);
        $description = trim($input->description);
        if ($name === '' || \core_text::strlen($name) > 180 || \core_text::strlen($description) > 5000) {
            throw new moodle_exception('invalidgraph', access::C);
        }
        $draft = graph::parse($input->graph, $publish);
        evidence::author($draft);
        $data = [
            'courseid' => $courseid, 'name' => $name, 'description' => $description,
            'draft' => json_encode($draft, JSON_THROW_ON_ERROR), 'timemodified' => time(),
            'enabled' => (int)!empty($input->enabled), 'certificate' => (int)!empty($input->certificate),
        ];
        return self::atomic($id ? 'path:' . $id : 'new:' . $courseid, static function () use (
            $DB,
            $id,
            $expected,
            $courseid,
            $data,
            $publish
        ): int {
            if ($id) {
                $current = self::path($courseid, $id);
                if ($current->revision != $expected) {
                    throw new moodle_exception('draftchanged', access::C);
                }
                $pathid = $id;
                $DB->update_record(self::PATH, $data + ['id' => $id, 'revision' => $expected + 1]);
            } else {
                if ($DB->count_records(self::PATH, ['courseid' => $courseid]) >= 100) {
                    throw new moodle_exception('cataloglimit', access::C);
                }
                $pathid = (int)$DB->insert_record(self::PATH, $data);
            }
            if ($publish) {
                $table = self::VERSION;
                $next = 1 + (int)$DB->get_field_sql("SELECT MAX(versionnum) FROM {{$table}} WHERE pathid = ?", [$pathid]);
                $version = $DB->insert_record(self::VERSION, [
                    'pathid' => $pathid, 'versionnum' => $next, 'name' => $data['name'], 'description' => $data['description'],
                    'graph' => $data['draft'], 'timecreated' => time(),
                ]);
                $DB->set_field(self::PATH, 'activeversion', $version, ['id' => $pathid]);
            }
            self::audit($courseid, $pathid, $publish ? 'published' : 'draft');
            return $pathid;
        });
    }

    /**
     * Execute a scoped Moodle lock and delegated transaction.
     *
     * @param string $key Scope key
     * @param callable $operation Callback
     * @return mixed
     */
    public static function atomic(string $key, callable $operation): mixed {
        global $DB;
        $lock = lock_config::get_lock_factory(access::C)->get_lock($key, 5);
        if (!$lock) {
            throw new moodle_exception('busy', access::C);
        }
        try {
            $tx = $DB->start_delegated_transaction();
            try {
                $result = $operation();
            } catch (\Throwable $failure) {
                $tx->rollback($failure);
                throw $failure;
            }
            $tx->allow_commit();
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Audit a configuration, migration or export action.
     *
     * @param int $courseid Entry course
     * @param int $pathid Path
     * @param string $operation Operation name
     */
    public static function audit(int $courseid, int $pathid, string $operation): void {
        global $DB, $USER;
        $id = $DB->insert_record(self::AUDIT, [
            'pathid' => $pathid, 'userid' => $USER->id, 'operation' => $operation, 'timecreated' => time(),
        ]);
        event\operation_recorded::create([
            'context' => \context_course::instance($courseid),
            'objectid' => $id,
            'other' => ['operation' => $operation, 'pathid' => $pathid],
        ])->trigger();
    }

    /**
     * Remove all local data associated with a deleted entry course.
     *
     * @param int $courseid Deleted course
     */
    public static function delete_course(int $courseid): void {
        global $DB;
        foreach ($DB->get_records(self::PATH, ['courseid' => $courseid], 'id', 'id') as $path) {
            self::atomic('path:' . $path->id, static function () use ($DB, $path): void {
                foreach ([self::HISTORY, self::PROGRESS, self::AUDIT, self::VERSION] as $table) {
                    $DB->delete_records($table, ['pathid' => $path->id]);
                }
                $DB->delete_records(self::PATH, ['id' => $path->id]);
            });
        }
    }
}
