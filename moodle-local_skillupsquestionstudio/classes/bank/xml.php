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

namespace local_skillupsquestionstudio\bank;

use local_skillupsquestionstudio\question;

/**
 * Generate Moodle XML using text nodes for all author or model content.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class xml {
    /**
     * Encode the five supported Core question types.
     */
    public static function generate(array $records): string {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $quiz = $doc->appendChild($doc->createElement('quiz'));
        foreach ($records as $data) {
            $data = question::validate($data);
            $node = $quiz->appendChild($doc->createElement('question'));
            $node->setAttribute('type', $data['kind'] === 'match' ? 'matching' : $data['kind']);
            self::rich($node, 'name', $data['name'], false);
            self::rich($node, 'questiontext', $data['prompt']);
            self::rich($node, 'generalfeedback', $data['feedback']);
            self::field($node, 'defaultgrade', '1');
            self::field($node, 'penalty', '0');
            switch ($data['kind']) {
                case 'multichoice':
                    self::field($node, 'single', 'true');
                    self::field($node, 'shuffleanswers', '0');
                    self::field($node, 'answernumbering', 'abc');
                    foreach ($data['answers'] as $index => $answer) {
                        self::answer($node, $answer, $index === $data['correct']);
                    }
                    break;
                case 'truefalse':
                    self::answer($node, 'true', $data['truth']);
                    self::answer($node, 'false', !$data['truth']);
                    break;
                case 'shortanswer':
                    self::field($node, 'usecase', '0');
                    foreach ($data['answers'] as $answer) {
                        self::answer($node, $answer, true);
                    }
                    break;
                case 'match':
                    self::field($node, 'shuffleanswers', '0');
                    foreach ($data['pairs'] as $pair) {
                        $sub = self::rich($node, 'subquestion', $pair['left']);
                        self::rich($sub, 'answer', $pair['right'], false);
                    }
                    break;
                case 'essay':
                    self::field($node, 'responseformat', 'editor');
                    self::field($node, 'attachments', '0');
                    self::field($node, 'responsefieldlines', '15');
                    self::rich($node, 'graderinfo', $data['rubric']);
                    break;
            }
            $tags = $node->appendChild($doc->createElement('tags'));
            $labels = ['Bloom - ' . $data['bloom'], 'Difficulty - ' . $data['level']];
            if ($data['unit']) {
                $labels[] = 'Unit - ' . $data['unit'];
            }
            if ($data['competency']) {
                $labels[] = 'Course competency - ' . $data['competency'];
            }
            foreach ($labels as $label) {
                self::rich($tags, 'tag', $label, false);
            }
        }
        return $doc->saveXML();
    }

    /**
     * Add one safe text scalar.
     */
    private static function field(\DOMElement $parent, string $name, string $text): void {
        $doc = $parent->ownerDocument;
        $parent->appendChild($doc->createElement($name))->appendChild($doc->createTextNode($text));
    }

    /**
     * Construct Moodle's nested text structure.
     */
    private static function rich(\DOMElement $parent, string $name, string $text, bool $formatted = true): \DOMElement {
        $node = $parent->appendChild($parent->ownerDocument->createElement($name));
        if ($formatted) {
            $node->setAttribute('format', 'plain_text');
        }
        self::field($node, 'text', $text);
        return $node;
    }

    /**
     * Add a validated answer weight.
     */
    private static function answer(\DOMElement $parent, string $text, bool $correct): void {
        $node = self::rich($parent, 'answer', $text);
        $node->setAttribute('fraction', $correct ? '100' : '0');
    }
}
