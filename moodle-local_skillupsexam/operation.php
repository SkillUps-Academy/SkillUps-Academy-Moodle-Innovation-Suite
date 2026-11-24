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
 * Apply a registration, supervision or cancellation operation.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsexam\form\editor;
use local_skillupsexam\operations;
use local_skillupsexam\plan;
use local_skillupsexam\roster;
use local_skillupsexam\scope;
use local_skillupsexam\store;
use local_skillupsexam\ui;

require_login();
$id = required_param('id', PARAM_INT);
$op = required_param('op', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$exam = store::get(store::EXAM, $id);
$course = get_course($exam->courseid);
$supervise = in_array($op, ['mark', 'incident'], true);
if (
    !in_array($op, ['mark', 'incident', 'register', 'unregister', 'cancel'], true) ||
    !scope::exam($exam, $course, $supervise ? 'supervise' : 'edit') ||
    ($userid && !roster::eligible($exam, $userid, true, $supervise))
) {
    scope::fail();
}
if (
    ($supervise && $exam->status !== 'scheduled') || $exam->status === 'cancelled' ||
    (in_array($op, ['register', 'unregister'], true) && $exam->regmode !== 'manual')
) {
    scope::fail('invalidinput');
}
require_login($course);
scope::page('operation', context_course::instance($course->id), compact('id', 'op', 'userid'));
$defaults = [];
$fields = [];
if ($op === 'register') {
    $fields['memberid'] = ['text', PARAM_INT, true];
} else if ($op === 'mark' || $op === 'incident') {
    $values = $op === 'mark' ? operations::MARKS : operations::LEVELS;
    $fields['value'] = ['select', array_combine($values, array_map([scope::class, 'text'], $values)), false];
    $fields['note'] = ['textarea', PARAM_RAW_TRIMMED, $op === 'incident'];
    if ($op === 'mark' && $userid) {
        $existing = $DB->get_record(store::MARK, ['examid' => $id, 'userid' => $userid]);
        $defaults = $existing ? ['value' => $existing->status, 'note' => $existing->note] : [];
    }
} else if ($op === 'cancel') {
    $fields['revision'] = ['hidden', PARAM_INT, false];
    $fields['reason'] = ['textarea', PARAM_RAW_TRIMMED, true];
    $defaults = ['revision' => $exam->revision];
} else {
    $fields['confirmremove'] = ['advcheckbox', null, true];
}
$back = scope::url('exam', ['id' => $id]);
$form = new editor(scope::url('operation', compact('id', 'op', 'userid')), ['fields' => $fields]);
$form->set_data($defaults);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    if ($op === 'cancel') {
        plan::cancel($id, (int)$data->revision, $data->reason);
    } else {
        if ($op === 'unregister' && empty($data->confirmremove)) {
            scope::fail('invalidinput');
        }
        operations::apply($id, $op, (int)($data->memberid ?? $userid), $data->value ?? '', $data->note ?? '');
    }
    redirect($back);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(scope::text($op));
echo ui::link($back, scope::text('back'));
if ($userid) {
    echo html_writer::tag('p', s(fullname(\core_user::get_user($userid, '*', MUST_EXIST))));
}
if ($op === 'register') {
    echo html_writer::tag('p', scope::text('registerhelp'));
    $after = optional_param('after', 0, PARAM_INT);
    $rows = [];
    $last = $after;
    foreach (roster::users($exam, true, false, $after) as $candidate) {
        $rows[] = [s(fullname($candidate)), (int)$candidate->id];
        $last = (int)$candidate->id;
        if (count($rows) >= 50) {
            break;
        }
    }
    echo ui::table([scope::text('name'), scope::text('memberid')], $rows);
    if (count($rows) >= 50) {
        echo ui::link(scope::url('operation', compact('id', 'op') + ['after' => $last]), scope::text('next'));
    }
}
$form->display();
echo $OUTPUT->footer();
