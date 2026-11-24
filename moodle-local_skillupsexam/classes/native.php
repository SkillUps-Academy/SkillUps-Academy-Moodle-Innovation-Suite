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

namespace local_skillupsexam;

use mod_quiz\quiz_settings;
use stdClass;

/**
 * Read native assessment dates, attempt metadata and final Gradebook rows.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native {
    /**
     * Load Moodle Assign without creating any data.
     *
     * @param stdClass $course Moodle course
     * @param \cm_info $cm Assign activity
     * @return \assign
     */
    private static function assignment(stdClass $course, \cm_info $cm): \assign {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        return new \assign($cm->context, $cm, $course);
    }

    /**
     * Read activity defaults and configuration warnings.
     *
     * @param stdClass $exam Convocation
     * @param stdClass $course Moodle course
     * @return array
     */
    public static function window(stdClass $exam, stdClass $course): array {
        $cm = scope::module($course, (int)$exam->cmid);
        if (!$cm) {
            return ['cm' => null, 'open' => 0, 'close' => 0, 'due' => 0, 'limit' => 0, 'warnings' => ['missingactivity']];
        }
        $warnings = [];
        $due = 0;
        if ($cm->modname === 'quiz') {
            $quiz = quiz_settings::create_for_cmid((int)$cm->id);
            $data = $quiz->get_quiz();
            $open = (int)$data->timeopen;
            $close = (int)$data->timeclose;
            if (!$quiz->has_questions()) {
                $warnings[] = 'noquestions';
            }
        } else {
            $data = self::assignment($course, $cm)->get_instance();
            $open = (int)$data->allowsubmissionsfromdate;
            $close = (int)$data->cutoffdate;
            $due = (int)$data->duedate;
            if ($due && ($close && $close < $due || $open && $due < $open)) {
                $warnings[] = 'inconsistent';
            }
        }
        $limit = (int)($data->timelimit ?? 0);
        if (
            ($open && $close && $open >= $close) || ($open && $open > $exam->starts) ||
            ($close && $close < $exam->ends) || ($due && $due < $exam->ends)
        ) {
            $warnings[] = 'inconsistent';
        }
        if (!$open || (!$close && !$due)) {
            $warnings[] = 'nodates';
        }
        if ($limit && $limit !== (int)$exam->ends - (int)$exam->starts) {
            $warnings[] = 'durationmismatch';
        }
        return ['cm' => $cm, 'open' => $open, 'close' => $close, 'due' => $due, 'limit' => $limit,
            'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * Check native result rights, including blind marking.
     *
     * @param stdClass $exam Convocation
     * @param stdClass $course Moodle course
     * @return bool
     */
    public static function permitted(stdClass $exam, stdClass $course): bool {
        $cm = scope::module($course, (int)$exam->cmid);
        if (!$cm || !scope::exam($exam, $course, 'results')) {
            return false;
        }
        if ($cm->modname === 'quiz') {
            return has_capability('mod/quiz:viewreports', $cm->context);
        }
        return has_capability('mod/assign:grade', $cm->context) && !self::assignment($course, $cm)->is_blind_marking();
    }

    /**
     * Read authorized participation and grade metadata without triggering a regrade.
     *
     * @param stdClass $exam Convocation
     * @param stdClass $course Moodle course
     * @param int[] $ids Requested registered learners
     * @return array
     */
    public static function results(stdClass $exam, stdClass $course, array $ids): array {
        global $CFG;
        if (!self::permitted($exam, $course)) {
            scope::fail();
        }
        require_once($CFG->libdir . '/gradelib.php');
        $cm = scope::module($course, (int)$exam->cmid);
        $context = \context_course::instance($course->id);
        $ids = array_values(array_filter(array_unique($ids), static fn($id) => roster::eligible($exam, (int)$id, true)));
        if (!$ids) {
            return [];
        }
        $item = has_capability('moodle/grade:viewall', $context) ? \grade_item::fetch([
            'courseid' => $course->id, 'itemtype' => 'mod', 'iteminstance' => $cm->instance, 'itemmodule' => $cm->modname,
            'itemnumber' => 0,
        ]) : false;
        $gradevalues = $item && !$item->needsupdate ? \grade_grade::fetch_users_grades($item, $ids, false) : [];
        $assignment = null;
        if ($cm->modname === 'quiz') {
            require_once($CFG->dirroot . '/mod/quiz/lib.php');
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        } else {
            $assignment = self::assignment($course, $cm);
        }
        $output = [];
        foreach ($ids as $userid) {
            $number = 0;
            $state = '';
            if ($assignment) {
                if (!$assignment->can_view_submission($userid)) {
                    continue;
                }
                $data = $assignment->get_instance();
                if ($data->teamsubmission) {
                    $group = $assignment->get_submission_group($userid);
                    $submission = $assignment->get_group_submission($userid, $group ? $group->id : 0, false);
                } else {
                    $submission = $assignment->get_user_submission($userid, false);
                }
                if ($submission) {
                    $number = $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED ? 1 : 0;
                    $state = scope::text('native_' . $submission->status);
                }
            } else {
                $attempts = quiz_get_user_attempts((int)$cm->instance, $userid, 'all', false);
                $number = count($attempts);
                $latest = end($attempts);
                if ($latest) {
                    $state = quiz_attempt_state_name($latest->state);
                }
            }
            $grade = $gradevalues[$userid] ?? null;
            $value = null;
            if ($grade && $grade->finalgrade !== null) {
                $grade->grade_item = $item;
                if (!$grade->is_hidden() || has_capability('moodle/grade:viewhidden', $context)) {
                    $value = grade_format_gradevalue($grade->finalgrade, $item);
                }
            }
            $output[$userid] = ['count' => $number, 'state' => $state, 'grade' => $value];
        }
        return $output;
    }
}
