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
use local_skillupshelpdesk\attachments;
use local_skillupshelpdesk\catalog;
use local_skillupshelpdesk\ui;

/**
 * Moodle Form for a new ticket or reply.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conversation extends \moodleform {
    /**
     * Define the intake and reply fields with a protected draft area.
     */
    public function definition(): void {
        $form = $this->_form;
        $new = !empty($this->_customdata['new']);
        if ($new) {
            $form->addElement('text', 'title', access::text('title'), ['maxlength' => 180, 'size' => 55]);
            $form->setType('title', PARAM_TEXT);
            $form->addRule('title', null, 'required', null, 'server');
            $form->addRule('title', null, 'maxlength', 180, 'server');
            foreach (['category', 'campus', 'priority', 'severity'] as $kind) {
                $field = $kind . 'id';
                $form->addElement('select', $field, access::text('kind_' . $kind), catalog::options($kind));
                $form->setType($field, PARAM_INT);
            }
            $form->addElement('select', 'courseid', access::text('kind_course'), ui::courses());
            $form->setType('courseid', PARAM_INT);
        } else if (!empty($this->_customdata['staff'])) {
            $form->addElement('advcheckbox', 'internal', access::text('internal'));
            $form->setType('internal', PARAM_BOOL);
        }
        $form->addElement('textarea', 'body', access::text($new ? 'description' : 'comment'), ['rows' => 8, 'cols' => 65]);
        $form->setType('body', PARAM_RAW_TRIMMED);
        $form->addRule('body', null, 'required', null, 'server');
        $form->addRule('body', null, 'maxlength', 8000, 'server');
        $form->addElement('filemanager', 'attachments', access::text('attachments'), null, attachments::options());
        $form->addHelpButton('attachments', 'attachments', access::COMPONENT);
        $this->add_action_buttons($new, access::text($new ? 'openticket' : 'sendcomment'));
    }

    /**
     * Validate actual attachment types and sizes before saving.
     *
     * @param array $data Form values
     * @param array $files Uploaded files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!attachments::valid((int)($data['attachments'] ?? 0))) {
            $errors['attachments'] = access::text('invalidattachment');
        }
        return $errors;
    }
}
