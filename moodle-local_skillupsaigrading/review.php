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
 * Prepare and authorize bounded submission evidence.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The adapter validates login for the assignment and course.
require_once(__DIR__ . '/../../config.php');

use local_skillupsaigrading\access;
use local_skillupsaigrading\activity\assign;
use local_skillupsaigrading\ai\registry;
use local_skillupsaigrading\evaluation;
use local_skillupsaigrading\ui;
use local_skillupsaigrading\workflow;

$id = required_param('id', PARAM_INT);
$studentid = required_param('student', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$historypage = max(0, optional_param('historypage', 0, PARAM_INT));
$activity = new assign($id, $action !== '' ? 'review' : 'view', true);
if (!$activity->visible($studentid)) {
    access::fail('accessdenied');
}
$student = core_user::get_user($studentid, '*', MUST_EXIST);
$values = ['id' => $id, 'student' => $studentid];
$canreview = has_capability(access::CAP . ':review', $activity->context);
$rubric = null;
$sources = [];
$notice = null;
if ($canreview) {
    try {
        $rubric = $activity->rubric();
        $sources = $activity->sources($studentid);
    } catch (moodle_exception $error) {
        if ($action !== '') {
            throw $error;
        }
        $notice = $error->getMessage();
    }
}
$selection = null;
if ($action !== '') {
    require_sesskey();
    if (!data_submitted()) {
        access::fail('invalidreview');
    }
    $source = required_param('source', PARAM_INT);
    if (!array_key_exists($source, $sources)) {
        access::fail('invaliddocument');
    }
    if ($action === 'create') {
        $job = workflow::create(
            $activity,
            $studentid,
            $source,
            required_param('nonce', PARAM_ALPHANUM),
            required_param('signature', PARAM_ALPHANUM),
            required_param('rubrichash', PARAM_ALPHANUM),
            required_param('excerpt', PARAM_RAW),
            required_param('provider', PARAM_ALPHA),
            optional_param('consent', false, PARAM_BOOL)
        );
        redirect(ui::url('job', ['id' => $id, 'job' => $job]));
    }
    if ($action !== 'prepare') {
        access::fail('invalidreview');
    }
    [, $signature, $text] = $activity->extract($studentid, $source);
    $rubrichash = hash('sha256', evaluation::json($rubric));
    $selection = core_text::substr(evaluation::redact($text, $student), 0, evaluation::LIMIT);
}

ui::header(ui::url('review', $values), $activity->context, $activity->course, fullname($student));
echo html_writer::tag('p', ui::link('assignment', ['id' => $id], ui::str('backassignment')));
echo $OUTPUT->notification(ui::str('humanonly'), 'info');
if ($selection !== null) {
    ui::form('review', $values + [
        'source' => $source, 'signature' => $signature, 'rubrichash' => $rubrichash,
        'nonce' => bin2hex(random_bytes(16)),
    ]);
    echo html_writer::tag('p', ui::str('excerpthelp'));
    ui::textarea('excerpt', $selection, evaluation::LIMIT);
    $providers = ['local' => ui::str('providerlocal')];
    if (has_capability(access::CAP . ':useai', $activity->context)) {
        foreach (registry::IDS as $provider) {
            if ($config = registry::config($provider)) {
                $label = ui::str('provider' . $provider) . ' · ' . $config['model'];
                $providers[$provider] = s($label . ' · ' . ui::str($config['external'] ? 'external' : 'loopback'));
            }
        }
    }
    echo html_writer::label(ui::str('provider'), 'grading-provider', true, ['class' => 'form-label']);
    echo html_writer::select($providers, 'provider', 'local', false, ['id' => 'grading-provider', 'class' => 'form-select mb-3']);
    if (count($providers) > 1) {
        echo html_writer::div(html_writer::checkbox('consent', 1, false, ui::str('consent'), ['id' => 'grading-consent']), 'mb-2');
    }
    ui::button('create', true);
    echo html_writer::end_tag('form');
} else if ($sources) {
    ui::form('review', $values);
    echo html_writer::label(ui::str('source'), 'grading-source', true, ['class' => 'form-label']);
    echo html_writer::select(
        array_map('s', $sources),
        'source',
        array_key_first($sources),
        false,
        ['id' => 'grading-source', 'class' => 'form-select']
    );
    ui::button('prepare', true);
    echo html_writer::end_tag('form');
} else if ($notice !== null) {
    echo $OUTPUT->notification(s($notice), 'warning');
} else if ($canreview) {
    echo $OUTPUT->notification(ui::str('nosources'), 'warning');
}
if ($rubric !== null) {
    ui::rubric($rubric);
}
echo $OUTPUT->heading(ui::str('studenthistory'), 3);
$conditions = ['cmid' => $id, 'studentid' => $studentid];
$total = $DB->count_records(workflow::JOB, $conditions);
$pagesize = 20;
$historypage = min($historypage, max(0, (int)ceil($total / $pagesize) - 1));
$history = $DB->get_records(
    workflow::JOB,
    $conditions,
    'id DESC',
    'id, timecreated, state, reviewstate',
    $historypage * $pagesize,
    $pagesize
);
$links = [];
foreach ($history as $row) {
    $label = ui::str('historyentry', (object)[
        'date' => userdate($row->timecreated), 'state' => ui::str($row->state), 'review' => ui::str($row->reviewstate),
    ]);
    $links[] = ui::link('job', ['id' => $id, 'job' => $row->id], $label);
}
echo $links ? html_writer::alist($links) : html_writer::tag('p', ui::str('nohistory'));
echo $OUTPUT->paging_bar($total, $historypage, $pagesize, ui::url('review', $values), 'historypage');
echo $OUTPUT->footer();
