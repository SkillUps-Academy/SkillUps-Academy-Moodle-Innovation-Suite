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

/**
 * Configure the native Attendance Pro activity module.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_skillupsattendance\activity;

/**
 * Edit attendance policy and standard activity settings.
 *
 * @package mod_skillupsattendance
 * @copyright 2026 SkillUps Academy
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_skillupsattendance_mod_form extends moodleform_mod {
    /**
     * Add native module and attendance fields.
     */
    public function definition(): void {
        $form = $this->_form;
        $form->addElement('header', 'general', get_string('general', 'form'));
        $form->addElement('text', 'name', get_string('name'), ['maxlength' => 255, 'size' => 64]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $form->addElement('header', 'attendancepolicy', activity::text('policy'));
        foreach (['minimum' => 75, 'consecutive' => 3, 'absences' => 5] as $key => $default) {
            $form->addElement('text', $key, activity::text($key), ['size' => 6]);
            $form->setType($key, PARAM_INT);
            $form->setDefault($key, $default);
            $form->addRule($key, null, 'required', null, 'client');
        }
        foreach (['alerts', 'grades'] as $key) {
            $form->addElement('advcheckbox', $key, activity::text($key));
            $form->addHelpButton($key, $key, activity::C);
        }
        $form->addElement('textarea', 'reasons', activity::text('reasons'), ['rows' => 4, 'cols' => 60, 'maxlength' => 1500]);
        $form->setType('reasons', PARAM_TEXT);
        $form->addHelpButton('reasons', 'reasons', activity::C);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate academic bounds and configured reasons.
     *
     * @param array $data Form inputs
     * @param array $files File fields
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach (['minimum' => 100, 'consecutive' => 200, 'absences' => 500] as $key => $limit) {
            if ($data[$key] < 0 || $data[$key] > $limit) {
                $errors[$key] = activity::text('invalidpolicy');
            }
        }
        $lines = preg_split('/\R/u', trim($data['reasons'] ?? '')) ?: [];
        if (count($lines) > 10) {
            $errors['reasons'] = activity::text('badreason');
        }
        foreach ($lines as $line) {
            if (\core_text::strlen(trim($line)) > 100) {
                $errors['reasons'] = activity::text('badreason');
            }
        }
        return $errors;
    }
}
