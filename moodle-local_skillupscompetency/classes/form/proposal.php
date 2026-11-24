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
use local_skillupscompetency\assessment;
use local_skillupscompetency\rubric;

/**
 * Collect a direct rating or native rubric criterion selections and private evidence.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class proposal extends \moodleform {
    /**
     * Add the fields for one competency proposal.
     */
    protected function definition(): void {
        [$ids, $levels, $definition] = $this->_customdata;
        $f = $this->_form;
        foreach ($ids as $name => $value) {
            $f->addElement('hidden', $name, $value);
            $f->setType($name, PARAM_INT);
        }
        $f->addElement('hidden', 'requestkey', bin2hex(random_bytes(16)));
        $f->setType('requestkey', PARAM_ALPHANUM);
        if ($definition) {
            $f->addElement('hidden', 'rubrichash', rubric::signature($definition));
            $f->setType('rubrichash', PARAM_ALPHANUM);
            foreach ($definition['criteria'] as $id => $criterion) {
                $options = [0 => access::text('choose')];
                foreach ($criterion['levels'] as $levelid => $level) {
                    $options[$levelid] = strip_tags($level['definition']) . ' (' . $level['score'] . ')';
                }
                $f->addElement('select', "criteria[$id]", strip_tags($criterion['description']), $options);
            }
        } else {
            $f->addElement('select', 'levelvalue', access::text('levelvalue'), $levels);
        }
        $f->addElement('textarea', 'observations', access::text('observations'), ['rows' => 5, 'cols' => 60]);
        $f->setType('observations', PARAM_TEXT);
        $f->addRule('observations', null, 'required', null, 'client');
        $f->addElement('filemanager', 'attachments', access::text('attachments'), null, assessment::options());
        $this->add_action_buttons(true, access::text('submitreview'));
    }
}
