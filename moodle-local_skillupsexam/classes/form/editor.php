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

namespace local_skillupsexam\form;

use local_skillupsexam\scope;

/**
 * Render native Moodle forms for exam management.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor extends \moodleform {
    /**
     * Build the form from the authorized field definitions.
     */
    protected function definition(): void {
        $form = $this->_form;
        foreach ($this->_customdata['fields'] as $name => $field) {
            [$type, $options, $required] = $field;
            $label = $type === 'hidden' ? '' : scope::text($name);
            if (in_array($type, ['select', 'autocomplete'], true)) {
                $form->addElement($type, $name, $label, $options);
            } else if ($type === 'advcheckbox') {
                $form->addElement($type, $name, $label);
            } else {
                $form->addElement($type, $name, $label);
            }
            if (in_array($type, ['hidden', 'text', 'textarea'], true)) {
                $form->setType($name, $options);
            }
            if ($required) {
                $form->addRule($name, get_string('required'), 'required', null, 'client');
            }
        }
        $this->add_action_buttons();
    }
}
