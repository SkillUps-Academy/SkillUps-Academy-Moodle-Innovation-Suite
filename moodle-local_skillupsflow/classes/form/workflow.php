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

namespace local_skillupsflow\form;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle form that submits the visual definition and optimistic revision identity.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow extends \moodleform {
    /**
     * Define accessible server-side fields around the editor.
     */
    protected function definition(): void {
        $form = $this->_form;
        $c = 'local_skillupsflow';
        $form->addElement('text', 'name', get_string('name', $c), ['maxlength' => 150]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', get_string('required'), 'required', null, 'client');
        $form->addElement('hidden', 'graph');
        $form->setType('graph', PARAM_RAW);
        $form->addElement('hidden', 'baseversion');
        $form->setType('baseversion', PARAM_INT);
        $this->add_action_buttons(true, get_string('saverevision', $c));
    }

    /**
     * Validate direct submissions and malformed editor contents.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (trim($data['name'] ?? '') === '' || \core_text::strlen($data['name']) > 150) {
            $errors['name'] = get_string('required');
        }
        try {
            \local_skillupsflow\definition::parse($data['graph'] ?? '');
        } catch (\moodle_exception $exception) {
            $errors['graph'] = $exception->getMessage();
        }
        return $errors;
    }
}
