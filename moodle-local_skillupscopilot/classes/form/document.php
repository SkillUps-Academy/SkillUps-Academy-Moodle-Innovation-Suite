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
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * A course document with optional restrictions inherited from a group or activity.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class document extends \moodleform {
    /**
     * Define file, title and access scope controls.
     */
    protected function definition(): void {
        $form = $this->_form;
        $component = 'local_skillupscopilot';
        $form->addElement('text', 'title', get_string('sourcetitle', $component), ['maxlength' => 200]);
        $form->setType('title', PARAM_TEXT);
        $form->addRule('title', get_string('required'), 'required', null, 'client');
        $form->addElement('select', 'cmid', get_string('activityscope', $component), $this->_customdata['modules']);
        $form->addElement('select', 'groupid', get_string('groupscope', $component), $this->_customdata['groups']);
        $form->addElement(
            'filepicker',
            'document',
            get_string('document', $component),
            null,
            ['accepted_types' => ['.txt', '.md'], 'maxbytes' => \local_skillupscopilot\sources::MAX_BYTES,
            'return_types' => FILE_INTERNAL]
        );
        $form->addElement(
            'advcheckbox',
            'confirmed',
            get_string('sourceconfirm', $component),
            get_string('sourceconfirm_desc', $component)
        );
        $this->add_action_buttons(false, get_string('addsource', $component));
    }

    /**
     * Validate the selection and current draft file.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $title = trim($data['title'] ?? '');
        if ($title === '' || \core_text::strlen($title) > 200) {
            $errors['title'] = get_string('invalidsource', 'local_skillupscopilot');
        }
        if (!$this->draft()) {
            $errors['document'] = get_string('invalidsource', 'local_skillupscopilot');
        }
        if (empty($data['confirmed'])) {
            $errors['confirmed'] = get_string('consentrequired', 'local_skillupscopilot');
        }
        return $errors;
    }

    /**
     * Resolve exactly one file from the current user's Moodle draft area.
     */
    public function draft(): ?\stored_file {
        $files = $this->get_draft_files('document');
        return is_array($files) && count($files) === 1 ? reset($files) : null;
    }
}
