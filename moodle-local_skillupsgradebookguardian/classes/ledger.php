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

namespace local_skillupsgradebookguardian;

use context_course;
use core\lock\lock_config;
use moodle_exception;
use stdClass;

/**
 * Course-scoped diagnostic and decisions ledger.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ledger {
    /** @var string Plugin-owned table. */
    public const TABLE = 'local_skillupsgradebookguardian_log';

    /**
     * Serialize plugin operations per course.
     *
     * @param int $courseid Course ID
     * @param callable $operation Operation to run
     * @return mixed
     */
    public static function locked(int $courseid, callable $operation): mixed {
        $lock = lock_config::get_lock_factory(access::COMPONENT)->get_lock((string)$courseid, 3);
        if (!$lock) {
            throw new moodle_exception('busy', access::COMPONENT);
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    /**
     * Record a new bounded diagnosis for the currently authorized operator.
     *
     * @param stdClass $course Course record
     * @return int Log ID
     */
    public static function diagnose(stdClass $course): int {
        global $DB;
        $context = context_course::instance($course->id);
        access::require($context, 'diagnose');
        return self::locked((int)$course->id, static function () use ($course, $DB): int {
            if (
                $DB->record_exists_select(
                    self::TABLE,
                    'courseid = :course AND kind = :kind AND timecreated > :cutoff',
                    ['course' => $course->id, 'kind' => 'diagnosis', 'cutoff' => time() - 10]
                )
            ) {
                throw new moodle_exception('cooldown', access::COMPONENT);
            }
            return self::append((int)$course->id, 'diagnosis', rules::diagnose(new snapshot($course)));
        });
    }

    /**
     * Append an immutable operator entry.
     *
     * @param int $courseid Course ID
     * @param string $kind Entry type
     * @param array $payload Evidence, preview or change data
     * @param int $parentid Parent entry ID
     * @return int Log ID
     */
    public static function append(int $courseid, string $kind, array $payload, int $parentid = 0): int {
        global $DB, $USER;
        $id = $DB->insert_record(self::TABLE, [
            'courseid' => $courseid, 'kind' => $kind, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'parentid' => $parentid, 'userid' => $USER->id, 'timecreated' => time(),
        ]);
        self::event($courseid, $id, $kind);
        return (int)$id;
    }

    /**
     * Log a course-scoped event without including grade or configuration details.
     *
     * @param int $courseid Course ID
     * @param int $id Ledger ID
     * @param string $operation Event operation
     */
    public static function event(int $courseid, int $id, string $operation): void {
        $event = event\operation_recorded::create([
            'context' => context_course::instance($courseid),
            'objectid' => $id,
            'other' => ['operation' => $operation],
        ]);
        $event->trigger();
    }

    /**
     * Retrieve a specific entry constrained by course and, optionally, type.
     *
     * @param int $courseid Course ID
     * @param int $id Log ID
     * @param string $kind Optional type
     * @return stdClass
     */
    public static function get(int $courseid, int $id, string $kind = ''): stdClass {
        global $DB;
        $criteria = ['id' => $id, 'courseid' => $courseid];
        if ($kind !== '') {
            $criteria['kind'] = $kind;
        }
        $entry = $DB->get_record(self::TABLE, $criteria, '*', MUST_EXIST);
        $entry->payload = json_decode($entry->payload, true, 32, JSON_THROW_ON_ERROR);
        return $entry;
    }

    /**
     * Find the direct approval or recovery entry for a preview.
     *
     * @param int $courseid Course ID
     * @param int $previewid Preview ID
     * @param string $kind Entry type
     * @return int Entry ID or zero
     */
    public static function decision(int $courseid, int $previewid, string $kind): int {
        global $DB;
        return (int)$DB->get_field(self::TABLE, 'id', ['courseid' => $courseid, 'parentid' => $previewid, 'kind' => $kind]);
    }

    /**
     * List course entries without loading historical payloads.
     *
     * @param int $courseid Course ID
     * @param string $kind Optional type
     * @param int $page Page number
     * @return array Total and records
     */
    public static function history(int $courseid, string $kind, int $page): array {
        global $DB;
        $criteria = ['courseid' => $courseid];
        if ($kind !== '') {
            $criteria['kind'] = $kind;
        }
        return [
            $DB->count_records(self::TABLE, $criteria),
            $DB->get_records(self::TABLE, $criteria, 'id DESC', 'id,kind,parentid,userid,timecreated', $page * 20, 20),
        ];
    }
}
