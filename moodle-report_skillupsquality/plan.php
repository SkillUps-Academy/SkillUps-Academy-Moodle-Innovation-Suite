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
 * Create or update plans without changing the audited course.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use report_skillupsquality\form\plan_form;
use report_skillupsquality\scope;
use report_skillupsquality\store;
use report_skillupsquality\ui;

require_login();
$id = optional_param('id', 0, PARAM_INT);
$findingid = optional_param('finding', 0, PARAM_INT);
if ($id) {
    $existing = store::plan($id, 'plan');
    $course = scope::course((int)$existing->courseid, 'plan');
    $rule = $existing;
} else {
    [$run, $rule] = store::finding($findingid, 'plan');
    $course = scope::course((int)$run->courseid, 'plan');
    $existing = $DB->get_record(store::PLAN, ['courseid' => $course->id, 'fingerprint' => $rule->fingerprint]);
}
require_login($course);
$url = ui::url('plan', ['id' => $id, 'finding' => $findingid]);
$PAGE->set_url($url);
$form = new plan_form($url, ['id' => $id, 'finding' => $findingid]);
$form->set_data($existing ? (object)[
    'state' => $existing->state, 'due' => $existing->due, 'notes' => $existing->notes,
] : (object)['state' => 'planned']);
$back = ui::url('plans', ['course' => $course->id]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    store::save_plan($findingid, $data->state, trim($data->notes), (int)$data->due, $id);
    redirect($back, scope::text('plansaved'));
}
ui::header($url, scope::text('editplan'), $course);
echo $OUTPUT->heading(s(ui::rule_name($rule->rulecode)), 2);
echo html_writer::tag('p', s(scope::text('notesprompt')));
$form->display();
echo $OUTPUT->footer();
