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

namespace local_skillupsaigrading;

use local_skillupsaigrading\ai\provider_failure;

/**
 * Validate that provider output cannot supply grades or evidence outside the native rubric and excerpt.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evaluation_test extends \basic_testcase {
    /**
     * Minimal rubric with a nonzero minimum to test Moodle normalization.
     */
    private function rubric(bool $lockzero = false): array {
        return ['lockzero' => $lockzero, 'criteria' => [[
            'id' => 8, 'description' => 'Measures erosion using evidence',
            'levels' => [['id' => 22, 'score' => 2.0], ['id' => 23, 'score' => 4.0], ['id' => 24, 'score' => 6.0]],
        ]]];
    }

    /**
     * Use native level scores, ignoring any direct numeric score from the provider.
     */
    public function test_grounded_level_uses_native_score_and_normalization(): void {
        $excerpt = 'Three marked sites showed erosion.';
        $reply = evaluation::json(['criteria' => [[
            'id' => 8, 'level' => 23, 'score' => 999, 'quotes' => ['sites showed erosion'],
            'rationale' => 'The observation covers three sites.', 'gap' => 'Explain the sampling interval.', 'uncertain' => false,
        ]], 'feedback' => 'Describe measurements more precisely.']);
        $result = evaluation::validate($reply, $this->rubric(), $excerpt);
        $this->assertSame(4.0, $result['criteria'][0]['score']);
        $this->assertSame(50.0, $result['percent']);
        $this->assertEqualsWithDelta(66.66667, evaluation::validate($reply, $this->rubric(true), $excerpt)['percent'], 0.00001);
    }

    /**
     * Suppress recommendations with invented evidence or insufficient confidence.
     */
    public function test_unsupported_quotes_leave_score_uncertain(): void {
        $reply = evaluation::json(['criteria' => [[
            'id' => 8, 'level' => 24, 'quotes' => ['Invented evidence'],
            'rationale' => 'Plausible but invented.', 'uncertain' => false,
        ]]]);
        $result = evaluation::validate($reply, $this->rubric(), 'Three sites showed erosion.');
        $this->assertTrue($result['criteria'][0]['uncertain']);
        $this->assertSame('', $result['criteria'][0]['rationale']);
        $this->assertNull($result['percent']);
    }

    /**
     * Refuse cross-rubric criterion IDs.
     */
    public function test_unknown_criterion_is_rejected(): void {
        $this->expectException(provider_failure::class);
        evaluation::validate('{"criteria":[{"id":99}]}', $this->rubric(), 'Three sites showed erosion.');
    }

    /**
     * Discard evidence and feedback when the model flags use of sensitive attributes.
     */
    public function test_flagged_sensitive_reasoning_is_discarded(): void {
        $excerpt = 'Three marked sites showed erosion.';
        $reply = evaluation::json(['criteria' => [[
            'id' => 8, 'level' => 23, 'quotes' => ['sites showed erosion'], 'gap' => 'Sensitive inference.',
            'rationale' => 'Sensitive inference.', 'uncertain' => false, 'sensitive' => true,
        ]], 'feedback' => 'Sensitive inference.']);
        $result = evaluation::validate($reply, $this->rubric(), $excerpt);
        $this->assertSame([], $result['criteria'][0]['quotes']);
        $this->assertSame('', $result['criteria'][0]['gap']);
        $this->assertSame('', $result['feedback']);
        $this->assertNull($result['percent']);
    }

    /**
     * Allow teacher excerpts only from verified source text.
     */
    public function test_excerpt_selection_cannot_add_unapproved_material(): void {
        $source = 'One authentic excerpt. Another authentic excerpt.';
        $this->assertSame("One authentic\nAnother authentic", evaluation::select("One authentic\nAnother authentic", $source));
        $this->expectException(\moodle_exception::class);
        evaluation::select("One authentic\nA different student answer", $source);
    }
}
