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

namespace local_skillupsscheduler\form;

use local_skillupsscheduler\scope;

/**
 * Require an explicit cancellation reason.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cancel extends \moodleform {
    /**
     * Define the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        $f = $this->_form;
        foreach (['id', 'revision', 'cancelconfirm'] as $key) {
            $f->addElement('hidden', $key, $this->_customdata[$key]);
            $f->setType($key, PARAM_INT);
        }
        $f->addElement('textarea', 'reason', scope::text('reason'), ['rows' => 3, 'cols' => 45]);
        $f->setType('reason', PARAM_TEXT);
        $f->addRule('reason', null, 'required', null, 'client');
        $this->add_action_buttons(true, scope::text('confirmcancel'));
    }
}
