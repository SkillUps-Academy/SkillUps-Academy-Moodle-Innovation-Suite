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
 * Display course audit history and batch operations.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use report_skillupsquality\catalog;
use report_skillupsquality\form\batch_form;
use report_skillupsquality\scope;
use report_skillupsquality\store;
use report_skillupsquality\ui;

require_login();
$courseid = optional_param('course', 0, PARAM_INT);
$categoryid = optional_param('category', 0, PARAM_INT);
$batch = optional_param('batch', '', PARAM_ALPHANUM);
$page = max(0, optional_param('page', 0, PARAM_INT));
$action = optional_param('action', '', PARAM_ALPHA);
$course = null;
$category = null;
$form = null;
$params = [];
if ($courseid) {
    $course = scope::course($courseid);
    require_login($course);
    $params['course'] = $courseid;
    if ($action === 'queue') {
        ui::post();
        $queued = store::enqueue([$course]);
        $message = $queued['ids'] ? scope::text('queuedcount', 1) : scope::text('alreadyqueued');
        redirect(ui::url('index', $params), $message);
    }
} else if ($categoryid) {
    $category = scope::category($categoryid);
    $params['category'] = $categoryid;
    $PAGE->set_context($category->get_context());
    $PAGE->set_url(ui::url('index', $params));
    $form = new batch_form(ui::url('index', $params), ['category' => $categoryid]);
} else if (!$batch || !preg_match('/^[a-f0-9]{32}$/D', $batch)) {
    require_capability('report/skillupsquality:batch', context_system::instance());
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(ui::url('index'));
    $form = new batch_form(ui::url('index'), ['category' => 0]);
}
if ($batch && !$course && !$category && !preg_match('/^[a-f0-9]{32}$/D', $batch)) {
    throw new moodle_exception('scopefailure', scope::COMPONENT);
}
$url = ui::url('index', $params + ($batch && !$params ? ['batch' => $batch] : []));
if ($form && ($data = $form->get_data())) {
    $queued = store::enqueue(scope::batch($data->mode, $categoryid, $data->selected ?? ''));
    $redirect = $queued['ids'] ? ui::url('index', ['batch' => $queued['batch']]) : ui::url('index', $params);
    redirect($redirect, $queued['ids'] ? scope::text('batchqueued', (object)[
        'scheduled' => count($queued['ids']), 'skipped' => $queued['skipped'],
    ]) : scope::text('alreadyqueued'));
}
ui::header($url, scope::text('pluginname'), $course, $category?->get_context());
if ($course) {
    echo $OUTPUT->heading(format_string($course->fullname, true, ['context' => context_course::instance($courseid)]), 2);
    if (scope::allowed($course, 'run')) {
        ui::button('index', ['course' => $courseid, 'action' => 'queue'], 'queue');
    }
    $criteria = ['courseid' => $courseid];
    $total = $DB->count_records(store::RUN, $criteria);
    $page = min($page, max(0, (int)ceil($total / 20) - 1));
    $records = $DB->get_records(store::RUN, $criteria, 'id DESC', '*', $page * 20, 20);
    $catalogurl = ui::url('catalog', ['course' => $courseid]);
} else if ($category) {
    echo $OUTPUT->heading($category->get_formatted_name(), 2);
    echo html_writer::tag('p', s(scope::text('categoryhelp')));
    $form->display();
} else if ($batch) {
    $records = [];
    foreach ($DB->get_records(store::RUN, ['batch' => $batch], 'id ASC', '*', 0, 50) as $run) {
        $candidate = $DB->get_record('course', ['id' => $run->courseid]);
        if ($candidate && scope::allowed($candidate)) {
            $records[] = $run;
        }
    }
    echo $OUTPUT->heading(scope::text('batchhistory'), 2);
} else {
    echo html_writer::tag('p', s(scope::text('dashboardhelp')));
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => ui::url('index')->out(false), 'class' => 'mb-4']);
    echo html_writer::label(scope::text('categoryid'), 'quality-category', false, ['class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'min' => 1, 'required' => true, 'name' => 'category', 'id' => 'quality-category',
        'class' => 'form-control mb-2',
    ]);
    echo html_writer::tag('button', s(scope::text('opencategory')), ['class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->heading(scope::text('selectedbatch'), 2);
    $form->display();
    $courses = get_user_capability_course(
        'report/skillupsquality:view',
        null,
        true,
        'fullname,category,visible',
        'fullname',
        50,
    );
    $rows = [];
    foreach ($courses ?: [] as $candidate) {
        if (scope::allowed($candidate)) {
            $name = format_string($candidate->fullname, true, ['context' => context_course::instance($candidate->id)]);
            $rows[] = [html_writer::link(ui::url('index', ['course' => $candidate->id]), $name), (int)$candidate->id];
        }
    }
    if ($rows) {
        ui::table('coursepicker', ['course', 'courseid'], $rows);
    }
    $catalogurl = ui::url('catalog');
}
if (isset($records)) {
    $rows = [];
    foreach ($records as $run) {
        $rows[] = [
            html_writer::link(ui::url('audit', ['id' => $run->id]), s(userdate((int)$run->timecreated))),
            (int)$run->courseid,
            ui::state($run->state),
            $run->score < 0 ? '—' : (int)$run->score,
            $run->state === 'done' ? (int)$run->objective : '—',
            $run->state === 'done' ? (int)$run->advisory : '—',
        ];
    }
    if ($rows) {
        ui::table('history', ['requested', 'courseid', 'state', 'score', 'kind_objective', 'kind_advisory'], $rows);
    } else {
        echo $OUTPUT->notification(scope::text('noaudits'), 'info');
    }
    if (isset($total)) {
        echo $OUTPUT->paging_bar($total, $page, 20, $url);
    }
}
if (isset($catalogurl)) {
    echo html_writer::tag('p', html_writer::link($catalogurl, s(scope::text('catalog'))));
}
echo $OUTPUT->footer();
