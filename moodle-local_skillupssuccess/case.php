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
 * Identified case and human intervention history.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupssuccess\scope;
use local_skillupssuccess\evidence;
use local_skillupssuccess\store;
use local_skillupssuccess\ui;
use local_skillupssuccess\util;

$courseid = required_param('course', PARAM_INT);
$id = required_param('id', PARAM_INT);
require_login($courseid);
$course = scope::course($courseid);
$context = context_course::instance($courseid);
$case = store::case_for($course, $id);
$params = ['course' => $courseid, 'id' => $id];
if (optional_param('action', '', PARAM_ALPHA) === 'record') {
    ui::require_post();
    store::intervene(
        $course,
        $id,
        required_param('revision', PARAM_INT),
        required_param('operation', PARAM_ALPHA),
        optional_param('notes', '', PARAM_RAW),
        optional_param('tutor', 0, PARAM_INT)
    );
    redirect(util::url('case', $params), util::str('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$person = core_user::get_user($case->userid, '*', MUST_EXIST);
$observation = $DB->get_record(
    store::OBS,
    ['id' => $case->observationid, 'courseid' => $courseid, 'userid' => $case->userid],
    '*',
    MUST_EXIST
);
$data = json_decode($observation->factors, true, 12, JSON_THROW_ON_ERROR);
util::audit($context, 'case', $id);
ui::header($context, util::url('case', $params), util::str('caseheading', fullname($person)));
echo html_writer::tag('p', ui::link('index', ['course' => $courseid], util::str('back')));
echo $OUTPUT->notification(util::str('reviewnotice'), 'info');
$tutor = $case->tutorid ? core_user::get_user($case->tutorid) : null;
echo $OUTPUT->render_from_template(util::C . '/case_summary', [
    'state' => util::str($case->state), 'risk' => util::str($case->risk), 'observed' => userdate($observation->captured),
    'tutor' => $tutor ? fullname($tutor) : util::str('unassigned'),
]);
$factors = [];
foreach ($data['features'] as $key => $value) {
    $trigger = $data['triggers'][$key] ?? null;
    $factors[] = [s(util::str('feature_' . $key)), $value === null ? '—' : s(format_float($value, 1, true, true)),
        $trigger ? s(util::str('matched', (string)$trigger['limit'])) : '—'];
}
echo ui::table('evidence', ['factor', 'value', 'rulesignal'], $factors);
$pending = evidence::pending($course, $id);
if ($pending) {
    $viewerinfo = get_fast_modinfo($course);
    $items = [];
    asort($pending, SORT_NUMERIC);
    foreach (array_slice($pending, 0, 20, true) as $cmid => $deadline) {
        $cm = $viewerinfo->get_cm($cmid);
        $items[] = [html_writer::link(
            new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
            s($cm->name)
        ), s(userdate($deadline))];
    }
    echo ui::table('currentpending', ['assignment', 'effectivedeadline'], $items);
}
$grades = $DB->get_records_select(
    store::OBS,
    'courseid = :course AND userid = :user AND gradepct IS NOT NULL',
    ['course' => $courseid, 'user' => $case->userid],
    'captured DESC, id DESC',
    'id,captured,gradepct',
    0,
    12
);
if ($grades) {
    $gradehistory = [];
    foreach ($grades as $point) {
        $gradehistory[] = [s(userdate($point->captured)), s(format_float($point->gradepct, 1) . '%')];
    }
    echo ui::table('gradehistory', ['observed', 'feature_grade'], $gradehistory);
}
echo $OUTPUT->heading(util::str('statistical'), 3);
if ($data['prediction']) {
    $prediction = $data['prediction'];
    echo html_writer::tag('p', s(util::str('predictiondetail', (object)[
        'model' => $prediction['version'], 'probability' => format_float(100 * $prediction['probability'], 1),
        'threshold' => format_float(100 * $prediction['threshold'], 1),
    ])));
    $contributions = [];
    foreach ($prediction['contributions'] as $key => $value) {
        $contributions[] = [s(util::str('feature_' . $key)), s(format_float($value, 3))];
    }
    echo ui::table('contributions', ['factor', 'value'], $contributions);
} else {
    echo html_writer::tag('p', s(util::str('noprediction')));
}
echo $OUTPUT->heading(util::str('intervention'), 3);
ui::form('case', $params + ['revision' => $case->revision]);
$operations = [];
foreach (['contact', 'plan', 'followup', 'monitor', 'resolved', 'reopen'] as $action) {
    if ($case->state === 'resolved' && !in_array($action, ['reopen'], true)) {
        continue;
    }
    $operations[$action] = util::str($action);
}
$manager = has_capability(util::CAP . ':manage', $context);
if ($manager) {
    $operations['assign'] = util::str('assign');
}
ui::select('operation', $operations, array_key_first($operations));
if ($manager) {
    $options = [0 => util::str('unassigned')];
    foreach (get_enrolled_users($context, util::CAP . ':view', 0, 'u.*', 'u.lastname, u.firstname', 0, 250, true) as $user) {
        if (scope::active($user) && scope::allowed($course, 'view', $user->id)) {
            $options[$user->id] = fullname($user);
        }
    }
    ui::select('tutor', $options, $case->tutorid);
}
echo html_writer::label(util::str('notes'), 'success-notes', true, ['class' => 'form-label']);
echo html_writer::tag('textarea', '', ['id' => 'success-notes', 'name' => 'notes', 'rows' => 5, 'maxlength' => 4000,
    'class' => 'form-control mb-3']);
ui::submit('record');
echo html_writer::end_tag('form');
$rows = [];
foreach ($DB->get_records(store::ENTRY, ['caseid' => $id], 'id DESC', '*', 0, 100) as $entry) {
    $author = $entry->userid ? core_user::get_user($entry->userid) : null;
    $assignment = '';
    if ($entry->action === 'assign') {
        $assignee = $entry->tutorid ? core_user::get_user($entry->tutorid) : null;
        $assignment = html_writer::div(s($assignee ? fullname($assignee) : util::str('unassigned')));
    }
    $rows[] = [s(userdate($entry->timecreated)), s(util::str($entry->action)),
        $author ? s(fullname($author)) : s(util::str('unassigned')), $assignment . nl2br(s($entry->note))];
}
if ($rows) {
    echo ui::table('history', ['observed', 'operation', 'actor', 'notes'], $rows);
}
echo $OUTPUT->footer();
