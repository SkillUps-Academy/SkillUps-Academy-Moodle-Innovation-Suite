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

namespace tool_skillupscoursefactory\form;

use tool_skillupscoursefactory\support;
use tool_skillupscoursefactory\ui;

/**
 * Create institutional source-course references.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_form extends \moodleform {
    /**
     * Define a named source reference.
     */
    protected function definition(): void {
        $form = $this->_form;
        $form->addElement('text', 'name', support::text('templatename'), ['maxlength' => 160]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'required', null, 'client');
        $sources = ui::sources();
        unset($sources[0]);
        $form->addElement('select', 'source', support::text('source'), $sources);
        $this->add_action_buttons(false, support::text('savetemplate'));
    }
}
