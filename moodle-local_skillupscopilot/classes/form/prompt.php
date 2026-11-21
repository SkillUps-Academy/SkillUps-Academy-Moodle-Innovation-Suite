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

namespace local_skillupscopilot\form;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Accessible chat form with an explicit external-provider acknowledgement.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class prompt extends \moodleform {
    /**
     * Define the prompt and generation choices.
     */
    protected function definition(): void {
        $form = $this->_form;
        $options = $this->_customdata;
        $form->addElement('hidden', 'requestkey', $options['requestkey']);
        $form->setType('requestkey', PARAM_ALPHANUM);
        $form->addElement('select', 'mode', get_string('mode', 'local_skillupscopilot'), $options['modes']);
        $form->addElement('select', 'provider', get_string('provider', 'local_skillupscopilot'), $options['providers']);
        $form->addElement(
            'textarea',
            'prompt',
            get_string('prompt', 'local_skillupscopilot'),
            ['rows' => 4, 'maxlength' => 4000]
        );
        $form->setType('prompt', PARAM_RAW_TRIMMED);
        $form->addRule('prompt', get_string('required'), 'required', null, 'client');
        if ($options['external']) {
            $form->addElement(
                'advcheckbox',
                'consent',
                get_string('consent', 'local_skillupscopilot'),
                get_string('consent_desc', 'local_skillupscopilot')
            );
        }
        $this->add_action_buttons(false, get_string('send', 'local_skillupscopilot'));
    }

    /**
     * Check that a submission still matches the server's choices.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (trim($data['prompt'] ?? '') === '' || \core_text::strlen($data['prompt']) > 4000) {
            $errors['prompt'] = get_string('invalidprompt', 'local_skillupscopilot');
        }
        if (!isset($this->_customdata['modes'][$data['mode'] ?? ''])) {
            $errors['mode'] = get_string('accessdenied', 'local_skillupscopilot');
        }
        $provider = $data['provider'] ?? '';
        $config = \local_skillupscopilot\ai\registry::config($provider);
        if (!$config) {
            $errors['provider'] = get_string('providerunavailable', 'local_skillupscopilot');
        } else if ($config['external'] && empty($data['consent'])) {
            $errors['consent'] = get_string('consentrequired', 'local_skillupscopilot');
        }
        return $errors;
    }
}
