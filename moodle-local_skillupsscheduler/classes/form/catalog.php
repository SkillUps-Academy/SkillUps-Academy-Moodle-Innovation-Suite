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

namespace local_skillupsscheduler\form;

use local_skillupsscheduler\scope;
use local_skillupsscheduler\store;

/**
 * Edit campus resources with Moodle Forms.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog extends \moodleform {
    /**
     * Define the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        global $DB;
        $f = $this->_form;
        [$type, $siteid, $id] = $this->_customdata;
        foreach (['type' => $type, 'siteid' => $siteid, 'id' => $id] as $key => $value) {
            $f->addElement('hidden', $key, $value);
            $f->setType($key, $key === 'type' ? PARAM_ALPHA : PARAM_INT);
        }
        if ($type !== 'grant') {
            $this->field('name', true, ['maxlength' => 100]);
        }
        switch ($type) {
            case 'site':
                $f->addElement('select', 'categoryid', scope::text('categoryid'), \core_course_category::make_categories_list());
                $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC);
                $f->addElement('autocomplete', 'timezone', scope::text('timezone'), array_combine($zones, $zones));
                $f->setDefault('timezone', 'UTC');
                break;
            case 'cycle':
                $parents = [0 => scope::text('rootperiod')];
                foreach ($DB->get_records(store::CYCLE, ['siteid' => $siteid, 'parentid' => 0], 'name') as $cycle) {
                    if ($cycle->id != $id) {
                        $parents[$cycle->id] = format_string($cycle->name);
                    }
                }
                $f->addElement('select', 'parentid', scope::text('parentid'), $parents);
                $this->field('startday', true, ['placeholder' => 'YYYY-MM-DD']);
                $this->field('endday', true, ['placeholder' => 'YYYY-MM-DD']);
                break;
            case 'room':
                $f->addElement('select', 'kind', scope::text('kind'), [
                    'physical' => scope::text('physicalroom'), 'virtual' => scope::text('virtualroom'),
                ]);
                $f->addElement('text', 'capacity', scope::text('capacity'), ['inputmode' => 'numeric']);
                $f->setType('capacity', PARAM_INT);
                $f->setDefault('capacity', 0);
                $this->field('url', false, ['type' => 'url', 'placeholder' => 'https://']);
                break;
            case 'block':
                $this->field('shiftname', true, ['maxlength' => 100]);
                $days = [];
                for ($weekday = 1; $weekday < 8; $weekday++) {
                    $days[$weekday] = userdate(gmmktime(12, 0, 0, 1, 5 + $weekday, 2025), '%A', 'UTC');
                }
                $f->addElement('select', 'weekday', scope::text('weekday'), $days);
                $this->field('begin', true, ['placeholder' => 'HH:MM']);
                $this->field('finish', true, ['placeholder' => 'HH:MM']);
                break;
            case 'grant':
                $f->addElement('text', 'userid', scope::text('userid'), ['inputmode' => 'numeric']);
                $f->setType('userid', PARAM_INT);
                $f->addRule('userid', null, 'required', null, 'client');
                break;
        }
        $this->add_action_buttons(true);
    }

    /**
     * Field.
     *
     * @param string $name Name
     * @param bool $required Required
     * @param array $attributes Attributes
     */
    private function field(string $name, bool $required, array $attributes): void {
        $this->_form->addElement('text', $name, scope::text($name), $attributes);
        $this->_form->setType($name, PARAM_TEXT);
        if ($required) {
            $this->_form->addRule($name, null, 'required', null, 'client');
        }
    }
}
