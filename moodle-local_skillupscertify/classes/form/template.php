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

namespace local_skillupscertify\form;

use local_skillupscertify\access;
use local_skillupscertify\credential\design;
use local_skillupscertify\evidence\moodle;
use local_skillupscertify\store;

/**
 * Template editing form with controlled fields and PDF preview.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template extends \moodleform {
    /**
     * Add standard Moodle controls.
     */
    public function definition(): void {
        $form = $this->_form;
        $form->addElement('hidden', 'revision', 0);
        $form->setType('revision', PARAM_INT);
        foreach (['label' => 180, 'title' => 90, 'issuer' => 130, 'signer' => 100] as $field => $length) {
            $form->addElement('text', $field, access::text($field), ['maxlength' => $length, 'size' => 48]);
            $form->setType($field, PARAM_TEXT);
            if ($field !== 'signer') {
                $form->addRule($field, null, 'required', null, 'server');
            }
        }
        $form->addElement('textarea', 'body', access::text('body'), ['maxlength' => 500, 'rows' => 4, 'cols' => 70]);
        $form->setType('body', PARAM_RAW_TRIMMED);
        $form->addRule('body', null, 'required', null, 'server');
        $form->addElement('static', 'fields', access::text('fields'), implode(' · ', array_map(
            static fn(string $field): string => '{' . $field . '}',
            design::FIELDNAMES
        )));
        $form->setDefault('body', access::text('defaultbody'));
        $form->addElement('select', 'orientation', access::text('orientation'), [
            'L' => access::text('landscape'), 'P' => access::text('portrait'),
        ]);
        $choices = [];
        foreach (array_keys(design::COLORS) as $palette) {
            $choices[$palette] = access::text('color_' . $palette);
        }
        $form->addElement('select', 'color', access::text('color'), $choices);
        $sources = ['course' => access::text('source_course'), 'manual' => access::text('source_manual')];
        if (moodle::pathways() || !empty($this->_customdata['pathway'])) {
            $sources['pathway'] = access::text('source_pathway');
        }
        $form->addElement('select', 'source', access::text('evidence'), $sources);
        $form->addElement('text', 'pathid', access::text('pathid'), ['size' => 10]);
        $form->setType('pathid', PARAM_INT);
        $form->hideIf('pathid', 'source', 'neq', 'pathway');
        $form->addElement('advcheckbox', 'publicmeta', access::text('publicmeta'));
        $form->addElement('advcheckbox', 'enabled', access::text('enabled'));
        $form->setDefault('enabled', 1);
        $form->addElement('html', \html_writer::div('', '', ['id' => 'skillups-certify-preview']));
        $form->addGroup([
            $form->createElement('submit', 'save', get_string('savechanges')),
            $form->createElement('submit', 'preview', access::text('previewpdf'), ['formtarget' => '_blank']),
            $form->createElement('cancel', 'cancel', get_string('cancel')),
        ], 'actions', '', ' ', false);
    }

    /**
     * Test the exact design rules before saving.
     *
     * @param array $data Form fields
     * @param array $files Files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            store::config((object)$data);
        } catch (\moodle_exception $failure) {
            $errors[$failure->errorcode === 'invalidsource' ? 'source' : 'body'] = access::text($failure->errorcode);
        }
        return $errors;
    }
}
