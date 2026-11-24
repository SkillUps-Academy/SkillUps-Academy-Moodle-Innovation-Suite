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

/**
 * Read the active Moodle assignment rubric without changing activity grades.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rubric {
    /**
     * Read the current assignment rubric from advanced grading.
     *
     * @param \stdClass $course Moodle course record
     * @param int $cmid Optional course activity ID
     * @return array
     */
    public static function definition(\stdClass $course, int $cmid): array {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $cm = get_fast_modinfo($course)->get_cms()[$cmid] ?? null;
        if (!$cm || !$cm->uservisible || $cm->modname !== 'assign') {
            return [];
        }
        $context = \context_module::instance($cmid);
        if (!has_capability('mod/assign:grade', $context)) {
            return [];
        }
        $manager = get_grading_manager($context, 'mod_assign', 'submissions');
        if ($manager->get_active_method() !== 'rubric') {
            return [];
        }
        $controller = $manager->get_active_controller();
        if (!$controller || !$controller->is_form_available()) {
            return [];
        }
        $definition = $controller->get_definition();
        if (!$definition || empty($definition->rubric_criteria)) {
            return [];
        }
        return ['id' => (int)$definition->id, 'criteria' => $definition->rubric_criteria];
    }

    /**
     * Sign the rubric contents for a form revision check.
     *
     * @param array $definition Current Moodle rubric definition
     * @return string
     */
    public static function signature(array $definition): string {
        return hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR));
    }

    /**
     * Normalize selected rubric points and record the selections.
     *
     * @param array $definition Current Moodle rubric definition
     * @param array $selected Selected level IDs indexed by criterion
     * @param int $scalelevels Number of competency scale levels
     * @return array
     */
    public static function evaluate(array $definition, array $selected, int $scalelevels): array {
        if (!$definition || count($selected) !== count($definition['criteria']) || $scalelevels < 1) {
            access::fail('invalidrubric');
        }
        $minimum = 0;
        $maximum = 0;
        $total = 0;
        $snapshot = [];
        foreach ($definition['criteria'] as $id => $criterion) {
            $levelid = $selected[$id] ?? 0;
            $level = $criterion['levels'][$levelid] ?? null;
            if (!$level) {
                access::fail('invalidrubric');
            }
            $scores = array_column($criterion['levels'], 'score');
            $minimum += min($scores);
            $maximum += max($scores);
            $total += (float)$level['score'];
            $snapshot[] = [
                'criterion' => strip_tags($criterion['description']),
                'level' => strip_tags($level['definition']),
                'score' => (float)$level['score'],
            ];
        }
        if ($maximum <= $minimum) {
            access::fail('invalidrubric');
        }
        $fraction = ($total - $minimum) / ($maximum - $minimum);
        return [1 + (int)round($fraction * ($scalelevels - 1)), [
            'definition' => $definition['id'], 'fraction' => $fraction, 'criteria' => $snapshot,
        ]];
    }
}
