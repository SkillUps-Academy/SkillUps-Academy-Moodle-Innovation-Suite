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
 * Outcome evaluation on explicitly collected, frozen prospective samples.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quality {
    /**
     * Minimum cell support for aggregate output.
     */
    public static function minimum(): int {
        return max(5, min(100, (int)(get_config(util::C, 'mincell') ?: 10)));
    }

    /**
     * Return zeroed sufficient statistics.
     */
    public static function blank(): array {
        return array_fill_keys(['tp', 'fp', 'tn', 'fn', 'unknown', 'pending', 'abstained', 'total', 'squared'], 0);
    }

    /**
     * Add one frozen prediction/label pair. Unknown outcomes are never treated as negatives.
     */
    public static function add(array $totals, int $flag, int $outcome, ?float $probability): array {
        $totals['total']++;
        if ($outcome === -1) {
            $totals['pending']++;
        } else if ($outcome === 2) {
            $totals['unknown']++;
        } else if ($flag === -1) {
            $totals['abstained']++;
        } else {
            $key = $outcome ? ($flag ? 'tp' : 'fn') : ($flag ? 'fp' : 'tn');
            $totals[$key]++;
            if ($probability !== null) {
                $totals['squared'] += ($probability - $outcome) ** 2;
            }
        }
        return $totals;
    }

    /**
     * Round counts to conservative bands, suppress all small released cells.
     */
    public static function band(int $count): string {
        $min = self::minimum();
        if ($count < $min) {
            return '—';
        }
        $low = intdiv($count, 5) * 5;
        return $low . '–' . ($low + 4);
    }

    /**
     * Goodness of prediction for labelled and non-abstained records.
     */
    public static function measures(array $counts): array {
        $fraction = static fn($part, $total) => $total ? $part / $total : null;
        $labelled = $counts['tp'] + $counts['tn'] + $counts['fp'] + $counts['fn'];
        return [
            'precision' => $fraction($counts['tp'], $counts['tp'] + $counts['fp']),
            'recall' => $fraction($counts['tp'], $counts['tp'] + $counts['fn']),
            'fpr' => $fraction($counts['fp'], $counts['fp'] + $counts['tn']),
            'fnr' => $fraction($counts['fn'], $counts['fn'] + $counts['tp']),
            'accuracy' => $fraction($counts['tp'] + $counts['tn'], $labelled),
            'brier' => $fraction($counts['squared'], $labelled),
        ];
    }

    /**
     * Release supported quality ratios and only pre-defined coarse bands.
     */
    public static function published(array $counts, bool $statistical): array {
        $k = self::minimum();
        $measures = self::measures($counts);
        $support = [
            'precision' => min($counts['tp'], $counts['fp']),
            'recall' => min($counts['tp'], $counts['fn']),
            'fpr' => min($counts['fp'], $counts['tn']),
            'fnr' => min($counts['fn'], $counts['tp']),
            'accuracy' => min($counts['tp'] + $counts['tn'], $counts['fp'] + $counts['fn']),
            'brier' => min($counts['tp'] + $counts['fn'], $counts['tn'] + $counts['fp']),
        ];
        $output = [];
        $bands = ['total', 'pending', 'unknown', 'abstained', 'tp', 'fp', 'tn', 'fn'];
        foreach ($bands as $key) {
            $output[$key] = self::band($counts[$key]);
        }
        foreach ($measures as $key => $number) {
            if ($number === null || $support[$key] < $k || ($key === 'brier' && !$statistical)) {
                $output[$key] = '—';
            } else {
                $output[$key] = $key === 'brier' ? format_float(round($number / 0.05) * 0.05, 2) :
                    format_float(round($number * 20) * 5, 0) . '%';
            }
        }
        return $output;
    }

    /**
     * Retrieve evaluated cohorts with authorized course IDs before reading any row.
     */
    public static function cohorts(array $courses, int $cohort, bool $statistical): array {
        global $DB;
        $out = [];
        foreach (array_chunk(array_keys($courses), 150) as $ids) {
            [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'course');
            $rows = $DB->get_recordset_select(
                store::SAMPLE,
                "courseid $sql AND cohort = :cohort AND due <= :asof",
                $params + ['cohort' => $cohort, 'asof' => time()],
                '',
                'id, courseid, rulealert, ruleversion, modelalert, probability, modelversion, modelfingerprint, outcome'
            );
            try {
                foreach ($rows as $row) {
                    $version = $statistical ? $row->modelfingerprint : $row->ruleversion;
                    if (!$version) {
                        continue;
                    }
                    $label = $statistical ? $row->modelversion . ' · ' . $version : $version;
                    $group = $row->courseid;
                    $prior = $out[$group][$label] ?? self::blank();
                    $out[$group][$label] = self::add(
                        $prior,
                        (int)($statistical ? $row->modelalert : $row->rulealert),
                        (int)$row->outcome,
                        $statistical && $row->probability !== null ? (float)$row->probability : null
                    );
                }
            } finally {
                $rows->close();
            }
        }
        return $out;
    }
}
