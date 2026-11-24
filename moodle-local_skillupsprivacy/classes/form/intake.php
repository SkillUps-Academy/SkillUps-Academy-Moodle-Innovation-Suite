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
use local_skillupsprivacy\workflow;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Register a standalone or linked case.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class intake extends \moodleform {
    /**
     * Define the form fields.
     */
    public function definition(): void {
        $form = $this->_form;
        $form->addElement('hidden', 'nativeid', $this->_customdata['nativeid']);
        $form->setType('nativeid', PARAM_INT);
        $form->addElement('text', 'userid', access::text('subject'));
        $form->setType('userid', PARAM_INT);
        $form->addRule('userid', null, 'required', null, 'client');
        $kinds = [];
        foreach (workflow::KINDS as $kind) {
            $kinds[$kind] = access::text('kind_' . $kind);
        }
        $form->addElement('select', 'kind', access::text('kind'), $kinds);
        $form->addElement('text', 'ownerid', access::text('owner'));
        $form->setType('ownerid', PARAM_INT);
        $form->addElement('text', 'contextid', access::text('reviewcontext'));
        $form->setType('contextid', PARAM_INT);
        $form->addElement('date_time_selector', 'due', access::text('deadline'));
        $this->add_action_buttons(true, access::text('register'));
    }
}
