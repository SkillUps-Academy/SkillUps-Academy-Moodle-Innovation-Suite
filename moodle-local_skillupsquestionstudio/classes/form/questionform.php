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

namespace local_skillupsquestionstudio\form;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\question;
use local_skillupsquestionstudio\view;

/**
 * Native Moodle form supporting all Studio question types.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questionform extends \moodleform {
    /**
     * Define question, classification and type-dependent controls.
     */
    protected function definition(): void {
        $form = $this->_form;
        foreach (['name', 'unit'] as $field) {
            $form->addElement('text', $field, get_string($field, access::C), ['maxlength' => 150]);
            $form->setType($field, PARAM_TEXT);
        }
        $form->addElement('select', 'kind', get_string('kind', access::C), view::choices('type_', question::TYPES));
        $form->addElement('select', 'bloom', get_string('bloom', access::C), view::choices('bloom_', question::BLOOM));
        $form->addElement('select', 'level', get_string('level', access::C), view::choices('level_', question::LEVELS));
        $form->setDefault('level', 'medium');
        $form->addElement('select', 'competency', get_string('competency', access::C), $this->_customdata);
        foreach (['prompt', 'feedback', 'choices', 'short', 'left', 'right', 'rubric'] as $field) {
            $form->addElement('textarea', $field, get_string($field, access::C), ['rows' => 4, 'cols' => 60]);
            $form->setType($field, PARAM_RAW_TRIMMED);
        }
        $form->addElement('select', 'correct', get_string('correct', access::C), array_combine(range(1, 6), range(1, 6)));
        $form->addElement('select', 'truth', get_string('correct', access::C), [1 => get_string('true', 'qtype_truefalse'),
            0 => get_string('false', 'qtype_truefalse')]);
        foreach (
            ['choices' => 'multichoice', 'correct' => 'multichoice', 'truth' => 'truefalse',
                'short' => 'shortanswer', 'left' => 'match', 'right' => 'match', 'rubric' => 'essay'] as $field => $kind
        ) {
            $form->hideIf($field, 'kind', 'neq', $kind);
        }
        $form->addElement('hidden', 'base', 0);
        $form->setType('base', PARAM_INT);
        $this->add_action_buttons(true, get_string('saverevision', access::C));
    }

    /**
     * Show safe localized errors from the common validator.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            question::validate(self::model((object)$data));
        } catch (\moodle_exception $exception) {
            $field = match ($data['kind'] ?? '') {
                'multichoice' => 'choices', 'match' => 'left', 'shortanswer' => 'short', default => 'prompt',
            };
            $errors[$exception->errorcode === 'invalidanswers' ? $field : 'prompt'] = $exception->getMessage();
        }
        return $errors;
    }

    /**
     * Convert plain multiline controls to the common question structure.
     */
    public static function model(\stdClass $data): array {
        $base = [
            'kind' => $data->kind ?? '', 'bloom' => $data->bloom ?? '', 'level' => $data->level ?? '',
            'name' => $data->name ?? '', 'unit' => $data->unit ?? '', 'prompt' => $data->prompt ?? '',
            'feedback' => $data->feedback ?? '', 'competency' => (int)($data->competency ?? 0),
        ];
        return $base + match ($base['kind']) {
            'multichoice' => ['answers' => self::lines($data->choices ?? ''), 'correct' => (int)($data->correct ?? 0) - 1],
            'truefalse' => ['truth' => isset($data->truth) && (int)$data->truth === 1],
            'shortanswer' => ['answers' => self::lines($data->short ?? '')],
            'match' => ['pairs' => array_map(
                static fn($lhs, $rhs) => ['left' => $lhs, 'right' => $rhs],
                self::lines($data->left ?? ''), self::lines($data->right ?? '')
            )],
            'essay' => ['rubric' => $data->rubric ?? ''],
            default => [],
        };
    }

    /**
     * Present a stored question as native form values.
     */
    public static function fields(array $model): array {
        return $model + match ($model['kind']) {
            'multichoice' => ['choices' => implode("\n", $model['answers'])],
            'shortanswer' => ['short' => implode("\n", $model['answers'])],
            'match' => ['left' => implode("\n", array_column($model['pairs'], 'left')),
                'right' => implode("\n", array_column($model['pairs'], 'right'))],
            default => [],
        };
    }

    /**
     * Split a bounded expected plaintext list; blank inner lines are invalid.
     */
    private static function lines(string $data): array {
        return preg_split('/\R/u', trim($data), 10);
    }
}
