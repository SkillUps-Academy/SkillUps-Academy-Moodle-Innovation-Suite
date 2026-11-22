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
 * Rule metadata and callable evaluation for built-in conditions.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class builtin_rule implements rule {
    /**
     * Register an immutable rule.
     *
     * @param string $code Stable code
     * @param string $kind Finding classification
     * @param string $severity Default impact
     * @param \Closure $check Read-only evaluator
     */
    public function __construct(
        /** @var string Stable rule code. */
        private readonly string $code,
        /** @var string Finding classification. */
        private readonly string $kind,
        /** @var string Default impact. */
        private readonly string $severity,
        /** @var \Closure Rule condition evaluator. */
        private readonly \Closure $check,
    ) {
    }

    /**
     * Return the rule's bilingual catalog references.
     */
    public function definition(): array {
        return [
            'code' => $this->code,
            'kind' => $this->kind,
            'severity' => $this->severity,
            'description' => 'rule_' . $this->code,
            'condition' => 'condition_' . $this->code,
            'recommendation' => 'recommend_' . $this->code,
        ];
    }

    /**
     * Run the read-only condition.
     */
    public function evaluate(inspection $course): iterable {
        return ($this->check)($course);
    }
}
