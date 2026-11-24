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
 * Create, publish or revise an exam convocation.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsexam\catalog;
use local_skillupsexam\form\editor;
use local_skillupsexam\plan;
use local_skillupsexam\report;
use local_skillupsexam\scope;
use local_skillupsexam\store;
use local_skillupsexam\ui;

require_login();
$id = optional_param('id', 0, PARAM_INT);
$exam = $id ? store::get(store::EXAM, $id) : null;
$courseid = $exam ? (int)$exam->courseid : optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    $options = [0 => scope::text('choose')];
    foreach (report::courses('plan') as $course) {
        $options[$course->id] = format_string($course->fullname, true, ['escape' => false]);
    }
    if (count($options) === 1) {
        scope::fail();
    }
    scope::page('edit', context_system::instance());
    echo $OUTPUT->header();
    echo $OUTPUT->heading(scope::text('newexam'));
    echo ui::filters(scope::url('edit'), ['courseid' => [0, $options]]);
    echo $OUTPUT->footer();
    exit;
}
$course = get_course($courseid);
$context = context_course::instance($courseid);
if ($exam ? !scope::exam($exam, $course, 'edit') || $exam->status === 'cancelled' : !scope::course($course, 'plan')) {
    scope::fail();
}
require_login($course);
scope::page('edit', $context, compact('courseid', 'id'));
$periods = [0 => scope::text('choose')];
$rooms = $activities = $staff = $supervisors = [0 => scope::text('none')];
$groups = [0 => scope::text('wholecourse')];
foreach (catalog::for_course(store::PERIOD, $course) as $item) {
    $periods[$item->id] = $item->name;
}
foreach (catalog::for_course(store::ROOM, $course) as $item) {
    $rooms[$item->id] = $item->campus . ' / ' . $item->name . ' (' . $item->capacity . ')';
}
foreach (get_fast_modinfo($course)->get_cms() as $cm) {
    if (scope::module($course, (int)$cm->id)) {
        $activities[$cm->id] = get_string('modulename', $cm->modname) . ' / ' .
            format_string($cm->name, true, ['escape' => false, 'context' => $cm->context]);
    }
}
foreach (groups_get_all_groups($courseid) as $group) {
    if (scope::groups($course, null) === null || isset(scope::groups($course, null)[$group->id])) {
        $groups[$group->id] = $group->name;
    }
}
foreach (get_enrolled_users($context, 'moodle/grade:viewall', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true) as $user) {
    if (!$user->suspended && !$user->deleted) {
        $staff[$user->id] = fullname($user);
    }
}
foreach (get_enrolled_users($context, scope::CAP . 'supervise', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true) as $user) {
    if (!$user->suspended && !$user->deleted && scope::course($course, 'supervise', (int)$user->id)) {
        $supervisors[$user->id] = fullname($user);
    }
}
$opts = static fn($keys) => array_combine($keys, array_map([scope::class, 'text'], $keys));
$fields = [
    'revision' => ['hidden', PARAM_INT, false],
    'requestkey' => ['hidden', PARAM_ALPHANUM, false],
    'name' => ['text', PARAM_TEXT, true],
    'periodid' => ['select', $periods, false],
    'starts' => ['date_time_selector', null, false],
    'duration' => ['text', PARAM_INT, true],
    'mode' => ['select', $opts(['onsite', 'online', 'hybrid']), false],
    'roomid' => ['select', $rooms, false],
    'cmid' => ['select', $activities, false],
    'groupid' => ['select', $groups, false],
    'teacherid' => ['autocomplete', $staff, false],
    'supervisor' => ['autocomplete', $supervisors, false],
    'regmode' => ['select', $opts(['active', 'manual']), false],
    'instruction' => ['textarea', PARAM_RAW_TRIMMED, false],
];
if (scope::course($course, 'edit') && (!$exam || $exam->status === 'draft')) {
    $fields['publish'] = ['advcheckbox', null, false];
}
if ($exam) {
    $fields['reason'] = ['textarea', PARAM_RAW_TRIMMED, true];
}
$form = new editor(scope::url('edit', compact('courseid', 'id')), ['fields' => $fields]);
$form->set_data($exam ? (array)$exam + ['duration' => (int)(($exam->ends - $exam->starts) / MINSECS)] :
    ['duration' => 60, 'requestkey' => bin2hex(random_bytes(16)), 'mode' => 'online']);
$back = $id ? scope::url('exam', compact('id')) : scope::url('index', compact('courseid'));
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    if (empty($data->periodid)) {
        scope::fail('periodrequired');
    }
    $data->id = $id;
    $saved = plan::save($course, $data);
    redirect(scope::url('exam', ['id' => $saved]));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(scope::text($id ? 'editexam' : 'newexam'));
echo ui::link($back, scope::text('back'));
$form->display();
echo $OUTPUT->footer();
