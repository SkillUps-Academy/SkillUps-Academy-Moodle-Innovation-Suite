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

/**
 * Moodle form for authorized category and selected-course submissions.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_form extends \moodleform {
    /**
     * Define the category and explicit batch modes.
     */
    protected function definition(): void {
        $form = $this->_form;
        $form->addElement('hidden', 'category', $this->_customdata['category']);
        $form->setType('category', PARAM_INT);
        if ($this->_customdata['category']) {
            $form->addElement('select', 'mode', scope::text('scope'), [
                'category' => scope::text('mode_category'), 'selected' => scope::text('mode_selected'),
            ]);
        } else {
            $form->addElement('hidden', 'mode', 'selected');
            $form->setType('mode', PARAM_ALPHA);
        }
        $form->addElement('text', 'selected', scope::text('selectedids'), ['size' => 38, 'maxlength' => 700]);
        $form->setType('selected', PARAM_TEXT);
        if ($this->_customdata['category']) {
            $form->hideIf('selected', 'mode', 'neq', 'selected');
        }
        $this->add_action_buttons(false, scope::text('queue'));
    }
}
