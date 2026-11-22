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

namespace report_skillupsquality;

/**
 * Institutional catalog, score weights and rule policies.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /** Rule semantics for historical comparison. */
    public const REVISION = 1;
    /** Severity weights applied once per distinct objective rule. */
    public const WEIGHTS = ['critical' => 25, 'high' => 15, 'medium' => 8, 'low' => 3];

    /**
     * Register native rules with stable IDs.
     *
     * New adapters implement the rule contract and register here.
     */
    public static function rules(): array {
        return [
            'emptycourse' => new builtin_rule('emptycourse', 'objective', 'high', checks::emptycourse(...)),
            'emptycontent' => new builtin_rule('emptycontent', 'objective', 'medium', checks::emptycontent(...)),
            'dates' => new builtin_rule('dates', 'objective', 'high', checks::dates(...)),
            'grade' => new builtin_rule('grade', 'objective', 'high', checks::grade(...)),
            'quizgrade' => new builtin_rule('quizgrade', 'objective', 'high', checks::quizgrade(...)),
            'rubric' => new builtin_rule('rubric', 'objective', 'high', checks::rubric(...)),
            'resources' => new builtin_rule('resources', 'objective', 'medium', checks::resources(...)),
            'links' => new builtin_rule('links', 'objective', 'medium', checks::links(...)),
            'instructions' => new builtin_rule('instructions', 'advisory', 'low', checks::instructions(...)),
            'hidden' => new builtin_rule('hidden', 'advisory', 'low', checks::hidden(...)),
            'sections' => new builtin_rule('sections', 'advisory', 'low', checks::sections(...)),
            'completion' => new builtin_rule('completion', 'advisory', 'medium', checks::completion(...)),
        ];
    }

    /**
     * Read enabled rules and severity from site configuration.
     */
    public static function policy(): array {
        $policy = ['revision' => self::REVISION, 'rules' => []];
        foreach (self::rules() as $code => $rule) {
            $enabled = get_config(scope::COMPONENT, 'enable_' . $code);
            if ($enabled !== false && !$enabled) {
                continue;
            }
            $severity = (string)get_config(scope::COMPONENT, 'severity_' . $code);
            $policy['rules'][$code] = isset(self::WEIGHTS[$severity]) ? $severity : $rule->definition()['severity'];
        }
        return $policy;
    }
}
