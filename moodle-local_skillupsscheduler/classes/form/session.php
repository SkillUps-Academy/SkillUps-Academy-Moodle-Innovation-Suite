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

use local_skillupsscheduler\catalog;
use local_skillupsscheduler\scope;
use local_skillupsscheduler\store;

/**
 * Collect a session and its recurrence in Moodle Forms.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session extends \moodleform {
    /**
     * Define the fields and controls for this Moodle form.
     */
    protected function definition(): void {
        global $DB;
        $f = $this->_form;
        [$site, $course, $slot, $day] = $this->_customdata;
        $choices = static function (string $table, array $filters) use ($DB, $site): array {
            $options = [0 => scope::text('none')];
            foreach ($DB->get_records($table, ['siteid' => $site->id] + $filters, 'name ASC') as $record) {
                $label = isset($record->shiftname) ? $record->shiftname . ' / ' . $record->name : $record->name;
                $options[$record->id] = format_string($label);
            }
            return $options;
        };
        $hidden = ['id' => $slot->id ?? 0, 'siteid' => $site->id, 'courseid' => $course->id, 'revision' => $slot->revision ?? 0];
        foreach ($hidden as $key => $value) {
            $f->addElement('hidden', $key, $value);
            $f->setType($key, PARAM_INT);
        }
        $f->addElement('hidden', 'series', $slot->series ?? bin2hex(random_bytes(16)));
        $f->setType('series', PARAM_ALPHANUM);
        $f->addElement('text', 'title', scope::text('title'), ['maxlength' => 120]);
        $f->setType('title', PARAM_TEXT);
        $f->addRule('title', null, 'required', null, 'client');
        $f->addElement('select', 'cycleid', scope::text('cycleid'), $choices(store::CYCLE, []));
        $groupchoices = [0 => scope::text('allgroup')];
        foreach (groups_get_all_groups($course->id, 0, 0, 'g.*', false, true) as $group) {
            $groupchoices[$group->id] = format_string($group->name);
        }
        if (!$slot) {
            $f->addElement('select', 'groupid', scope::text('groupid'), $groupchoices);
        }
        $teachers = [0 => scope::text('none')];
        $context = \context_course::instance($course->id);
        foreach (get_enrolled_users($context, scope::CAP . 'teach', 0, 'u.*', '', 0, 0, true) as $user) {
            if (!$user->suspended && !$user->deleted) {
                $teachers[$user->id] = fullname($user);
            }
        }
        $f->addElement('autocomplete', 'teacherid', scope::text('teacherid'), $teachers);
        $f->addElement('select', 'mode', scope::text('mode'), [
            'onsite' => scope::text('onsite'), 'online' => scope::text('online'), 'hybrid' => scope::text('hybrid'),
        ]);
        $f->addElement('select', 'roomid', scope::text('physicalroom'), $choices(store::ROOM, ['kind' => 'physical']));
        $f->addElement('select', 'virtualid', scope::text('virtualroom'), $choices(store::ROOM, ['kind' => 'virtual']));
        $f->addElement('select', 'blockid', scope::text('blockid'), $choices(store::BLOCK, []));
        foreach (['day', 'begin', 'finish'] as $key) {
            $f->addElement('text', $key, scope::text($key), ['placeholder' => $key === 'day' ? 'YYYY-MM-DD' : 'HH:MM']);
            $f->setType($key, PARAM_TEXT);
            $f->addRule($key, null, 'required', null, 'client');
        }
        $f->setDefault('day', $day ?: (new \DateTimeImmutable('now', new \DateTimeZone($site->timezone)))->format('Y-m-d'));
        $f->setDefault('begin', '09:00');
        $f->setDefault('finish', '10:00');
        if (!$slot) {
            $f->addElement('select', 'count', scope::text('count'), array_combine(range(1, 40), range(1, 40)));
            $f->addElement('select', 'interval', scope::text('interval'), [
                1 => scope::text('daily'), 7 => scope::text('weekly'), 14 => scope::text('fortnightly'),
            ]);
            $f->setDefault('interval', 7);
        } else {
            $f->addElement('textarea', 'reason', scope::text('reason'), ['rows' => 3, 'cols' => 45]);
            $f->setType('reason', PARAM_TEXT);
            $f->addRule('reason', null, 'required', null, 'client');
        }
        if ((!$slot || $slot->status === 'draft') && scope::staff($site, 'publish')) {
            $f->addElement('advcheckbox', 'publish', scope::text('publish'), scope::text('publishdetail'));
        }
        $this->add_action_buttons(true, scope::text('savesession'));
    }

    /**
     * Check input before the scheduling service runs.
     *
     * @param array $data Data
     * @param array $files Files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $site = $this->_customdata[0];
        try {
            $start = catalog::local_time($data['day'], $data['begin'], $site->timezone);
            $end = catalog::local_time($data['day'], $data['finish'], $site->timezone);
            if ($end <= $start) {
                $errors['finish'] = scope::text('invalidtime');
            }
        } catch (\moodle_exception $e) {
            $errors['finish'] = $e->getMessage();
        }
        if (!$data['cycleid']) {
            $errors['cycleid'] = scope::text('invalidinput');
        }
        return $errors;
    }
}
