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

namespace local_skillupsintegration\form;

use local_skillupsintegration\access;
use local_skillupsintegration\profile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Configure one integration without accepting or displaying secret values.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source extends \moodleform {
    /**
     * Define the integration profile.
     */
    public function definition(): void {
        $form = $this->_form;
        foreach (['id', 'revision'] as $key) {
            $form->addElement('hidden', $key, $this->_customdata[$key]);
            $form->setType($key, PARAM_INT);
        }
        $form->addElement('text', 'name', access::text('name'), ['maxlength' => 100]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'required', null, 'client');
        $types = [];
        foreach (profile::TYPES as $value) {
            $types[$value] = access::text('type_' . $value);
        }
        $form->addElement('select', 'type', access::text('type'), $types);
        if ($this->_customdata['id']) {
            $form->freeze('type');
        }
        $form->addElement('text', 'endpoint', access::text('endpoint'), ['size' => 55]);
        $form->setType('endpoint', PARAM_RAW_TRIMMED);
        $form->hideIf('endpoint', 'type', 'eq', 'inbound');
        $form->addHelpButton('endpoint', 'endpoint', access::C);
        $form->addElement('text', 'secretref', access::text('secretref'), ['autocomplete' => 'off', 'maxlength' => 64]);
        $form->setType('secretref', PARAM_RAW_TRIMMED);
        $form->addHelpButton('secretref', 'secretref', access::C);
        foreach (['enabled', 'users', 'courses', 'webhook'] as $key) {
            $form->addElement('advcheckbox', $key, access::text($key));
        }
        foreach (['categoryid', 'pushuserid'] as $key) {
            $form->addElement('text', $key, access::text($key));
            $form->setType($key, PARAM_INT);
            $form->addHelpButton($key, $key, access::C);
        }
        $form->hideIf('categoryid', 'courses', 'notchecked');
        $policies = [];
        foreach (profile::POLICIES as $value) {
            $policies[$value] = access::text('policy_' . $value);
        }
        $form->addElement('select', 'policy', access::text('policy'), $policies);
        $form->addHelpButton('policy', 'policy', access::C);
        $form->addElement('textarea', 'mapping', access::text('mapping'), ['rows' => 7, 'cols' => 55, 'maxlength' => 8000]);
        $form->setType('mapping', PARAM_RAW);
        $form->addHelpButton('mapping', 'mapping', access::C);
        $this->add_action_buttons(true);
    }
}
