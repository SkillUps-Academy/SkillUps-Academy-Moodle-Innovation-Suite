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
 * Form for an institution administrator to change a course's request quota.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursequota extends \moodleform {
    /**
     * Define the course identifier and new limit.
     */
    protected function definition(): void {
        $form = $this->_form;
        $component = 'local_skillupscopilot';
        $form->addElement('text', 'quota_courseid', get_string('courseid', $component), ['inputmode' => 'numeric']);
        $form->setType('quota_courseid', PARAM_INT);
        $form->addElement('text', 'requestlimit', get_string('coursequota', $component), ['inputmode' => 'numeric']);
        $form->setType('requestlimit', PARAM_INT);
        $form->addHelpButton('requestlimit', 'override', $component);
        $this->add_action_buttons(false);
    }

    /**
     * Confirm the ID resolves to a real course and the limit is in range.
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        $id = (int)($data['quota_courseid'] ?? 0);
        if ($id < 1 || $id === SITEID || !$DB->record_exists('course', ['id' => $id])) {
            $errors['quota_courseid'] = get_string('invalidcourse', 'local_skillupscopilot');
        }
        if (!isset($data['requestlimit']) || $data['requestlimit'] < -1 || $data['requestlimit'] > 1000000) {
            $errors['requestlimit'] = get_string('invalidoverride', 'local_skillupscopilot');
        }
        return $errors;
    }
}
