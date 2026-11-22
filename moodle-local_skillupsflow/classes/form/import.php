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

namespace local_skillupsflow\form;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Import an inert JSON template through Moodle's draft file area.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import extends \moodleform {
    /**
     * Define the import form.
     */
    protected function definition(): void {
        $form = $this->_form;
        $c = 'local_skillupsflow';
        $form->addElement('text', 'name', get_string('name', $c), ['maxlength' => 150]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', get_string('required'), 'required', null, 'client');
        $form->addElement(
            'filepicker',
            'templatefile',
            get_string('templatefile', $c),
            null,
            ['accepted_types' => ['.json'], 'maxbytes' => 65536, 'return_types' => FILE_INTERNAL]
        );
        $this->add_action_buttons(false, get_string('import', $c));
    }

    /**
     * Validate size and format before submission is accepted.
     */
    public function validation($data, $files): array {
        $result = parent::validation($data, $files);
        if (trim($data['name'] ?? '') === '' || \core_text::strlen($data['name']) > 150) {
            $result['name'] = get_string('required');
        }
        try {
            $this->graph();
        } catch (\moodle_exception $exception) {
            $result['templatefile'] = $exception->getMessage();
        }
        return $result;
    }

    /**
     * Use only the submitting account's current draft file.
     */
    public function graph(): array {
        $files = $this->get_draft_files('templatefile');
        $file = is_array($files) && count($files) === 1 ? reset($files) : null;
        if (
            !$file || $file->get_filesize() > 65536 ||
                !str_ends_with(strtolower($file->get_filename()), '.json')
        ) {
            throw new \moodle_exception('invaliddefinition', 'local_skillupsflow');
        }
        return \local_skillupsflow\definition::parse($file->get_content());
    }
}
