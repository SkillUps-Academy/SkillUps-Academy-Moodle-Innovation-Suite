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

namespace mod_skillupsattendance\form;

use mod_skillupsattendance\activity;
use mod_skillupsattendance\session as model;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Configure an individual session or a bounded recurring series.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session extends \moodleform {
    /**
     * Define schedule settings using Moodle's time selectors.
     */
    public function definition(): void {
        $form = $this->_form;
        $activity = $this->_customdata['activity'];
        foreach (['id', 'session', 'revision'] as $field) {
            $form->addElement('hidden', $field, $this->_customdata[$field]);
            $form->setType($field, PARAM_INT);
        }
        $form->addElement('text', 'name', activity::text('sessionname'), ['maxlength' => 100]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'required', null, 'client');
        $form->addElement('select', 'groupid', activity::text('group'), $activity->groups());
        $form->addElement('text', 'teacherid', activity::text('teacherid'), ['size' => 8]);
        $form->setType('teacherid', PARAM_INT);
        $form->addHelpButton('teacherid', 'teacherid', activity::C);
        foreach (['modality' => model::MODALITIES, 'method' => model::METHODS] as $key => $choices) {
            $options = [];
            foreach ($choices as $value) {
                $options[$value] = activity::text($key . '_' . $value);
            }
            $form->addElement('select', $key, activity::text($key), $options);
        }
        $attributes = ['maxlength' => 32, 'autocomplete' => 'new-password'];
        $form->addElement('passwordunmask', 'newpin', activity::text('newpin'), $attributes);
        $form->setType('newpin', PARAM_RAW_TRIMMED);
        $form->hideIf('newpin', 'method', 'neq', 'code');
        $form->addHelpButton('newpin', 'newpin', activity::C);
        $form->addElement('date_time_selector', 'starts', activity::text('starts'));
        $form->addElement('date_time_selector', 'ends', activity::text('ends'));
        foreach (['early' => 0, 'grace' => 0, 'late' => 5] as $key => $default) {
            $form->addElement('text', $key, activity::text($key), ['size' => 4]);
            $form->setType($key, PARAM_INT);
            $form->setDefault($key, $default);
        }
        foreach (['checkout', 'extra'] as $key) {
            $form->addElement('advcheckbox', $key, activity::text($key));
        }
        if (!$this->_customdata['session']) {
            $form->addElement('select', 'frequency', activity::text('frequency'), [
                'week' => activity::text('frequency_week'), 'day' => activity::text('frequency_day'),
            ]);
            $form->addElement('select', 'occurrences', activity::text('occurrences'), array_combine(range(1, 26), range(1, 26)));
            $form->setDefault('occurrences', 1);
        }
        $this->add_action_buttons(true, activity::text('savesession'));
    }
}
