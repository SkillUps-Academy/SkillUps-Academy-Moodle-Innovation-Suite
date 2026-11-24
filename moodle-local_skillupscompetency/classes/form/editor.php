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

namespace local_skillupscompetency\form;

use local_skillupscompetency\access;

/**
 * Reuse Moodle Forms for catalog annotations, matrix settings and reviews.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor extends \moodleform {
    /**
     * Define fields and action buttons for the selected operation.
     */
    protected function definition(): void {
        [$operation, $hidden, $fields, $button] = $this->_customdata;
        $f = $this->_form;
        foreach (['op' => $operation] + $hidden as $name => $value) {
            $f->addElement('hidden', $name, $value);
            $f->setType($name, is_int($value) ? PARAM_INT : PARAM_ALPHANUMEXT);
        }
        foreach ($fields as $name => [$type, $detail]) {
            $label = access::text($name);
            if ($type === 'textarea') {
                $f->addElement('textarea', $name, $label, ['rows' => 4, 'cols' => 55]);
                $f->setType($name, PARAM_TEXT);
            } else if ($type === 'filepicker') {
                $f->addElement('filepicker', $name, $label, null, $detail);
            } else if ($type === 'select' || $type === 'autocomplete') {
                $f->addElement($type, $name, $label, $detail);
            } else if ($type === 'advcheckbox') {
                $f->addElement($type, $name, $label);
            } else {
                $f->addElement('text', $name, $label, $detail);
                $f->setType($name, PARAM_TEXT);
            }
        }
        $this->add_action_buttons(true, access::text($button));
    }
}
