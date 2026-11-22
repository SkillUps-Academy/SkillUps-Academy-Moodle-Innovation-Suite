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

/**
 * Course scan policies.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupssuccess\predictor;
use local_skillupssuccess\rules;
use local_skillupssuccess\scope;
use local_skillupssuccess\store;
use local_skillupssuccess\ui;
use local_skillupssuccess\util;

$courseid = required_param('course', PARAM_INT);
require_login($courseid);
$course = scope::course($courseid, 'configure');
$policy = store::policy($courseid);
$url = util::url('policy', ['course' => $courseid]);
if (optional_param('action', '', PARAM_ALPHA) === 'save') {
    ui::require_post();
    $values = [];
    foreach (rules::DEFAULTS as $key => $default) {
        $values[$key] = required_param($key, PARAM_INT);
    }
    store::configure(
        $course,
        $values,
        optional_param('enabled', false, PARAM_BOOL),
        optional_param('study', false, PARAM_BOOL),
        optional_param('usemodel', false, PARAM_BOOL) && predictor::configured() !== null
    );
    redirect($url, util::str('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$values = rules::config(json_decode($policy->definition, true, 8, JSON_THROW_ON_ERROR));
ui::header(context_course::instance($courseid), $url, util::str('policy'));
echo html_writer::tag('p', ui::link('index', ['course' => $courseid], util::str('back')));
echo html_writer::tag('p', s(util::str('policyhelp')));
ui::form('policy', ['course' => $courseid]);
foreach (['enabled', 'study', 'usemodel'] as $field) {
    if ($field === 'usemodel' && !predictor::configured()) {
        continue;
    }
    echo html_writer::div(html_writer::checkbox(
        $field,
        1,
        (bool)$policy->{$field},
        util::str($field),
        ['id' => 'success-' . $field]
    ), 'form-check mb-2');
}
$maximums = ['inactive' => 90, 'overdue' => 50, 'grade' => 100, 'decline' => 100, 'participation' => 30, 'high' => 5];
foreach ($values as $key => $value) {
    echo html_writer::label(util::str('limit_' . $key), 'success-' . $key, true, ['class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'id' => 'success-' . $key, 'name' => $key, 'type' => 'number', 'min' => $key === 'high' ? 1 : 0,
        'max' => $maximums[$key], 'value' => $value, 'required' => 'required', 'class' => 'form-control mb-3',
    ]);
}
ui::submit('save');
echo html_writer::end_tag('form'), $OUTPUT->footer();
