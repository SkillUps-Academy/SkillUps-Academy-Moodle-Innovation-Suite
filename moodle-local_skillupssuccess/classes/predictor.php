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
 * Optional approved fixed logistic model. This plugin performs inference only.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class predictor {
    /** The only accepted features are academic measurements. */
    public const FEATURES = ['inactive', 'overdue', 'grade', 'decline', 'participation'];

    /**
     * Require institutional enablement and a deployment-owned specification.
     */
    public static function configured(): ?array {
        global $CFG;
        if (!get_config(util::C, 'modelenabled')) {
            return null;
        }
        return self::validate($CFG->local_skillupssuccess_model ?? []);
    }

    /**
     * Verify the frozen format, target, documented version and numeric bounds.
     */
    public static function validate(mixed $model): ?array {
        if (
            !is_array($model) || ($model['target'] ?? null) !== 'independent_support_need_14d' ||
                ($model['approved'] ?? null) !== true || !is_string($model['version'] ?? null) ||
                !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,99}\z/D', $model['version']) ||
                !is_numeric($model['intercept'] ?? null) || !is_numeric($model['threshold'] ?? null) ||
                !is_array($model['weights'] ?? null) || array_diff(array_keys($model['weights']), self::FEATURES)
        ) {
            return null;
        }
        $threshold = (float)$model['threshold'];
        $intercept = (float)$model['intercept'];
        if (!is_finite($threshold) || !is_finite($intercept) || $threshold <= 0 || $threshold >= 1 || abs($intercept) > 30) {
            return null;
        }
        $weights = [];
        foreach (self::FEATURES as $feature) {
            $weight = $model['weights'][$feature] ?? 0;
            if (!is_numeric($weight) || !is_finite((float)$weight) || abs((float)$weight) > 30) {
                return null;
            }
            $weights[$feature] = (float)$weight;
        }
        if (!array_filter($weights)) {
            return null;
        }
        $approved = ['version' => $model['version'], 'target' => $model['target'], 'threshold' => $threshold,
            'intercept' => $intercept, 'weights' => $weights];
        $approved['fingerprint'] = hash('sha256', util::json($approved));
        return $approved;
    }

    /**
     * Score with no missing-feature imputation and return local log-odds contributions.
     */
    public static function score(array $features, ?array $model): ?array {
        if (!$model) {
            return null;
        }
        $ranges = ['inactive' => [0, 30], 'overdue' => [0, 5], 'grade' => [0, 100],
            'decline' => [-100, 100], 'participation' => [0, 10]];
        $contributions = [];
        $logit = $model['intercept'];
        foreach ($model['weights'] as $feature => $weight) {
            if ($weight === 0.0) {
                continue;
            }
            if (!isset($features[$feature])) {
                return null;
            }
            [$lower, $upper] = $ranges[$feature];
            $scaled = (max($lower, min($upper, $features[$feature])) - $lower) / ($upper - $lower);
            $contributions[$feature] = $scaled * $weight;
            $logit += $contributions[$feature];
        }
        $probability = 1.0 / (1.0 + exp(-max(-35.0, min(35.0, $logit))));
        return ['probability' => $probability, 'flag' => (int)($probability >= $model['threshold']),
            'version' => $model['version'], 'fingerprint' => $model['fingerprint'], 'threshold' => $model['threshold'],
            'contributions' => $contributions];
    }
}
