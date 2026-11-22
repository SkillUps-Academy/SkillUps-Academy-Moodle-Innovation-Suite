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

namespace local_skillupsquestionstudio\form;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\question;
use local_skillupsquestionstudio\view;

/**
 * Configure a bounded batch of draft questions.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generation extends \moodleform {
    /**
     * Define a local provider and an optional authorized Moodle AI provider.
     */
    protected function definition(): void {
        $form = $this->_form;
        $type = 'local';
        $providers = ['local' => get_string('provider_local', access::C)];
        if ($this->_customdata['ai']) {
            $providers['ai'] = get_string('provider_ai', access::C);
        }
        $form->addElement('select', 'sourceid', get_string('source', access::C), $this->_customdata['sources']);
        $form->addElement('select', 'provider', get_string('provider', access::C), $providers);
        $form->setDefault('provider', $type);
        if (isset($providers['ai'])) {
            $form->addElement('advcheckbox', 'consent', get_string('consent', access::C), get_string('consentdesc', access::C));
            $form->hideIf('consent', 'provider', 'neq', 'ai');
        }
        $form->addElement('select', 'kind', get_string('kind', access::C), view::choices('type_', question::TYPES));
        $form->addElement('select', 'bloom', get_string('bloom', access::C), view::choices('bloom_', question::BLOOM));
        $form->addElement('select', 'level', get_string('level', access::C), view::choices('level_', question::LEVELS));
        $form->setDefault('level', 'medium');
        $form->addElement('text', 'unit', get_string('unit', access::C), ['maxlength' => 150]);
        $form->setType('unit', PARAM_TEXT);
        $form->addElement('select', 'competency', get_string('competency', access::C), $this->_customdata['competencies']);
        $form->addElement('select', 'count', get_string('count', access::C), array_combine(range(1, 10), range(1, 10)));
        $form->setDefault('count', 5);
        $this->add_action_buttons(false, get_string('generate', access::C));
    }
}
