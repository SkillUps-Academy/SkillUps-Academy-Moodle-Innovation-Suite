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
 * Deterministic and inspectable course-level thresholds.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules {
    /** Disabled features use a threshold of zero. */
    public const DEFAULTS = ['inactive' => 10, 'overdue' => 2, 'grade' => 60, 'decline' => 12, 'participation' => 1, 'high' => 2];

    /**
     * Normalize teacher configuration to a known safe range.
     */
    public static function config(array $input): array {
        $maxima = ['inactive' => 90, 'overdue' => 50, 'grade' => 100, 'decline' => 100, 'participation' => 30, 'high' => 5];
        $result = [];
        foreach (self::DEFAULTS as $key => $default) {
            $result[$key] = max($key === 'high' ? 1 : 0, min($maxima[$key], (int)($input[$key] ?? $default)));
        }
        return $result;
    }

    /**
     * Compare only available measurements to enabled thresholds.
     */
    public static function evaluate(array $features, array $thresholds): array {
        $triggers = [];
        $observed = 0;
        foreach ($thresholds as $key => $limit) {
            if ($key === 'high' || !$limit || !isset($features[$key])) {
                continue;
            }
            $actual = $features[$key];
            $observed++;
            $matched = in_array($key, ['grade', 'participation'], true) ? $actual < $limit : $actual >= $limit;
            if ($matched) {
                $triggers[$key] = ['value' => $actual, 'limit' => $limit];
            }
        }
        $count = count($triggers);
        $risk = $observed === 0 ? 'unknown' : ($count >= $thresholds['high'] ? 'high' : ($count ? 'watch' : 'normal'));
        return ['risk' => $risk, 'triggers' => $triggers, 'observed' => $observed,
            'version' => hash('sha256', 'rules-v1:' . util::json($thresholds))];
    }
}
