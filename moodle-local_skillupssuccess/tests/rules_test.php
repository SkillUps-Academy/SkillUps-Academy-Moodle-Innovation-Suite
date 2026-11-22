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
 * Synthetic cases for rule separation, frozen model validation and quality diagnostics.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules_test extends \advanced_testcase {
    /**
     * Missing academic signals are never imputed as rule violations.
     */
    public function test_rules_explain_thresholds_and_unknowns(): void {
        $limits = rules::config(['high' => 2]);
        $unknown = rules::evaluate([], $limits);
        $this->assertSame('unknown', $unknown['risk']);
        $this->assertSame([], $unknown['triggers']);
        $review = rules::evaluate(['overdue' => 2, 'grade' => 60, 'decline' => 13], $limits);
        $this->assertSame('high', $review['risk']);
        $this->assertSame(['value' => 2, 'limit' => 2], $review['triggers']['overdue']);
        $this->assertArrayNotHasKey('grade', $review['triggers']);
        $this->assertSame($unknown['version'], $review['version']);
    }

    /**
     * The model contract rejects unapproved weights and provides separate feature contributions.
     */
    public function test_frozen_logistic_predictions(): void {
        $model = ['approved' => true, 'version' => 'synthetic-v1', 'target' => 'independent_support_need_14d',
            'threshold' => .5, 'intercept' => -2.0, 'weights' => ['overdue' => 5.0]];
        $this->assertNull(predictor::validate(['approved' => false] + $model));
        $this->assertNull(predictor::validate(['weights' => ['birth_year' => 5.0]] + $model));
        $valid = predictor::validate($model);
        $this->assertNull(predictor::score([], $valid));
        $positive = predictor::score(['overdue' => 4], $valid);
        $negative = predictor::score(['overdue' => 0], $valid);
        $this->assertSame(1, $positive['flag']);
        $this->assertSame(0, $negative['flag']);
        $this->assertEqualsWithDelta(0.880797, $positive['probability'], .0001);
        $this->assertSame(['overdue' => 4.0], $positive['contributions']);
    }

    /**
     * Recover known false positive/negative rates and suppress small cells.
     */
    public function test_quality_metrics_and_disclosure(): void {
        $this->resetAfterTest();
        set_config('mincell', 5, util::C);
        $sample = [[1, 1, .9], [1, 0, .8], [0, 1, .4], [0, 0, .1], [-1, 1, null], [1, 2, .9], [0, -1, .1]];
        $counts = quality::blank();
        foreach ($sample as [$flag, $outcome, $value]) {
            $counts = quality::add($counts, $flag, $outcome, $value);
        }
        $this->assertEquals(
            [1, 1, 1, 1, 1, 1, 1],
            array_values(array_intersect_key($counts, array_flip(['tp', 'fp', 'tn', 'fn', 'unknown', 'pending', 'abstained'])))
        );
        $measures = quality::measures($counts);
        $this->assertEquals(.5, $measures['fpr']);
        $this->assertEquals(.5, $measures['fnr']);
        $this->assertEqualsWithDelta(.255, $measures['brier'], .0001);
        $this->assertSame('—', quality::published($counts, true)['fp']);
        $this->assertSame('10–14', quality::band(12));
    }

    /**
     * Comparative diagnostics preserve disparate error rates for supported academic cohorts.
     */
    public function test_group_error_comparison_keeps_false_negatives_and_positives_visible(): void {
        $this->resetAfterTest();
        set_config('mincell', 5, util::C);
        $baseline = quality::blank();
        $major = ['tp' => 10, 'fp' => 15, 'tn' => 5, 'fn' => 10] + $baseline;
        $minor = ['tp' => 10, 'fp' => 5, 'tn' => 15, 'fn' => 10] + $baseline;
        $this->assertSame('75%', quality::published($major, false)['fpr']);
        $this->assertSame('25%', quality::published($minor, false)['fpr']);
        $this->assertSame('50%', quality::published($major, false)['fnr']);
        $this->assertSame('—', quality::published($major, false)['brier']);
    }
}
