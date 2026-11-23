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

use core_competency\course_competency;
use core_competency\user_competency_course;
use grade_category;
use grade_grade;
use grade_item;
use stdClass;

/**
 * Read-only access checks and authoritative Moodle evidence evaluation.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evidence {
    /** @var array Learner-scoped course records for the current evaluation only. */
    private array $courses = [];

    /** @var int Moodle user whose evidence is being evaluated. */
    private readonly int $userid;

    /**
     * Initialize a single learner's evaluation.
     *
     * @param int $userid Subject
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Validate references and author capabilities before accepting an itinerary graph.
     *
     * @param array $graph Validated graph
     */
    public static function author(array $graph): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $courses = [];
        foreach ($graph['nodes'] as $node) {
            $id = $node['course'];
            if (!isset($courses[$id])) {
                $course = $DB->get_record('course', ['id' => $id]);
                if (!$course || $course->id == SITEID) {
                    access::deny();
                }
                $context = \context_course::instance($id);
                if (!has_capability('moodle/course:update', $context)) {
                    access::deny();
                }
                $courses[$id] = $course;
            }
            $course = $courses[$id];
            $cm = $node['cm'] ? (get_fast_modinfo($course)->get_cms()[$node['cm']] ?? null) : null;
            if ($node['cm'] && (!$cm || $cm->deletioninprogress)) {
                access::deny();
            }
            if ($node['completion'] && !(new \completion_info($course))->is_enabled($cm)) {
                throw new \moodle_exception('completiondisabled', access::C);
            }
            if (
                $node['competency'] && (
                !\core_competency\api::is_enabled() ||
                !course_competency::record_exists_select('courseid = :course AND competencyid = :competency', [
                    'course' => $id, 'competency' => $node['competency'],
                ])
                )
            ) {
                throw new \moodle_exception('invalidcompetency', access::C);
            }
        }
    }

    /**
     * Resolve an accessible native destination and its URL.
     *
     * @param array $node Graph node
     * @return array|null Course, course module and Moodle link
     */
    public function destination(array $node): ?array {
        global $DB;
        $id = $node['course'];
        if (!array_key_exists($id, $this->courses)) {
            $course = $DB->get_record('course', ['id' => $id]);
            $this->courses[$id] = $course && access::enrolled($course, $this->userid) ? $course : null;
        }
        $course = $this->courses[$id];
        if (!$course) {
            return null;
        }
        if (!$node['cm']) {
            return [$course, null, new \moodle_url('/course/view.php', ['id' => $id])];
        }
        $cm = get_fast_modinfo($course, $this->userid)->get_cms()[$node['cm']] ?? null;
        return $cm && !$cm->deletioninprogress && $cm->uservisible && $cm->url ? [$course, $cm, $cm->url] : null;
    }

    /**
     * Evaluate permitted completion, grade and competency conditions.
     *
     * @param array $node Node conditions
     * @param array $destination Authorized native destination
     * @return bool
     */
    public function completed(array $node, array $destination): bool {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        [$course, $cm] = $destination;
        if ($node['completion']) {
            $info = new \completion_info($course);
            if (!$info->is_enabled($cm)) {
                return false;
            }
            $status = $cm
                ? in_array(
                    (int)$info->get_data($cm, false, $this->userid)->completionstate,
                    [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
                    true
                )
                : $info->is_course_complete($this->userid);
            if (!$status) {
                return false;
            }
        }
        if ($node['grade'] >= 0 && !$this->grade($course, $cm, $node['grade'])) {
            return false;
        }
        if (!$node['competency']) {
            return true;
        }
        if (
            !\core_competency\api::is_enabled() ||
            !has_capability('moodle/competency:coursecompetencyview', \context_course::instance($course->id), $this->userid) ||
            !course_competency::record_exists_select('courseid = :course AND competencyid = :competency', [
                'course' => $course->id, 'competency' => $node['competency'],
            ])
        ) {
            return false;
        }
        $result = user_competency_course::get_record([
            'courseid' => $course->id, 'userid' => $this->userid, 'competencyid' => $node['competency'],
        ]);
        return $result && $result->get('proficiency');
    }

    /**
     * Compare the learner's visible final numeric grade without triggering regrading.
     *
     * @param stdClass $course Course scope
     * @param \cm_info|null $cm Activity or course total
     * @param int $threshold Percentage threshold
     * @return bool
     */
    private function grade(stdClass $course, ?\cm_info $cm, int $threshold): bool {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        if (
            !$course->showgrades ||
            !has_capability('moodle/grade:view', \context_course::instance($course->id), $this->userid)
        ) {
            return false;
        }
        $criteria = ['courseid' => $course->id, 'itemtype' => $cm ? 'mod' : 'course'];
        if ($cm) {
            $criteria += ['itemmodule' => $cm->modname, 'iteminstance' => $cm->instance, 'itemnumber' => 0];
        }
        $items = grade_item::fetch_all($criteria) ?: [];
        $item = count($items) === 1 ? reset($items) : null;
        if (
            !$item || $item->needsupdate || (int)$item->gradetype !== GRADE_TYPE_VALUE ||
            $item->grademax <= $item->grademin || !$this->visible_grade_item($item)
        ) {
            return false;
        }
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $this->userid]);
        if (!$grade || $grade->finalgrade === null || $grade->is_hidden()) {
            return false;
        }
        // A course total can reveal a threshold derived from hidden items.
        if (
            !$cm && $DB->record_exists_sql(
                "SELECT 1 FROM {grade_items} gi
               LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
              WHERE gi.courseid = :courseid
                AND (gi.hidden = 1 OR gi.hidden > :itemnow OR gg.hidden = 1 OR gg.hidden > :gradenow)",
                ['userid' => $this->userid, 'courseid' => $course->id, 'itemnow' => time(), 'gradenow' => time()]
            )
        ) {
            return false;
        }
        $percent = 100 * ($grade->finalgrade - $item->grademin) / ($item->grademax - $item->grademin);
        return $percent + 0.00001 >= $threshold;
    }

    /**
     * Reject a hidden grade or grade ancestor, and fail closed on damaged hierarchies.
     *
     * @param grade_item $item Grade item
     * @return bool
     */
    private function visible_grade_item(grade_item $item): bool {
        if ($item->is_hidden()) {
            return false;
        }
        $id = (int)$item->categoryid;
        $seen = [];
        while ($id) {
            if (isset($seen[$id]) || count($seen) >= 50) {
                return false;
            }
            $seen[$id] = true;
            $category = grade_category::fetch(['id' => $id, 'courseid' => $item->courseid]);
            $parentgrade = $category ? grade_item::fetch([
                'courseid' => $item->courseid, 'itemtype' => $category->parent ? 'category' : 'course', 'iteminstance' => $id,
            ]) : null;
            if (!$parentgrade || $parentgrade->is_hidden()) {
                return false;
            }
            $id = (int)$category->parent;
        }
        return true;
    }
}
