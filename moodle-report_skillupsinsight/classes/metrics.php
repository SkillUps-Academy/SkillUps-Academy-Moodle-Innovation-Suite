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

namespace report_skillupsinsight;

/**
 * Raw additive measures and presentation formulas.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class metrics {
    /** Supported dashboard indicators. */
    public const IDS = ['enrolled', 'newlearners', 'active', 'participation', 'educators', 'teaching',
        'academic', 'completion'];

    /**
     * Count Moodle's currently active cohort, then reconcile relevant records within the academic window.
     */
    public static function capture(\stdClass $course, int $begin, int $end): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $context = \context_course::instance($course->id);
        [$learnsql, $learnparams] = get_enrolled_sql($context, util::CAP . ':learner', 0, true);
        [$teachsql, $teachparams] = get_enrolled_sql($context, util::CAP . ':educator', 0, true);
        $learners = self::active_population_sql($learnsql);
        $educators = self::active_population_sql($teachsql);
        $row = self::zero();
        $row['enrolled'] = $DB->count_records_sql("SELECT COUNT(1) FROM ($learners) cohort", $learnparams);
        $row['educators'] = $DB->count_records_sql("SELECT COUNT(1) FROM ($educators) cohort", $teachparams);
        $window = ['fromtime' => $begin, 'totime' => $end];
        $params = $learnparams + $window + ['courseid' => $course->id];
        $row['newlearners'] = $DB->count_records_sql("SELECT COUNT(1)
            FROM ($learners) cohort JOIN (
                SELECT ue.userid, MIN(ue.timecreated) firsttime FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :courseid GROUP BY ue.userid
            ) entries ON entries.userid = cohort.id
            WHERE entries.firsttime >= :fromtime AND entries.firsttime < :totime", $params);

        // The SQL reader exposes the standard log table and its course/time index.
        $readers = get_log_manager()->get_readers(\core\log\sql_internal_table_reader::class);
        $standard = $readers['logstore_standard'] ?? null;
        if ($standard && $standard->get_internal_log_table_name() === 'logstore_standard_log') {
            $logtable = '{logstore_standard_log}';
            $condition = 'l.courseid = :courseid AND l.anonymous = 0 AND l.timecreated >= :fromtime AND l.timecreated < :totime
                AND (l.realuserid IS NULL OR l.realuserid = 0) AND l.origin IN (:web, :ws) AND l.component <> :selfcomponent';
            $logscope = ['web' => 'web', 'ws' => 'ws', 'selfcomponent' => util::C];
            $logparams = $params + $logscope;
            $row['active'] = $DB->count_records_sql("SELECT COUNT(DISTINCT l.userid) FROM $logtable l
                JOIN ($learners) cohort ON cohort.id = l.userid WHERE $condition", $logparams);
            [$insql, $inparams] = $DB->get_in_or_equal([
                '\mod_assign\event\assessable_submitted', '\mod_assign\event\submission_created',
                '\mod_quiz\event\attempt_submitted', '\mod_forum\event\post_created',
                '\mod_forum\event\discussion_created', '\mod_feedback\event\response_submitted',
            ], SQL_PARAMS_NAMED, 'participationevent');
            $row['participating'] = $DB->count_records_sql("SELECT COUNT(DISTINCT l.userid) FROM $logtable l
                JOIN ($learners) cohort ON cohort.id = l.userid WHERE $condition AND l.eventname $insql", $logparams + $inparams);
            [$insql, $inparams] = $DB->get_in_or_equal([
                '\core\event\user_graded', '\core\event\course_module_created', '\core\event\course_module_updated',
                '\mod_assign\event\submission_graded', '\mod_forum\event\post_created', '\mod_forum\event\discussion_created',
            ], SQL_PARAMS_NAMED, 'teacherevent');
            $row['teaching'] = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT l.userid) FROM $logtable l
                JOIN ($educators) cohort ON cohort.id = l.userid WHERE $condition AND l.eventname $insql",
                $teachparams + $window + ['courseid' => $course->id] + $logscope + $inparams
            );
            $row['logcourses'] = 1;
            $row['loglearners'] = $row['enrolled'];
            $row['logeducators'] = $row['educators'];
        }
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
        if ($item && !$item->needsupdate && (int)$item->gradetype === GRADE_TYPE_VALUE && $item->grademax > $item->grademin) {
            $grades = $DB->get_record_sql("SELECT COUNT(g.id) observations, SUM(g.finalgrade) gradesum
                FROM {grade_grades} g JOIN ($learners) cohort ON cohort.id = g.userid
                WHERE g.itemid = :gradeitem AND g.finalgrade IS NOT NULL AND g.timemodified >= :fromtime
                  AND g.timemodified < :totime", $learnparams + $window + ['gradeitem' => $item->id]);
            $row['graded'] = (int)$grades->observations;
            $row['gradesum'] = 100 * ((float)$grades->gradesum - $row['graded'] * $item->grademin) /
                ($item->grademax - $item->grademin);
        }
        $info = new \completion_info($course);
        if ($info->is_enabled() && $info->get_criteria()) {
            $row['completionbase'] = $row['enrolled'];
            $row['completed'] = $DB->count_records_sql("SELECT COUNT(DISTINCT cc.userid)
                FROM {course_completions} cc JOIN ($learners) cohort ON cohort.id = cc.userid
                WHERE cc.course = :courseid AND cc.timecompleted > 0 AND cc.timecompleted >= :fromtime
                  AND cc.timecompleted < :totime", $params);
        }
        return $row;
    }

    /**
     * Exclude deleted, suspended, unconfirmed and guest accounts.
     */
    private static function active_population_sql(string $enrolledsql): string {
        // The enrolment API already excludes guest sessions from the actively enrolled cohort.
        return "SELECT DISTINCT u.id FROM ($enrolledsql) enrolled JOIN {user} u ON u.id = enrolled.id
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1";
    }

    /**
     * Basis values used by weighted rollups.
     */
    public static function zero(): array {
        return array_fill_keys(['enrolled', 'newlearners', 'active', 'participating', 'educators', 'teaching', 'graded', 'gradesum',
            'completionbase', 'completed', 'logcourses', 'loglearners', 'logeducators'], 0);
    }

    /**
     * Aggregate authorized additive numerators and denominators.
     */
    public static function sum(array $totals, array $next): array {
        foreach ($totals as $key => $value) {
            $totals[$key] = $value + ($next[$key] ?? 0);
        }
        return $totals;
    }

    /**
     * Compute only from recorded denominators. A null value is unavailable or suppressed.
     */
    public static function values(array $row): array {
        $small = get_config(util::C, 'smallcell');
        $minimum = $small === false ? 5 : max(1, min(100, (int)$small));
        $ratio = static fn($n, $d) => $d >= $minimum ? (100.0 * $n) / $d : null;
        return [
            'enrolled' => $row['enrolled'],
            'newlearners' => $row['newlearners'],
            'active' => $row['logcourses'] && $row['loglearners'] >= $minimum ? $row['active'] : null,
            'participation' => $ratio($row['participating'], $row['loglearners']),
            'educators' => $row['educators'],
            'teaching' => $row['logcourses'] && $row['logeducators'] >= $minimum ? $row['teaching'] : null,
            'academic' => $ratio($row['gradesum'] / 100.0, $row['graded']),
            'completion' => $ratio($row['completed'], $row['completionbase']),
        ];
    }

    /**
     * Indicators presented in percentages.
     */
    public static function percent(string $key): bool {
        return in_array($key, ['participation', 'academic', 'completion'], true);
    }
}
