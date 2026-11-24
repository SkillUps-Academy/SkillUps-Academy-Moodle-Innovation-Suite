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

namespace local_skillupsprivacy\form;

use local_skillupsprivacy\access;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Assign a responsible officer and deadline.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class routing extends \moodleform {
    /**
     * Define the form fields.
     */
    public function definition(): void {
        $form = $this->_form;
        $form->addElement('hidden', 'revision', $this->_customdata['revision']);
        $form->setType('revision', PARAM_INT);
        $form->addElement('text', 'ownerid', access::text('owner'));
        $form->setType('ownerid', PARAM_INT);
        $form->addRule('ownerid', null, 'required', null, 'client');
        $form->addElement('date_time_selector', 'due', access::text('deadline'));
        $this->add_action_buttons(false, access::text('updaterouting'));
    }
}
