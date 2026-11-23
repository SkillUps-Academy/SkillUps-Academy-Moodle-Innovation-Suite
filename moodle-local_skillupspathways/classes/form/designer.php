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

namespace local_skillupspathways\form;

use local_skillupspathways\access;
use local_skillupspathways\graph;
use local_skillupspathways\ui;

/**
 * Moodle-backed metadata, save and publish controls for the visual editor.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class designer extends \moodleform {
    /**
     * Build the editor's Moodle Form.
     */
    public function definition(): void {
        $form = $this->_form;
        $form->addElement('text', 'name', access::text('pathname'), ['size' => 55, 'maxlength' => 180]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'required', null, 'server');
        $form->addRule('name', null, 'maxlength', 180, 'server');
        $form->addElement('textarea', 'description', access::text('description'), ['rows' => 3, 'cols' => 55]);
        $form->setType('description', PARAM_RAW_TRIMMED);
        $form->addElement('advcheckbox', 'enabled', access::text('enabled'));
        $form->setDefault('enabled', 1);
        $form->addElement('advcheckbox', 'certificate', access::text('certificate'));
        $form->addElement('hidden', 'revision', 0);
        $form->setType('revision', PARAM_INT);
        $form->addElement('hidden', 'graph', '{"nodes":[],"edges":[]}');
        $form->setType('graph', PARAM_RAW);
        $form->addElement('html', \html_writer::div('', '', [
            'id' => 'skillups-pathways-designer',
            'data-labels' => json_encode(ui::editor_labels(), JSON_THROW_ON_ERROR),
        ]));
        $form->addGroup([
            $form->createElement('submit', 'savedraft', access::text('savedraft')),
            $form->createElement('submit', 'publish', access::text('publish')),
            $form->createElement('cancel', 'cancel', get_string('cancel')),
        ], 'actions', '', ' ', false);
    }

    /**
     * Validate posted graph data and attach rendering feedback to a visible field.
     *
     * @param array $data Submitted values
     * @param array $files Submitted files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            graph::parse($data['graph'], !empty($data['publish']));
        } catch (\moodle_exception $exception) {
            $errors['name'] = access::text($exception->errorcode);
        }
        return $errors;
    }
}
