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
 * Moodle navigation and Mustache render helpers.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view {
    /**
     * Route within the plugin.
     */
    public static function url(string $page, array $params): \moodle_url {
        return new \moodle_url('/local/skillupsquestionstudio/' . $page . '.php', $params);
    }

    /**
     * Configure one Moodle course page.
     */
    public static function page(\moodle_url $url, int $id, string $title): void {
        global $PAGE, $COURSE;
        $PAGE->set_url($url);
        $PAGE->set_context(access::context($id));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title(get_string($title, access::C));
        $PAGE->set_heading($COURSE->fullname);
        $PAGE->navbar->add(get_string('pluginname', access::C), self::url('index', ['courseid' => $id]));
    }

    /**
     * Resolve an enum's localized names.
     */
    public static function choices(string $prefix, array $ids): array {
        $choices = [];
        foreach ($ids as $id) {
            $choices[$id] = get_string($prefix . $id, access::C);
        }
        return $choices;
    }

    /**
     * Shape a validated question for an escaped preview.
     */
    public static function question(array $data, bool $key = true): array {
        $answers = [];
        $pool = [];
        switch ($data['kind']) {
            case 'multichoice':
                foreach ($data['answers'] as $index => $answer) {
                    $answers[] = ['text' => $answer, 'correct' => $key && $index === $data['correct']];
                }
                break;
            case 'truefalse':
                foreach ([true => 'true', false => 'false'] as $value => $label) {
                    $answers[] = ['text' => get_string($label, 'qtype_truefalse'),
                        'correct' => $key && (bool)$value === $data['truth']];
                }
                break;
            case 'match':
                foreach ($data['pairs'] as $pair) {
                    $answers[] = ['text' => $key ? $pair['left'] . ' → ' . $pair['right'] : $pair['left']];
                }
                if (!$key) {
                    $pool = array_values(question::ranked(array_column($data['pairs'], 'right'), $data['prompt']));
                }
                break;
            case 'shortanswer':
                $answers = $key ? array_map(static fn($text) => ['text' => $text, 'correct' => true], $data['answers']) : [];
                break;
        }
        return [
            'name' => $data['name'], 'prompt' => $data['prompt'],
            'kind' => get_string('type_' . $data['kind'], access::C),
            'level' => get_string('level_' . $data['level'], access::C),
            'bloom' => get_string('bloom_' . $data['bloom'], access::C), 'unit' => $data['unit'],
            'feedback' => $key ? $data['feedback'] : '', 'rubric' => $key ? ($data['rubric'] ?? '') : '',
            'answers' => $answers, 'pool' => $pool,
            'blank' => !$key && in_array($data['kind'], ['shortanswer', 'essay'], true),
        ];
    }
}
