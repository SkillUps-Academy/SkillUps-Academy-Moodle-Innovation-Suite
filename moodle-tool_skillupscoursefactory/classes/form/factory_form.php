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

use tool_skillupscoursefactory\importer;
use tool_skillupscoursefactory\support;
use tool_skillupscoursefactory\ui;

/**
 * Preview an individual request or a spreadsheet import.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class factory_form extends \moodleform {
    /**
     * Define entry modes and schedule without mutating courses.
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
            $form->addElement('select', 'source', support::text('source'), ui::sources());
            $form->addElement('text', 'sourceoverride', support::text('sourceoverride'), ['size' => 12]);
            $form->setType('sourceoverride', PARAM_INT);
            $form->addElement('select', 'template', support::text('templates'), ui::templates());
            $categories = \core_course_category::make_categories_list('moodle/course:create');
            $form->addElement('select', 'category', support::text('category'), $categories);
            foreach (['fullname', 'shortname', 'idnumber', 'period', 'startdate', 'enddate', 'teachers', 'coordinators'] as $name) {
                $form->addElement('text', $name, support::text($name), ['size' => 36]);
                $form->setType($name, PARAM_TEXT);
            }
            $form->addRule('fullname', null, 'required', null, 'client');
            $form->addRule('shortname', null, 'required', null, 'client');
            $form->addElement('advcheckbox', 'visible', support::text('visible'));
        }
        $form->addElement('date_time_selector', 'runat', support::text('runat'), ['optional' => true]);
        $this->add_action_buttons(true, support::text('preview'));
    }
}
