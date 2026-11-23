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

namespace local_skillupsgamification\form;

use local_skillupsgamification\access;
use local_skillupsgamification\engine;
use local_skillupsgamification\goals;
use local_skillupsgamification\team;
use local_skillupsgamification\ui;

/**
 * Moodle Forms for course settings, award rules and achievement catalog.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor extends \moodleform {
    /**
     * Define the requested edit form and its typed fields.
     */
    public function definition(): void {
        $form = $this->_form;
        $section = $this->_customdata['section'];
        $courseid = $this->_customdata['courseid'];
        if ($section === 'settings') {
            foreach (['enabled', 'rankingenabled', 'pathenabled'] as $field) {
                $form->addElement('advcheckbox', $field, access::text($field));
            }
            $this->integer('xplevel', 100);
            $this->integer('dailycap');
            $choices = [0 => access::text('nogrouping')];
            foreach (team::groupings($courseid) as $grouping) {
                $choices[$grouping->id] = $grouping->name;
            }
            $form->addElement('select', 'groupingid', access::text('groupingid'), $choices);
            $form->setType('groupingid', PARAM_INT);
        } else if ($section === 'exclusions') {
            $this->integer('userid');
            $form->addElement('advcheckbox', 'excluded', access::text('exclude'));
        } else {
            $form->addElement('text', 'name', access::text('name'), ['maxlength' => 180, 'size' => 52]);
            $form->setType('name', PARAM_TEXT);
            $form->addRule('name', null, 'required', null, 'server');
            $form->addElement('advcheckbox', 'enabled', access::text('enabled'));
            $form->setDefault('enabled', 1);
            if ($section === 'rules') {
                $this->choice('kind', 'source_', engine::KINDS);
                $this->integer('sourceid');
                $form->addHelpButton('sourceid', 'sourceid', access::C);
                foreach (['points' => 10, 'xp' => 10, 'maxdaily' => 0, 'maxawards' => 0] as $field => $default) {
                    $this->integer($field, $default);
                }
            } else {
                $this->choice('kind', 'goal_', goals::KINDS);
                $this->choice('scope', 'scope_', goals::SCOPES);
                $this->choice('metric', 'metric_', goals::METRICS);
                $this->choice('icon', 'icon_', goals::ICONS);
                $form->addElement('textarea', 'description', access::text('description'), ['rows' => 5, 'cols' => 60]);
                $form->setType('description', PARAM_RAW_TRIMMED);
                foreach (['target' => 1, 'cost' => 10, 'stock' => 0] as $field => $default) {
                    $this->integer($field, $default);
                }
            }
            foreach (['starts', 'ends'] as $field) {
                $form->addElement('date_time_selector', $field, access::text($field), ['optional' => true]);
            }
        }
        $this->add_action_buttons(in_array($section, ['rules', 'goals'], true));
    }

    /**
     * Add an integer field with a known default.
     *
     * @param string $field Field
     * @param int $default Default
     */
    private function integer(string $field, int $default = 0): void {
        $this->_form->addElement('text', $field, access::text($field), ['inputmode' => 'numeric']);
        $this->_form->setType($field, PARAM_INT);
        $this->_form->setDefault($field, $default);
        $this->_form->addRule($field, null, 'required', null, 'server');
    }

    /**
     * Add a fixed set of localized option keys.
     *
     * @param string $field Form field
     * @param string $prefix Language key prefix
     * @param array $values Valid choices
     */
    private function choice(string $field, string $prefix, array $values): void {
        $this->_form->addElement('select', $field, access::text($field), ui::options($prefix, $values));
        $this->_form->setType($field, PARAM_ALPHA);
    }

    /**
     * Validate complete evaluation windows before the service checks permissions.
     *
     * @param array $data Submitted fields
     * @param array $files Submitted files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['ends']) && $data['ends'] <= ($data['starts'] ?? 0)) {
            $errors['ends'] = access::text('invalidconfiguration');
        }
        $timed = $this->_customdata['section'] === 'goals' && $data['kind'] === 'event';
        if ($timed && (empty($data['starts']) || empty($data['ends']))) {
            $errors['starts'] = access::text('eventwindowrequired');
        }
        return $errors;
    }
}
