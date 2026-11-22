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

namespace report_skillupsquality\form;

use report_skillupsquality\scope;
use report_skillupsquality\store;

/**
 * Edit a corrective plan through the native forms API.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_form extends \moodleform {
    /**
     * Define state, due date and plain-text action notes.
     */
    protected function definition(): void {
        $form = $this->_form;
        foreach (['id', 'finding'] as $field) {
            $form->addElement('hidden', $field, $this->_customdata[$field]);
            $form->setType($field, PARAM_INT);
        }
        $states = [];
        foreach (store::PLANSTATES as $state) {
            $states[$state] = scope::text('state_' . $state);
        }
        $form->addElement('select', 'state', scope::text('state'), $states);
        $form->addElement('date_selector', 'due', scope::text('duedate'), ['optional' => true]);
        $form->addElement('textarea', 'notes', scope::text('notes'), ['rows' => 5, 'cols' => 58, 'maxlength' => 4000]);
        $form->setType('notes', PARAM_TEXT);
        $this->add_action_buttons(true, scope::text('saveplan'));
    }

    /**
     * Reject action notes beyond the retention and storage contract.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (\core_text::strlen($data['notes']) > 4000) {
            $errors['notes'] = scope::text('invalidplan');
        }
        return $errors;
    }
}
