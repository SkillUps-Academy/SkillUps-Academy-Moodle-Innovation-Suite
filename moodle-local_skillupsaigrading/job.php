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
 * Teacher review and decision history.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The adapter validates login for the assignment and course.
require_once(__DIR__ . '/../../config.php');

use local_skillupsaigrading\access;
use local_skillupsaigrading\activity\assign;
use local_skillupsaigrading\ui;
use local_skillupsaigrading\workflow;

$id = required_param('id', PARAM_INT);
$jobid = required_param('job', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$activity = new assign($id, 'view', true);
$values = ['id' => $id, 'job' => $jobid];
$job = workflow::read($activity, $jobid);
if ($action !== '') {
    require_sesskey();
    if (!data_submitted()) {
        access::fail('invalidreview');
    }
    workflow::decide(
        $activity,
        $jobid,
        required_param('previous', PARAM_INT),
        $action,
        optional_param('feedback', '', PARAM_RAW)
    );
    redirect(ui::url('job', $values), ui::str('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$current = workflow::current($activity, $job);
$result = $job->result === null ? [] : json_decode($job->result, true, 20, JSON_THROW_ON_ERROR);
$rubric = json_decode($job->rubric, true, 20, JSON_THROW_ON_ERROR);
$subject = core_user::get_user($job->studentid, '*', MUST_EXIST);
$decisions = $DB->get_records(workflow::DECISION, ['jobid' => $jobid], 'id DESC', '*', 0, 100);
$feedback = $result['feedback'] ?? '';
foreach ($decisions as $decision) {
    if ($decision->action !== 'compare') {
        $feedback = $decision->feedback;
        break;
    }
}

ui::header(ui::url('job', $values), $activity->context, $activity->course, ui::str('reviewjob', fullname($subject)));
echo html_writer::tag('p', ui::link('assignment', ['id' => $id], ui::str('backassignment')));
echo $OUTPUT->notification(ui::str('humanonly'), 'info');
echo html_writer::tag('p', s(ui::str('jobstate', (object)[
    'state' => ui::str($job->state), 'review' => ui::str($job->reviewstate),
    'date' => userdate($job->timecreated), 'updated' => userdate($job->timemodified), 'attempt' => $job->attempt + 1,
])));
if (!$current) {
    echo $OUTPUT->notification(ui::str('stalesnapshot'), 'warning');
}
if (in_array($job->state, ['queued', 'working'], true)) {
    echo html_writer::tag('p', ui::link('job', $values, ui::str('refresh')));
} else if ($job->state === 'failed') {
    echo $OUTPUT->notification(ui::str('failedhelp'), 'warning');
}
if ($job->provider !== 'local') {
    echo $OUTPUT->notification(ui::str('aigenerated'), 'warning');
    echo html_writer::tag('p', s(ui::str('provenance', (object)[
        'provider' => ui::str('provider' . $job->provider), 'model' => $job->model,
        'version' => $job->modelversion, 'reported' => $job->reportedmodel ?? '—', 'hash' => $job->rubrichash,
    ])));
} else {
    echo html_writer::tag('p', ui::str('localworksheet'));
}
if (!empty($job->evaluatedat)) {
    echo html_writer::tag('p', s(ui::str('evaluatedat', userdate($job->evaluatedat))));
}
if ($job->suggested !== null) {
    echo html_writer::tag('p', s(ui::str('suggested', format_float($job->suggested, 2))));
}
if ($job->human !== null) {
    echo html_writer::tag('p', s(ui::str('comparison', (object)[
        'human' => format_float($job->human, 2), 'date' => userdate($job->humanat),
        'delta' => format_float(abs($job->suggested - $job->human), 2),
    ])));
}
ui::rubric($rubric, $result['criteria'] ?? []);

if ($current && $job->state === 'ready' && has_capability(access::CAP . ':review', $activity->context)) {
    ui::form('job', $values + ['previous' => $job->latestdecision]);
    ui::textarea('feedback', $feedback, 10000);
    ui::button('edit');
    ui::button('approve', true);
    ui::button('reject');
    if ($job->suggested !== null) {
        ui::button('compare');
    }
    echo html_writer::end_tag('form');
} else if ($feedback !== '') {
    echo $OUTPUT->heading(ui::str('feedback'), 3), html_writer::tag('p', nl2br(s($feedback)));
}
$grader = new moodle_url('/mod/assign/view.php', ['id' => $id, 'action' => 'grader', 'userid' => $subject->id]);
echo html_writer::tag('p', html_writer::link($grader, ui::str('nativegrader')));
echo $OUTPUT->heading(ui::str('decisions'), 3);
$history = [];
foreach ($decisions as $decision) {
    $marker = $decision->userid ? core_user::get_user($decision->userid) : null;
    $line = ui::str($decision->action) . ' · ' . ($marker ? fullname($marker) : ui::str('eraseduser'));
    $line .= ' · ' . userdate($decision->timecreated);
    $history[] = s($line) . html_writer::tag('div', nl2br(s($decision->feedback)));
}
echo $history ? html_writer::alist($history) : html_writer::tag('p', ui::str('nohistory'));
echo $OUTPUT->footer();
