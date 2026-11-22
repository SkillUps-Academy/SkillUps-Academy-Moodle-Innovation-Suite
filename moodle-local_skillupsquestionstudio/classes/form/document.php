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
require_once($CFG->dirroot . '/repository/lib.php');

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\extraction\document as parser;

/**
 * Source upload through the current account's Moodle draft area.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class document extends \moodleform {
    /**
     * Build the secure file picker.
     */
    protected function definition(): void {
        $this->_form->addElement('filepicker', 'document', get_string('sourcefile', access::C), null, [
            'accepted_types' => array_map(static fn($extension) => '.' . $extension, parser::EXTENSIONS),
            'maxbytes' => parser::MAX_BYTES, 'return_types' => FILE_INTERNAL,
        ]);
        $this->add_action_buttons(false, get_string('upload', access::C));
    }

    /**
     * Validate the source before Moodle attempts to queue it.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            parser::extension($this->draft());
        } catch (\moodle_exception $exception) {
            $errors['document'] = get_string('invaliddocument', access::C);
        }
        return $errors;
    }

    /**
     * Resolve exactly one File API draft record.
     */
    public function draft(): \stored_file {
        $files = $this->get_draft_files('document');
        if (!is_array($files) || count($files) !== 1) {
            access::fail('invaliddocument');
        }
        return reset($files);
    }
}
