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
 * Stable alternative assessments derived only from approved current revisions.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assessment {
    /**
     * Store a randomized, reproducible selection scoped to one project.
     */
    public static function create(\stdClass $project, string $name, int $size, int $userid): int {
        global $DB;
        if ($size < 1 || $size > 30) {
            access::fail('invalidvariant');
        }
        $name = question::text($name, 150);
        $lock = access::lock($project->id);
        try {
            if ($DB->count_records(access::C . '_variant', ['projectid' => $project->id]) >= 100) {
                access::fail('variantlimit');
            }
            $items = $DB->get_records_select(
                access::C . '_item',
                'projectid = ? AND currentid = approved AND approved > 0',
                [$project->id],
                'id',
                'id,currentid'
            );
            if (count($items) < $size) {
                access::fail('invalidvariant');
            }
            $references = [];
            $ordered = array_slice(question::ranked($items, bin2hex(random_bytes(16))), 0, $size);
            foreach ($ordered as $item) {
                $references[] = ['itemid' => (int)$item->id, 'revisionid' => (int)$item->currentid,
                    'seed' => bin2hex(random_bytes(12))];
            }
            $id = $DB->insert_record(access::C . '_variant', (object)[
                'projectid' => $project->id, 'userid' => $userid, 'name' => $name, 'timecreated' => time(),
                'refs' => json_encode($references, JSON_THROW_ON_ERROR),
            ]);
            studio::audit($project->id, 'variantcreated', $id);
            return $id;
        } finally {
            $lock->release();
        }
    }

    /**
     * Resolve a variant in the current project.
     */
    public static function find(int $projectid, int $id): \stdClass {
        global $DB;
        return $DB->get_record(access::C . '_variant', ['id' => $id, 'projectid' => $projectid], '*', MUST_EXIST);
    }

    /**
     * Recheck each pinned approval; editing or reopening makes the variant stale.
     */
    public static function questions(\stdClass $project, \stdClass $variant): array {
        $rows = [];
        $refs = json_decode($variant->refs, true, 8, JSON_THROW_ON_ERROR);
        foreach ($refs as $ref) {
            $item = studio::item($project->id, $ref['itemid']);
            if ((int)$item->approved !== $ref['revisionid'] || (int)$item->currentid !== $ref['revisionid']) {
                access::fail('stalevariant');
            }
            $revision = studio::revision($item->id, $ref['revisionid']);
            $data = question::validate(json_decode($revision->contents, true, 10, JSON_THROW_ON_ERROR));
            competencies::require_valid($project->courseid, $data['competency']);
            $rows[$revision->id] = question::reorder($data, $ref['seed']);
        }
        return $rows;
    }
}
