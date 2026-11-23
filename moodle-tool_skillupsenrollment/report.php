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
 * Filter the scoped ledger by course, participant or batch.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_skillupsenrollment\jobs;
use tool_skillupsenrollment\support;
use tool_skillupsenrollment\ui;

require_login();
ui::setup('report', 'reports');
$filter = [];
foreach (['courseid', 'userid', 'jobid'] as $key) {
    $value = optional_param($key, 0, PARAM_INT);
    if ($value > 0) {
        $filter[$key] = $value;
    }
}
if (!empty($filter['jobid'])) {
    jobs::visible($filter['jobid']);
}
if (!empty($filter['courseid']) && !support::can_report($filter['courseid'], (int)$USER->id)) {
    throw new moodle_exception('denied', support::C);
}
$errors = optional_param('errors', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);
$urlparams = $filter + ['errors' => $errors ? 1 : 0];
$where = [];
$params = $filter;
foreach ($filter as $key => $value) {
    $where[] = "$key = :$key";
}
if ($errors) {
    $where[] = "state IN ('invalid', 'failed')";
}
if (!has_capability('tool/skillupsenrollment:manage', context_system::instance())) {
    $where[] = 'jobid IN (SELECT id FROM {' . jobs::JOB . '} WHERE userid = :owner)';
    $params['owner'] = $USER->id;
}
$select = $where ? implode(' AND ', $where) : '1 = 1';
$excel = ui::excel_enabled();
if ($action) {
    require_sesskey();
    if (!$filter || !data_submitted() || ($action !== 'csv' && !($action === 'excel' && $excel))) {
        throw new moodle_exception('invalidaction', support::C);
    }
    jobs::audit($filter['jobid'] ?? 0, 'reportexported');
    ui::download($select, $params, $action, 'skillups-enrollment-report');
    exit;
}
ui::header();
echo html_writer::tag('p', s(support::text('reporthelp')));
echo html_writer::start_tag('form', ['method' => 'get', 'action' => ui::url('report')->out(false), 'class' => 'mb-4']);
foreach (['courseid', 'userid', 'jobid'] as $key) {
    echo html_writer::label(support::text($key), 'scope-' . $key, false, ['class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'name' => $key, 'type' => 'number', 'min' => 1, 'id' => 'scope-' . $key,
        'value' => $filter[$key] ?? '', 'class' => 'form-control mb-2',
    ]);
}
echo html_writer::checkbox('errors', 1, $errors, support::text('onlyerrors'), ['class' => 'me-2']);
echo html_writer::empty_tag('br');
echo html_writer::tag('button', s(support::text('applyfilter')), ['class' => 'btn btn-primary mt-2']);
echo html_writer::end_tag('form');
if ($filter) {
    echo $OUTPUT->single_button(ui::url('report', $urlparams + ['action' => 'csv']), support::text('csv'), 'post');
    if ($excel) {
        echo $OUTPUT->single_button(ui::url('report', $urlparams + ['action' => 'excel']), support::text('excel'), 'post');
    }
    $total = $DB->count_records_select(jobs::ROW, $select, $params);
    $page = max(0, min(optional_param('page', 0, PARAM_INT), (int)ceil($total / 25) - 1));
    $rows = [];
    foreach ($DB->get_records_select(jobs::ROW, $select, $params, 'id DESC', '*', $page * 25, 25) as $row) {
        if (!ui::row_visible($row)) {
            continue;
        }
        $rows[] = [
            html_writer::link(ui::url('job', ['id' => $row->jobid]), s(support::text('jobref', $row->jobid))),
            $row->courseid ?: '—', $row->userid ?: '—', s(support::text('action_' . $row->action)), ui::state($row->state),
            $row->errorcode ? s(support::text($row->errorcode)) : '—', s(userdate($row->timemodified)),
        ];
    }
    if ($rows) {
        ui::table('results', ['job', 'courseid', 'userid', 'action', 'state', 'reason', 'modified'], $rows);
    } else {
        echo $OUTPUT->notification(support::text('norows'), 'info');
    }
    echo $OUTPUT->paging_bar($total, $page, 25, ui::url('report', $urlparams));
}
echo $OUTPUT->footer();
