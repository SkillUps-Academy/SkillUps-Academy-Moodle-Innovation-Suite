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
 * Calculate and describe immutable audit results.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /**
     * Collect all outcomes in a reproducible, versioned policy.
     */
    public static function audit(\stdClass $course, array $policy): array {
        $view = new inspection($course);
        if (count($view->modules) > 2000) {
            throw new \moodle_exception('courselimit', scope::COMPONENT);
        }
        $findings = [];
        $score = 100;
        $hasobjective = false;
        $counts = ['objective' => 0, 'advisory' => 0];
        $rules = catalog::rules();
        if ($policy['revision'] !== catalog::REVISION) {
            throw new \moodle_exception('policychanged', scope::COMPONENT);
        }
        foreach ($policy['rules'] as $code => $severity) {
            $rule = $rules[$code];
            $kind = $rule->definition()['kind'];
            $hasobjective = $hasobjective || $kind === 'objective';
            $present = false;
            foreach ($rule->evaluate($view) as $evidence) {
                $fingerprint = inspection::fingerprint($code, $evidence['type'], $evidence['id']);
                if (isset($findings[$fingerprint])) {
                    continue;
                }
                $findings[$fingerprint] = [
                    'rulecode' => $code, 'kind' => $kind, 'severity' => $severity, 'targettype' => $evidence['type'],
                    'targetid' => $evidence['id'], 'fingerprint' => $fingerprint,
                    'evidence' => json_encode($evidence['values'], JSON_THROW_ON_ERROR),
                ];
                $counts[$kind]++;
                $present = true;
            }
            if ($present && $kind === 'objective') {
                $score -= catalog::WEIGHTS[$severity];
            }
        }
        return [
            'score' => $hasobjective ? max(0, $score) : -1,
            'findings' => $findings,
            'unverified' => array_keys($view->unverified),
        ] + $counts;
    }
}
