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

namespace local_skillupscompetency;

use core_competency\api;
use core_competency\user_competency_course;
use stdClass;

/**
 * Submit competency proposals and validate them through the native rating API.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assessment {
    /** @var string Private evidence area. */
    public const AREA = 'evidence';

    /**
     * Return the Moodle filemanager constraints.
     *
     * @return array
     */
    public static function options(): array {
        global $CFG;
        return [
            'subdirs' => 0, 'maxfiles' => 3, 'maxbytes' => min(5 * 1024 * 1024, get_max_upload_file_size($CFG->maxbytes)),
            'accepted_types' => ['.pdf', '.docx', '.png', '.jpg', '.jpeg'],
        ];
    }

    /**
     * Read the native course rating without creating a relation.
     *
     * @param int $courseid Moodle course ID
     * @param int $userid Learner ID
     * @param int $competencyid Native competency ID
     * @return int
     */
    public static function current(int $courseid, int $userid, int $competencyid): int {
        $item = user_competency_course::get_record([
            'courseid' => $courseid, 'userid' => $userid, 'competencyid' => $competencyid,
        ]);
        return $item ? (int)$item->get('grade') : 0;
    }

    /**
     * Create a pending competency proposal and attach private evidence.
     *
     * @param stdClass $course Moodle course record
     * @param int $userid Learner ID
     * @param int $competencyid Native competency ID
     * @param int $cmid Optional course activity ID
     * @param stdClass $data Validated proposal or matrix values
     * @return int
     */
    public static function submit(stdClass $course, int $userid, int $competencyid, int $cmid, stdClass $data): int {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/filelib.php');
        $context = \context_course::instance($course->id);
        $self = (int)$USER->id === $userid;
        require_capability(access::CAP . ($self ? 'self' : 'assess'), $context);
        if (!access::can_see_subject($course, $userid, $cmid)) {
            access::fail();
        }
        $item = access::linked($course, $competencyid);
        if ($cmid) {
            access::linked($course, $competencyid, $cmid);
        }
        $levels = catalog::levels($item);
        $level = (int)($data->levelvalue ?? 0);
        $detail = null;
        $kind = $self ? 'self' : 'teacher';
        if (!empty($data->rubric)) {
            if ($self || !$cmid) {
                access::fail('invalidrubric');
            }
            $definition = rubric::definition($course, $cmid);
            if (!$definition || !hash_equals(rubric::signature($definition), $data->rubrichash ?? '')) {
                access::fail('stalerubric');
            }
            [$level, $detail] = rubric::evaluate($definition, (array)($data->criteria ?? []), count($levels));
            $kind = 'rubric';
        }
        $observation = trim($data->observations ?? '');
        if (
            !isset($levels[$level]) || $observation === '' || \core_text::strlen($observation) > 4000 ||
            !preg_match('/^[a-f0-9]{32}$/D', $data->requestkey ?? '')
        ) {
            access::fail('invalidinput');
        }
        return store::write(static function () use (
            $DB,
            $USER,
            $course,
            $context,
            $userid,
            $competencyid,
            $cmid,
            $data,
            $item,
            $kind,
            $level,
            $detail,
            $observation
        ): int {
            if ($DB->record_exists(store::ASSESS, ['requestkey' => $data->requestkey])) {
                access::fail('alreadyfiled');
            }
            $id = $DB->insert_record(store::ASSESS, (object)[
                'courseid' => $course->id, 'userid' => $userid, 'competencyid' => $competencyid, 'cmid' => $cmid,
                'authorid' => $USER->id, 'scaleid' => $item->get_scale()->id,
                'basegrade' => self::current((int)$course->id, $userid, $competencyid), 'levelvalue' => $level,
                'status' => 'pending', 'kind' => $kind, 'observations' => $observation, 'requestkey' => $data->requestkey,
                'rubricdata' => $detail ? json_encode($detail, JSON_THROW_ON_ERROR) : null, 'created' => time(),
            ]);
            if (!empty($data->attachments)) {
                file_save_draft_area_files($data->attachments, $context->id, access::C, self::AREA, $id, self::options());
            }
            self::event($id, $context, $userid, 'pending');
            return (int)$id;
        });
    }

    /**
     * Save a coordinator decision and publish approved ratings.
     *
     * @param int $id Record ID
     * @param bool $approve Whether to publish the proposal
     * @param string $feedback Reviewer feedback
     */
    public static function decide(int $id, bool $approve, string $feedback): void {
        global $DB, $USER;
        store::write(static function () use ($DB, $USER, $id, $approve, $feedback): void {
            $record = store::get(store::ASSESS, $id);
            $course = get_course($record->courseid);
            $context = \context_course::instance($course->id);
            require_capability(access::CAP . 'review', $context);
            if (!access::read_assessment($record) || $record->authorid == $USER->id || $record->userid == $USER->id) {
                access::fail();
            }
            $feedback = trim($feedback);
            if ($record->status !== 'pending') {
                access::fail('notpending');
            }
            if ($feedback === '' || \core_text::strlen($feedback) > 4000) {
                access::fail('invalidinput');
            }
            if ($approve) {
                require_capability('moodle/competency:competencygrade', $context);
                $item = access::linked($course, (int)$record->competencyid);
                $current = self::current((int)$course->id, (int)$record->userid, (int)$record->competencyid);
                if (
                    $item->get_scale()->id != $record->scaleid || !isset(catalog::levels($item)[$record->levelvalue]) ||
                    $current !== (int)$record->basegrade
                ) {
                    access::fail('stalelevel');
                }
                $core = api::grade_competency_in_course($course, $record->userid, $record->competencyid, $record->levelvalue);
                if (!$core) {
                    access::fail('ratingfailed');
                }
            }
            $status = $approve ? 'approved' : 'rejected';
            $DB->update_record(store::ASSESS, (object)[
                'id' => $id, 'status' => $status, 'reviewerid' => $USER->id, 'reviewed' => time(), 'feedback' => $feedback,
            ]);
            self::event($id, $context, (int)$record->userid, $status);
        });
    }

    /**
     * Trigger the Moodle audit event for the requested change.
     *
     * @param int $id Record ID
     * @param \context_course $context Authorized Moodle context
     * @param int $userid Learner ID
     * @param string $status Assessment state
     */
    private static function event(int $id, \context_course $context, int $userid, string $status): void {
        \local_skillupscompetency\event\assessment_changed::create([
            'objectid' => $id, 'context' => $context, 'relateduserid' => $userid, 'other' => ['status' => $status],
        ])->trigger();
    }
}
