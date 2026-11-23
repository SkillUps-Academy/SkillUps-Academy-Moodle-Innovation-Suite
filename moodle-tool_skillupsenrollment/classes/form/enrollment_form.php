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

namespace tool_skillupsenrollment\form;

use tool_skillupsenrollment\importer;
use tool_skillupsenrollment\support;
use tool_skillupsenrollment\validator;

/**
 * Prepare a single intent or a spreadsheet simulation.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrollment_form extends \moodleform {
    /**
     * Render the specified input path.
     */
    protected function definition(): void {
        $form = $this->_form;
        if ($this->_customdata['import']) {
            $form->addElement(
                'filepicker',
                'sheet',
                support::text('sheet'),
                null,
                ['accepted_types' => ['.csv', '.xlsx'], 'maxbytes' => importer::MAXBYTES, 'maxfiles' => 1]
            );
            $form->addRule('sheet', null, 'required', null, 'client');
            $sizes = support::sizes();
            $form->addElement('select', 'batchsize', support::text('batchsize'), array_combine($sizes, $sizes));
            $form->setDefault('batchsize', end($sizes));
        } else {
            $options = [];
            foreach (validator::ACTIONS as $mode) {
                $options[$mode] = support::text('action_' . $mode);
            }
            $form->addElement('select', 'action', support::text('action'), $options);
            foreach (['userid', 'username', 'courseid', 'shortname', 'instanceid', 'role', 'groupid'] as $field) {
                $form->addElement('text', $field, support::text($field), ['size' => 24]);
                $form->setType($field, PARAM_TEXT);
            }
            $form->addElement('select', 'method', support::text('method'), ['' => support::text('defaultmethod'),
                'manual' => support::text('manual'), 'self' => support::text('self')]);
        }
        $form->addElement('date_time_selector', 'runat', support::text('runat'), ['optional' => true]);
        $this->add_action_buttons(true, support::text('preview'));
    }
}
