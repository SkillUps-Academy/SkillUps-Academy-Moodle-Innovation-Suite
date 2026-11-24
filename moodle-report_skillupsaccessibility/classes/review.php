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

namespace report_skillupsaccessibility;

use stdClass;

/**
 * Record remediation status, assignees, evidence and decision history.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review {
    /** @var string File API area. */
    public const AREA = 'evidence';
    /** @var array Supported remediation states. */
    public const STATES = ['pending', 'corrected', 'dismissed'];

    /**
     * Return Moodle filemanager restrictions.
     *
     * @return array
     */
    public static function options(): array {
        global $CFG;
        return [
            'subdirs' => 0, 'maxfiles' => 3,
            'maxbytes' => min(5 * 1024 * 1024, get_max_upload_file_size($CFG->maxbytes)),
            'accepted_types' => ['.pdf', '.png', '.jpg', '.jpeg', '.txt'],
        ];
    }

    /**
     * Resolve an authorized finding and its authored source.
     *
     * @param int $id Workflow finding ID
     * @return array Finding, source and course
     */
    public static function open(int $id): array {
        $finding = store::get(store::FINDING, $id);
        $asset = store::get(store::ASSET, (int)$finding->assetid);
        $course = get_course($asset->courseid);
        if (!scope::asset($course, $asset)) {
            scope::fail();
        }
        return [$finding, $asset, $course];
    }

    /**
     * Record the review and attach private evidence.
     *
     * @param int $id Finding ID
     * @param stdClass $input Review form
     * @return int Review record ID
     */
    public static function save(int $id, stdClass $input): int {
        global $DB, $USER, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        [, , $course] = self::open($id);
        return store::lock((int)$course->id, static function () use ($DB, $USER, $id, $input): int {
            return store::transaction(static function () use ($DB, $USER, $id, $input): int {
                [$finding, $asset, $course] = self::open($id);
                if (!scope::allowed($course, 'review')) {
                    scope::fail();
                }
                $notes = trim($input->notes ?? '');
                $assignee = (int)$input->assignee;
                if (!in_array($input->state, self::STATES, true) || $notes === '' || \core_text::strlen($notes) > 4000) {
                    scope::fail('invalidreview');
                }
                if ($assignee) {
                    $user = $DB->get_record('user', ['id' => $assignee, 'deleted' => 0, 'suspended' => 0], 'id');
                    if (!$user || !scope::allowed($course, 'review', $assignee) || !scope::asset($course, $asset, $assignee)) {
                        scope::fail('invalidreview');
                    }
                }
                $record = $DB->insert_record(store::REVIEW, (object)[
                    'courseid' => $course->id, 'findingid' => $id, 'authorid' => $USER->id, 'assignee' => $assignee,
                    'state' => $input->state, 'notes' => $notes, 'created' => time(),
                ]);
                $DB->update_record(store::FINDING, (object)[
                    'id' => $finding->id, 'state' => $input->state, 'assignee' => $assignee,
                ]);
                if (!empty($input->evidence)) {
                    file_save_draft_area_files(
                        $input->evidence,
                        \context_course::instance($course->id)->id,
                        scope::C,
                        self::AREA,
                        $record,
                        self::options(),
                    );
                }
                return (int)$record;
            });
        });
    }
}
