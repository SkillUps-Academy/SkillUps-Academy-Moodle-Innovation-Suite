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

namespace local_skillupshelpdesk\form;

use local_skillupshelpdesk\access;

/**
 * Moodle Form for editing taxonomy and knowledge entries.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog_edit extends \moodleform {
    /**
     * Add kind-specific catalog options.
     */
    public function definition(): void {
        $form = $this->_form;
        $kind = $this->_customdata['kind'];
        $form->addElement('text', 'name', access::text('name'), ['maxlength' => 255, 'size' => 50]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'required', null, 'server');
        $form->addRule('name', null, 'maxlength', 255, 'server');
        $form->addElement('advcheckbox', 'active', access::text('active'));
        $form->setDefault('active', 1);
        if ($kind === 'priority') {
            foreach (['responsegoal', 'resolutiongoal'] as $field) {
                $form->addElement('text', $field, access::text($field));
                $form->setType($field, PARAM_INT);
                $form->addRule($field, null, 'required', null, 'server');
            }
        }
        if ($kind === 'status') {
            $form->addElement('advcheckbox', 'initialstate', access::text('initialstate'));
            if (isset($this->_customdata['terminal'])) {
                $form->addElement('static', 'terminaldisplay', access::text('terminal'), $this->_customdata['terminal'] ?
                    get_string('yes') : get_string('no'));
                $form->addElement('hidden', 'terminal', $this->_customdata['terminal']);
                $form->setType('terminal', PARAM_BOOL);
            } else {
                $form->addElement('advcheckbox', 'terminal', access::text('terminal'));
            }
        }
        if (in_array($kind, ['article', 'template'], true)) {
            $form->addElement('textarea', 'body', access::text('content'), ['rows' => 12, 'cols' => 70]);
            $form->setType('body', PARAM_RAW_TRIMMED);
            $form->addRule('body', null, 'required', null, 'server');
            $form->addRule('body', null, 'maxlength', $kind === 'template' ? 8000 : 12000, 'server');
        }
        $this->add_action_buttons();
    }

    /**
     * Reject incompatible SLA and initial status fields before writing.
     *
     * @param array $data Form values
     * @param array $files Submitted upload values
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (isset($data['initialstate']) && $data['initialstate'] && (empty($data['active']) || !empty($data['terminal']))) {
            $errors['initialstate'] = access::text('invalidcatalog');
        }
        if (isset($data['responsegoal']) && $data['responsegoal'] < 1) {
            $errors['responsegoal'] = access::text('invalidcatalog');
        }
        if (
            isset($data['resolutiongoal']) &&
            ($data['resolutiongoal'] < $data['responsegoal'] || $data['resolutiongoal'] > 10080)
        ) {
            $errors['resolutiongoal'] = access::text('invalidcatalog');
        }
        return $errors;
    }
}
