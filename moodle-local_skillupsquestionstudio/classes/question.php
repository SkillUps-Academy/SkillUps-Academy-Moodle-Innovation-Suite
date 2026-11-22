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

/**
 * A strict structure shared by authoring, generation, review and XML.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question {
    /**
     * Supported Moodle types, with match normalized to Moodle's XML matching type.
     */
    public const TYPES = ['multichoice', 'truefalse', 'match', 'shortanswer', 'essay'];
    /**
     * Localized Bloom levels.
     */
    public const BLOOM = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];
    /**
     * Localized difficulty scales.
     */
    public const LEVELS = ['easy', 'medium', 'hard'];

    /**
     * Validate data without interpreting HTML or executable constructs.
     */
    public static function validate(array $data): array {
        $kind = $data['kind'] ?? null;
        $bloom = $data['bloom'] ?? null;
        $level = $data['level'] ?? null;
        $competency = $data['competency'] ?? 0;
        if (
            !in_array($kind, self::TYPES, true) || !in_array($bloom, self::BLOOM, true) ||
                !in_array($level, self::LEVELS, true) || !is_int($competency) || $competency < 0
        ) {
            access::fail('invalidquestion');
        }
        $result = [
            'kind' => $kind, 'bloom' => $bloom, 'level' => $level, 'competency' => $competency,
            'name' => self::text($data['name'] ?? null, 200),
            'prompt' => self::text($data['prompt'] ?? null, 4000),
            'unit' => self::text($data['unit'] ?? '', 150, true),
            'feedback' => self::text($data['feedback'] ?? '', 2000, true),
        ];
        switch ($kind) {
            case 'multichoice':
                $choices = self::answers($data['answers'] ?? null, 3, 6);
                $correct = $data['correct'] ?? null;
                if (!is_int($correct) || !array_key_exists($correct, $choices)) {
                    access::fail('invalidanswers');
                }
                return $result + ['answers' => $choices, 'correct' => $correct];
            case 'truefalse':
                if (!is_bool($data['truth'] ?? null)) {
                    access::fail('invalidanswers');
                }
                return $result + ['truth' => $data['truth']];
            case 'match':
                $pairs = $data['pairs'] ?? null;
                if (!is_array($pairs) || !array_is_list($pairs) || count($pairs) < 3 || count($pairs) > 8) {
                    access::fail('invalidanswers');
                }
                $left = self::answers(array_column($pairs, 'left'), count($pairs), 8);
                $right = self::answers(array_column($pairs, 'right'), count($pairs), 8);
                return $result + ['pairs' => array_map(
                    static fn($a, $b) => ['left' => $a, 'right' => $b],
                    $left,
                    $right
                )];
            case 'shortanswer':
                $answers = self::answers($data['answers'] ?? null, 1, 8);
                foreach ($answers as $answer) {
                    if (str_contains($answer, '*') || str_contains($answer, '\\')) {
                        access::fail('invalidanswers');
                    }
                }
                return $result + ['answers' => $answers];
            case 'essay':
                return $result + ['rubric' => self::text($data['rubric'] ?? null, 3000)];
        }
        access::fail('invalidquestion');
    }

    /**
     * Check input text length, UTF-8 and valid XML scalar values.
     */
    public static function text(mixed $value, int $max, bool $empty = false): string {
        if (
            !is_string($value) || !mb_check_encoding($value, 'UTF-8') || \core_text::strlen($value) > $max ||
                preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x{fffe}\x{ffff}]/u', $value) ||
                (!$empty && trim($value) === '')
        ) {
            access::fail('invalidquestion');
        }
        return trim($value);
    }

    /**
     * Detect blank or equivalent answers.
     */
    private static function answers(mixed $answers, int $min, int $max): array {
        if (!is_array($answers) || !array_is_list($answers) || count($answers) < $min || count($answers) > $max) {
            access::fail('invalidanswers');
        }
        $texts = array_map(static fn($answer) => self::text($answer, 500), $answers);
        $keys = array_map(self::normalize(...), $texts);
        if (in_array('', $keys, true) || count(array_unique($keys)) !== count($texts)) {
            access::fail('invalidanswers');
        }
        return $texts;
    }

    /**
     * Normalize visible text for exact and near duplicate detection.
     */
    public static function normalize(string $text): string {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', \core_text::strtolower($text)));
    }

    /**
     * Generate an exact per-project deduplication key from the prompt.
     */
    public static function fingerprint(array $question): string {
        return hash('sha256', self::normalize($question['prompt']));
    }

    /**
     * Rank reproducibly without reusing the global random state.
     */
    public static function ranked(array $items, string $seed): array {
        $keys = [];
        foreach ($items as $key => $value) {
            $keys[$key] = hash('sha256', $seed . ':' . $key);
        }
        asort($keys, SORT_STRING);
        $ordered = [];
        foreach (array_keys($keys) as $key) {
            $ordered[$key] = $items[$key];
        }
        return $ordered;
    }

    /**
     * Reorder choices or pairs while keeping the answer key consistent.
     */
    public static function reorder(array $data, string $seed): array {
        if ($data['kind'] === 'multichoice') {
            $choices = self::ranked($data['answers'], $seed);
            $data['correct'] = (int)array_search($data['correct'], array_keys($choices), true);
            $data['answers'] = array_values($choices);
        } else if ($data['kind'] === 'match') {
            $data['pairs'] = array_values(self::ranked($data['pairs'], $seed));
        }
        return $data;
    }
}
