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

namespace local_skillupsquestionstudio\generation;

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\question;

/**
 * Deterministic document-derived draft generator for Community installations.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class local implements provider {
    /**
     * Generate editable items from glossary entries or substantive prose.
     */
    public function generate(string $source, array $request, \context_course $context, int $userid): array {
        $concepts = self::concepts($source);
        $result = [];
        $kind = $request['kind'];
        $strings = get_string_manager();
        $lang = $request['lang'];
        $base = array_intersect_key($request, array_flip(['kind', 'bloom', 'level', 'competency', 'unit']));
        $limit = $request['count'];
        $count = count($concepts);
        if ($kind === 'match') {
            foreach (array_chunk($concepts, 3) as $group) {
                if (count($group) < 3 || count($result) >= $limit) {
                    break;
                }
                $label = implode(', ', array_column($group, 'left'));
                $result[] = $base + [
                    'name' => \core_text::substr($label, 0, 150),
                    'prompt' => $strings->get_string('promptmatch', access::C, $label, $lang), 'pairs' => $group,
                ];
            }
            return $result;
        }
        foreach ($concepts as $index => $concept) {
            if (count($result) >= $limit) {
                break;
            }
            if ($kind === 'multichoice' && $count < 3) {
                break;
            }
            $term = $concept['left'];
            $excerpt = $concept['right'];
            $row = $base + ['name' => $term];
            switch ($kind) {
                case 'multichoice':
                    $choices = [$excerpt, $concepts[($index + 1) % $count]['right'], $concepts[($index + 2) % $count]['right']];
                    $row += [
                        'prompt' => $strings->get_string('promptchoice', access::C, $term, $lang),
                        'answers' => $choices, 'correct' => 0,
                    ];
                    $row = question::reorder($row, $term);
                    break;
                case 'truefalse':
                    $truth = $count < 2 || $index % 2 === 0;
                    $statement = $truth ? $excerpt : $concepts[($index + 1) % $count]['right'];
                    $row += [
                        'prompt' => $strings->get_string(
                            'prompttruth',
                            access::C,
                            (object)['term' => $term, 'statement' => $statement],
                            $lang
                        ),
                        'truth' => $truth, 'feedback' => $excerpt,
                    ];
                    break;
                case 'shortanswer':
                    $row += [
                        'prompt' => $strings->get_string('promptshort', access::C, $excerpt, $lang), 'answers' => [$term],
                    ];
                    break;
                default:
                    $row += [
                        'prompt' => $strings->get_string('promptessay', access::C, $term, $lang),
                        'rubric' => $strings->get_string('promptrubric', access::C, $excerpt, $lang),
                    ];
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Find diverse terms, with glossary syntax preferred over prose fragments.
     */
    private static function concepts(string $source): array {
        $chunks = preg_split('/\n+|(?<=[.!?])\s+(?=\p{Lu})/u', $source, 400);
        $seen = [];
        $result = [];
        foreach ($chunks as $chunk) {
            $chunk = trim(preg_replace('/\s+/u', ' ', $chunk));
            if (
                !preg_match('/\A([\p{L}\p{N}][\p{L}\p{N}\s\-]{1,80})\s*:\s*(.{18,})/u', $chunk, $match) &&
                    !preg_match('/\A([\p{L}\p{N}\-]+(?:\s+[\p{L}\p{N}\-]+){1,2})\s+(.{30,})/u', $chunk, $match)
            ) {
                continue;
            }
            $left = trim($match[1]);
            $right = \core_text::substr(trim($match[2]), 0, 300);
            $a = question::normalize($left);
            $b = question::normalize($right);
            if (!$a || !$b || isset($seen[$a]) || isset($seen[$b])) {
                continue;
            }
            $seen[$a] = $seen[$b] = true;
            $result[] = ['left' => $left, 'right' => $right];
            if (count($result) >= 60) {
                break;
            }
        }
        return $result;
    }
}
