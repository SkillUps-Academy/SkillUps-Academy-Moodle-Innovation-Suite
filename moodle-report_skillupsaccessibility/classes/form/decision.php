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

namespace report_skillupsaccessibility\form;

use report_skillupsaccessibility\review;
use report_skillupsaccessibility\scope;

/**
 * Collect remediation status, responsibility and private evidence in Moodle Forms.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decision extends \moodleform {
    /**
     * Define the review form.
     */
    protected function definition(): void {
        [$id, $assignable] = $this->_customdata;
        $form = $this->_form;
        $form->addElement('hidden', 'id', $id);
        $form->setType('id', PARAM_INT);
        $states = [];
        foreach (review::STATES as $status) {
            $states[$status] = scope::text($status);
        }
        $form->addElement('select', 'state', scope::text('state'), $states);
        $form->addElement('autocomplete', 'assignee', scope::text('assignee'), $assignable);
        $form->addElement('textarea', 'notes', scope::text('notes'), ['rows' => 4, 'cols' => 55]);
        $form->setType('notes', PARAM_TEXT);
        $form->addRule('notes', null, 'required', null, 'client');
        $form->addElement('filemanager', 'evidence', scope::text('evidence'), null, review::options());
        $this->add_action_buttons(true, scope::text('savereview'));
    }
}
