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

namespace local_skillupsrepository\form;

use local_skillupsrepository\ui;

/**
 * Build native Moodle forms for repository submissions and moderation.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fields extends \moodleform {
    /**
     * Create authorized and localized controls.
     */
    protected function definition(): void {
        $form = $this->_form;
        foreach ($this->_customdata['fields'] as $key => [$type, $options, $required]) {
            $label = $type === 'hidden' ? '' : ui::t($key);
            if ($type === 'filemanager') {
                $form->addElement('filemanager', $key, $label, null, $options);
            } else if (in_array($type, ['select', 'autocomplete'], true)) {
                $form->addElement($type, $key, $label, $options);
            } else {
                $form->addElement($type, $key, $label);
            }
            if (in_array($type, ['text', 'textarea', 'hidden'], true)) {
                $form->setType($key, $options);
            }
            if ($required) {
                $form->addRule($key, get_string('required'), 'required', null, 'client');
            }
        }
        $this->add_action_buttons();
    }
}
