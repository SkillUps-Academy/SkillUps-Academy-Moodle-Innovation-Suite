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

namespace local_skillupsquestionstudio;

/**
 * Course project state, optimistic revisions and explicit review.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class studio {
    /**
     * A project is bounded independently from the course enrolment size.
     */
    public const MAX_ITEMS = 400;

    /**
     * Create a project visible only to authorized colleagues in this course.
     */
    public static function create(int $courseid, int $userid, string $name): int {
        global $DB;
        $id = $DB->insert_record(access::C . '_project', (object)[
            'courseid' => $courseid, 'userid' => $userid, 'name' => question::text($name, 150),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        self::audit($id, 'projectcreated');
        return $id;
    }

    /**
     * Load only questions that belong to this project.
     */
    public static function item(int $projectid, int $id): \stdClass {
        global $DB;
        return $DB->get_record(access::C . '_item', ['projectid' => $projectid, 'id' => $id], '*', MUST_EXIST);
    }

    /**
     * Check one revision against the item's own history.
     */
    public static function revision(int $itemid, int $id): \stdClass {
        global $DB;
        return $DB->get_record(access::C . '_rev', ['itemid' => $itemid, 'id' => $id], '*', MUST_EXIST);
    }

    /**
     * Validate and save author changes under a single project lock.
     */
    public static function save(\stdClass $project, int $id, int $base, array $data, int $userid): int {
        global $DB;
        $data = question::validate($data);
        competencies::require_valid($project->courseid, $data['competency']);
        $lock = access::lock($project->id);
        try {
            $item = $id ? self::item($project->id, $id) : null;
            if ($item && (int)$item->currentid !== $base) {
                access::fail('editconflict');
            }
            $fingerprint = question::fingerprint($data);
            if (self::duplicate($project->id, $fingerprint, $id)) {
                access::fail('duplicate');
            }
            if (!$item && $DB->count_records(access::C . '_item', ['projectid' => $project->id]) >= self::MAX_ITEMS) {
                access::fail('itemlimit');
            }
            $tx = $DB->start_delegated_transaction();
            try {
                $id = self::write($project->id, $data, $userid, $item, 0, 'manual');
                self::audit($project->id, 'revisioncreated', $id);
                $tx->allow_commit();
                return $id;
            } catch (\Throwable $exception) {
                $tx->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Write one validated revision inside the caller's lock and transaction.
     */
    public static function write(int $projectid, array $data, int $userid, ?\stdClass $item, int $sourceid, string $origin): int {
        global $DB;
        $now = time();
        $hash = question::fingerprint($data);
        if ($item) {
            $id = $item->id;
            $seq = self::revision($id, $item->currentid)->seq + 1;
        } else {
            $id = $DB->insert_record(access::C . '_item', (object)[
                'projectid' => $projectid, 'sourceid' => $sourceid, 'userid' => $userid, 'kind' => $data['kind'],
                'origin' => $origin, 'fingerprint' => $hash, 'timemodified' => $now,
            ]);
            $seq = 1;
        }
        $revision = $DB->insert_record(access::C . '_rev', (object)[
            'itemid' => $id, 'userid' => $userid, 'seq' => $seq, 'timecreated' => $now,
            'contents' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $DB->update_record(access::C . '_item', (object)[
            'id' => $id, 'currentid' => $revision, 'approved' => 0, 'reviewer' => 0,
            'fingerprint' => $hash, 'kind' => $data['kind'], 'timemodified' => $now,
        ]);
        $DB->set_field(access::C . '_project', 'timemodified', $now, ['id' => $projectid]);
        return (int)$id;
    }

    /**
     * Exact normalized duplicates use a database-backed per-project guard.
     */
    public static function duplicate(int $id, string $fingerprint, int $exclude = 0): bool {
        global $DB;
        return $DB->record_exists_select(
            access::C . '_item',
            'projectid = ? AND fingerprint = ? AND id <> ?',
            [$id, $fingerprint, $exclude]
        );
    }

    /**
     * Find a likely duplicate with token-set similarity without an external service.
     */
    public static function nearest(int $projectid, array $data, int $exclude): int {
        global $DB;
        $terms = array_unique(explode(' ', question::normalize($data['prompt'])));
        if (count($terms) < 5) {
            return 0;
        }
        $sql = 'SELECT i.id, r.contents FROM {' . access::C . '_item} i
            JOIN {' . access::C . '_rev} r ON r.id = i.currentid WHERE i.projectid = ? AND i.id <> ?';
        $records = $DB->get_recordset_sql($sql, [$projectid, $exclude]);
        try {
            foreach ($records as $item) {
                $other = array_unique(explode(' ', question::normalize(json_decode($item->contents, true)['prompt'])));
                $denominator = count(array_unique(array_merge($terms, $other)));
                if ($denominator && count(array_intersect($terms, $other)) / $denominator >= 0.85) {
                    return (int)$item->id;
                }
            }
        } finally {
            $records->close();
        }
        return 0;
    }

    /**
     * Approve the exact revision the reviewer has visibly inspected.
     */
    public static function review(\stdClass $project, int $id, int $revision, bool $approve, int $userid): void {
        global $DB;
        access::actor($userid, $project->courseid, 'review');
        $lock = access::lock($project->id);
        try {
            $item = self::item($project->id, $id);
            if ((int)$item->currentid !== $revision) {
                access::fail('editconflict');
            }
            $data = question::validate(json_decode(self::revision($id, $revision)->contents, true));
            competencies::require_valid($project->courseid, $data['competency']);
            $DB->update_record(access::C . '_item', (object)[
                'id' => $id, 'approved' => $approve ? $revision : 0, 'reviewer' => $userid, 'timemodified' => time(),
            ]);
            self::audit($project->id, $approve ? 'approved' : 'reopened', $revision);
        } finally {
            $lock->release();
        }
    }

    /**
     * Emit a Moodle audit event with minimal numeric references.
     */
    public static function audit(int $projectid, string $action, int $reference = 0): void {
        global $DB;
        $record = $DB->get_record(access::C . '_project', ['id' => $projectid], '*', MUST_EXIST);
        \local_skillupsquestionstudio\event\studio_changed::create([
            'context' => access::context($record->courseid), 'objectid' => $projectid,
            'other' => ['action' => $action, 'reference' => $reference],
        ])->trigger();
    }
}
