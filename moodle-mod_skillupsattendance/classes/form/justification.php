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
use mod_skillupsattendance\justification as model;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Collect a permitted reason and private draft evidence.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class justification extends \moodleform {
    /**
     * Define permitted reason, text and files.
     */
    public function definition(): void {
        $form = $this->_form;
        $activity = $this->_customdata['activity'];
        foreach (['id', 'session'] as $field) {
            $form->addElement('hidden', $field, $this->_customdata[$field]);
            $form->setType($field, PARAM_INT);
        }
        $reasons = model::reasons($activity);
        $form->addElement('select', 'reason', activity::text('reason'), array_combine($reasons, $reasons));
        $form->addElement('textarea', 'statement', activity::text('statement'), ['rows' => 4, 'cols' => 60, 'maxlength' => 1000]);
        $form->setType('statement', PARAM_TEXT);
        $form->addElement('filemanager', 'evidence', activity::text('evidence'), null, model::file_options($activity));
        $this->add_action_buttons(true, activity::text('submitjust'));
    }
}
