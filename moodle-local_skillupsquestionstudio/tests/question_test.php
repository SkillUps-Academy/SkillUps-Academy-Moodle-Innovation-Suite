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

namespace local_skillupsquestionstudio;

use local_skillupsquestionstudio\bank\xml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit checks for authored and generated questions, answer weights and XML escaping.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(question::class)]
#[CoversClass(xml::class)]
final class question_test extends \basic_testcase {
    /**
     * Make an invalid item from each shape.
     */
    public static function invalid_cases(): array {
        return [
            'choice index' => [['kind' => 'multichoice', 'answers' => ['Alpha', 'Beta', 'Gamma'], 'correct' => 3]],
            'repeated choice' => [['kind' => 'multichoice', 'answers' => ['Alpha', 'alpha!', 'Gamma'], 'correct' => 1]],
            'explicit truth' => [['kind' => 'truefalse', 'truth' => 'false']],
            'matching field' => [['kind' => 'match', 'pairs' => [
                ['left' => 'A', 'right' => 'B'], ['left' => 'C', 'right' => 'D'],
            ]]],
            'wildcard answer' => [['kind' => 'shortanswer', 'answers' => ['*']]],
            'essay rubric' => [['kind' => 'essay', 'rubric' => '']],
        ];
    }

    /**
     * Reject bad answer structures.
     */
    #[DataProvider('invalid_cases')]
    public function test_invalid_answer_structures(array $shape): void {
        $this->expectException(\moodle_exception::class);
        question::validate($shape + [
            'name' => 'Example', 'prompt' => 'What is good teaching?', 'bloom' => 'apply', 'level' => 'easy',
        ]);
    }

    /**
     * Preserve the correct choice after reproducible shuffling and keep markup inert in XML.
     */
    public function test_variant_keeps_answer_and_escapes_xml(): void {
        $draft = question::validate([
            'kind' => 'multichoice', 'name' => 'A & B', 'prompt' => '<example> & evidence',
            'answers' => ['Correct', 'Distractor 1', 'Distractor 2'], 'correct' => 0,
            'bloom' => 'understand', 'level' => 'medium',
        ]);
        $one = question::reorder($draft, 'version-a');
        $this->assertSame($one, question::reorder($draft, 'version-a'));
        $this->assertSame('Correct', $one['answers'][$one['correct']]);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML(xml::generate([$one]), LIBXML_NONET));
        $xpath = new \DOMXPath($doc);
        $this->assertSame('<example> & evidence', $xpath->evaluate('string(/quiz/question/questiontext/text)'));
        $this->assertSame('Correct', $xpath->evaluate('string(/quiz/question/answer[@fraction="100"]/text)'));
    }
}
