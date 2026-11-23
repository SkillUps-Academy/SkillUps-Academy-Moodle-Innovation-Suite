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

namespace local_skillupscertify;

use core\lock\lock_config;
use local_skillupscertify\credential\design;
use local_skillupscertify\evidence\moodle;
use moodle_exception;
use stdClass;

/**
 * Persist versioned templates, issue metadata and auditable operations.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class store {
    /** @var string Template table. */
    public const TEMPLATE = 'local_skillupscertify_template';

    /** @var string Template revisions. */
    public const REVISION = 'local_skillupscertify_revision';

    /** @var string Credential registry. */
    public const ISSUE = 'local_skillupscertify_issue';

    /** @var string Batch requests. */
    public const BATCH = 'local_skillupscertify_batch';

    /** @var string Batch recipients. */
    public const ITEM = 'local_skillupscertify_item';

    /** @var string Manager actions. */
    public const AUDIT = 'local_skillupscertify_audit';

    /**
     * Restrict a record to an explicit course.
     *
     * @param string $table Plugin table
     * @param int $courseid Course ID
     * @param int $id Record ID
     * @return stdClass
     */
    public static function scoped(string $table, int $courseid, int $id): stdClass {
        global $DB;
        $row = $DB->get_record($table, ['id' => $id, 'courseid' => $courseid]);
        if (!$row) {
            access::deny();
        }
        return $row;
    }

    /**
     * Return the revision pinned by a batch or the current revision of a template.
     *
     * @param stdClass $template Template
     * @param int $id Pinned revision ID, if known
     * @return stdClass
     */
    public static function revision(stdClass $template, int $id = 0): stdClass {
        global $DB;
        $params = ['templateid' => $template->id] + ($id ? ['id' => $id] : ['revision' => $template->revision]);
        return $DB->get_record(self::REVISION, $params, '*', MUST_EXIST);
    }

    /**
     * Validate source policy and design settings.
     *
     * @param stdClass $input Fields
     * @return array
     */
    public static function config(stdClass $input): array {
        $source = $input->source ?? '';
        $pathid = (int)($input->pathid ?? 0);
        if (
            !in_array($source, ['manual', 'course', 'pathway'], true) ||
            ($source === 'pathway' && (!$pathid || !moodle::pathways()))
        ) {
            throw new moodle_exception('invalidsource', access::C);
        }
        return [
            'design' => json_encode(design::normalize($input), JSON_THROW_ON_ERROR),
            'source' => $source,
            'pathid' => $source === 'pathway' ? $pathid : 0,
            'publicmeta' => (int)!empty($input->publicmeta),
        ];
    }

    /**
     * Create a new immutable edition when a draft is saved.
     *
     * @param stdClass $course Course
     * @param int $id Template ID or zero
     * @param stdClass $input Form fields
     * @return int
     */
    public static function save_template(stdClass $course, int $id, stdClass $input): int {
        global $DB;
        require_capability(access::CAP . 'manage', \context_course::instance($course->id));
        $name = trim(clean_param($input->label, PARAM_TEXT));
        if ($name === '' || \core_text::strlen($name) > 180) {
            throw new moodle_exception('invaliddesign', access::C);
        }
        $config = self::config($input);
        return self::atomic("templates:$course->id", static function () use ($DB, $course, $id, $input, $name, $config): int {
            $version = 1;
            if ($id) {
                $old = self::scoped(self::TEMPLATE, $course->id, $id);
                if ($old->revision != $input->revision) {
                    throw new moodle_exception('staledesign', access::C);
                }
                $version = 1 + (int)$old->revision;
            } else if ($DB->count_records(self::TEMPLATE, ['courseid' => $course->id]) >= 100) {
                throw new moodle_exception('templatelimit', access::C);
            }
            $record = [
                'courseid' => $course->id, 'label' => $name, 'revision' => $version,
                'enabled' => (int)!empty($input->enabled),
            ];
            if ($id) {
                $DB->update_record(self::TEMPLATE, $record + ['id' => $id]);
            } else {
                $id = (int)$DB->insert_record(self::TEMPLATE, $record + ['timecreated' => time()]);
            }
            $DB->insert_record(self::REVISION, $config + [
                'templateid' => $id, 'revision' => $version, 'timecreated' => time(),
            ]);
            self::audit($course->id, 'template', $id);
            return $id;
        });
    }

    /**
     * Execute with Moodle locking and database rollback on failure.
     *
     * @param string $key Operation scope
     * @param callable $operation Work
     * @return mixed
     */
    public static function atomic(string $key, callable $operation): mixed {
        global $DB;
        $lock = lock_config::get_lock_factory(access::C)->get_lock($key, 5);
        if (!$lock) {
            throw new moodle_exception('busy', access::C);
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $result = $operation();
            } catch (\Throwable $failure) {
                $transaction->rollback($failure);
                throw $failure;
            }
            $transaction->allow_commit();
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Emit both a plugin audit row and Moodle audit event.
     *
     * @param int $courseid Course
     * @param string $action Operation
     * @param int $objectid Registry or template reference
     */
    public static function audit(int $courseid, string $action, int $objectid): void {
        global $DB, $USER;
        $id = $DB->insert_record(self::AUDIT, [
            'courseid' => $courseid, 'userid' => (int)$USER->id,
            'action' => $action, 'objectid' => $objectid, 'timecreated' => time(),
        ]);
        event\operation_recorded::create([
            'context' => \context_course::instance($courseid),
            'objectid' => $id,
            'other' => ['action' => $action, 'reference' => $objectid],
        ])->trigger();
    }

    /**
     * Read the immutable data stored at issue time.
     *
     * @param stdClass $issue Credential record
     * @return array
     */
    public static function snapshot(stdClass $issue): array {
        return json_decode($issue->snapshot, true, 16, JSON_THROW_ON_ERROR);
    }
}
