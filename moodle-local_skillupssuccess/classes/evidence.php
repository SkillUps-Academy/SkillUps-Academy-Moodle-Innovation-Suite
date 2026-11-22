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

namespace local_skillupssuccess;

/**
 * Collect only academic source signals; never retain Moodle event payloads or submissions.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evidence {
    /**
     * Build one course/learner observation at the captured time.
     */
    public static function collect(\stdClass $course, int $userid, int $now): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $since = self::eligible_since($course, $userid, $now);
        $last = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $userid, 'courseid' => $course->id]);
        $days = $since > 0 && $now - $since >= 3 * DAYSECS ?
            max(0, intdiv($now - max((int)$last, $since), DAYSECS)) : null;
        $modinfo = get_fast_modinfo($course, $userid);
        $modids = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->visible && $cm->uservisible && !$cm->deletioninprogress) {
                $modids[] = $cm->id;
            }
        }
        $pending = self::pending_assignments($course, $userid, $modinfo, $now, $since);
        $grade = self::grade($course, $userid);
        $reference = $DB->get_records_select(
            store::OBS,
            'courseid = :course AND userid = :user
            AND captured <= :recent AND captured >= :oldest AND gradepct IS NOT NULL',
            ['course' => $course->id, 'user' => $userid, 'recent' => $now - 7 * DAYSECS, 'oldest' => $now - 30 * DAYSECS],
            'captured DESC, id DESC',
            'id, gradepct',
            0,
            1
        );
        $reference = reset($reference) ?: null;
        $decline = $grade !== null && $reference ? (float)$reference->gradepct - $grade : null;
        return [
            'inactive' => $days, 'overdue' => $pending === null ? null : count($pending), 'grade' => $grade, 'decline' => $decline,
            'participation' => $since > 0 && $now - $since >= 14 * DAYSECS ?
                self::activity((int)$course->id, $userid, $modids, $now) : null,
        ];
    }

    /**
     * Find the start of the currently valid enrollment, bounded by the course start.
     */
    private static function eligible_since(\stdClass $course, int $userid, int $now): int {
        global $DB;
        $record = $DB->get_record_sql(
            'SELECT MIN(CASE WHEN ue.timestart > ue.timecreated THEN ue.timestart ELSE ue.timecreated END)
            AS starttime FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = :course AND ue.userid = :user AND e.status = 0 AND ue.status = 0
              AND (ue.timeend = 0 OR ue.timeend > :ending) AND (ue.timestart = 0 OR ue.timestart <= :starting)',
            ['course' => $course->id, 'user' => $userid, 'ending' => $now, 'starting' => $now]
        );
        return max((int)$record->starttime, (int)$course->startdate);
    }

    /**
     * Show current missing work only for a Case page whose viewer is authorized.
     */
    public static function pending(\stdClass $course, int $caseid): array {
        global $CFG;
        $case = store::case_for($course, $caseid);
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $now = time();
        $modinfo = get_fast_modinfo($course, $case->userid);
        $since = self::eligible_since($course, $case->userid, $now);
        return self::pending_assignments($course, $case->userid, $modinfo, $now, $since) ?? [];
    }

    /**
     * Identify visible individual assignments with an elapsed effective deadline and no submitted attempt.
     */
    private static function pending_assignments(
        \stdClass $course,
        int $userid,
        \course_modinfo $modinfo,
        int $now,
        int $since
    ): ?array {
        $items = [];
        $observed = 0;
        foreach ($modinfo->get_instances_of('assign') as $cm) {
            if (
                !$cm->visible || !$cm->uservisible || $cm->deletioninprogress ||
                    !has_capability('mod/assign:submit', $cm->context, $userid)
            ) {
                continue;
            }
            $assign = new \assign($cm->context, $cm, $course);
            $instance = $assign->get_instance($userid);
            if ($instance->teamsubmission || ($instance->blindmarking && !$instance->revealidentities)) {
                continue;
            }
            $override = $assign->override_exists($userid);
            $due = (int)($override->duedate ?? $instance->duedate);
            $extension = (int)($assign->get_user_flags($userid, false)->extensionduedate ?? 0);
            $due = $due ? max($due, $extension) : $extension;
            $opens = (int)($override->allowsubmissionsfromdate ?? $instance->allowsubmissionsfromdate);
            if (!$due || $due < $since || $opens > $now) {
                continue;
            }
            $observed++;
            if ($due >= $now) {
                continue;
            }
            $submission = $assign->get_user_submission($userid, false);
            if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                $items[$cm->id] = $due;
            }
        }
        return $observed ? $items : null;
    }

    /**
     * Read an existing published numeric grade item and final course grade without regrading.
     */
    private static function grade(\stdClass $course, int $userid): ?float {
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
        if (
            !$item || $item->needsupdate || $item->hidden || (int)$item->gradetype !== GRADE_TYPE_VALUE ||
                $item->grademax <= $item->grademin
        ) {
            return null;
        }
        $final = $item->get_final($userid);
        if (!$final || $final->hidden || $final->finalgrade === null) {
            return null;
        }
        return max(0.0, min(100.0, 100.0 * ($final->finalgrade - $item->grademin) / ($item->grademax - $item->grademin)));
    }

    /**
     * Measure breadth of valid activity events in currently available modules.
     */
    private static function activity(int $course, int $userid, array $moduleids, int $time): ?int {
        global $DB;
        $readers = get_log_manager()->get_readers(\core\log\sql_internal_table_reader::class);
        $standard = $readers['logstore_standard'] ?? null;
        $lifetime = (int)get_config('logstore_standard', 'loglifetime');
        if (
            !$standard || $standard->get_internal_log_table_name() !== 'logstore_standard_log' ||
                ($lifetime > 0 && $lifetime < 14) || !$moduleids
        ) {
            return null;
        }
        $count = 0;
        foreach (array_chunk($moduleids, 250) as $ids) {
            [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'module');
            $count += $DB->count_records_sql(
                "SELECT COUNT(DISTINCT contextinstanceid) FROM {logstore_standard_log}
                WHERE courseid = :course AND userid = :user AND anonymous = 0 AND contextlevel = :level
                  AND contextinstanceid $sql AND timecreated >= :since AND timecreated <= :asof
                  AND (realuserid IS NULL OR realuserid = 0) AND origin IN (:web, :ws)",
                $params + ['course' => $course, 'user' => $userid, 'level' => CONTEXT_MODULE, 'since' => $time - 14 * DAYSECS,
                'asof' => $time,
                'web' => 'web',
                'ws' => 'ws']
            );
        }
        return $count;
    }
}
