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
 * Review and authorize the simulated enrollment ledger.
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
$id = required_param('id', PARAM_INT);
ui::setup('job', 'jobdetails', ['id' => $id]);
$job = jobs::visible($id);
$errors = optional_param('errors', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);
$where = 'jobid = :job';
$params = ['job' => $id];
if ($errors) {
    $where .= " AND state IN ('failed', 'invalid')";
}
$excel = ui::excel_enabled();
if ($action) {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidaction', support::C);
    }
    if ($action === 'csv' || ($action === 'excel' && $excel)) {
        jobs::audit($id, 'exported');
        ui::download($where, $params, $action, 'skillups-enrollment-' . $id);
        exit;
    }
    jobs::command($id, $action, optional_param('row', 0, PARAM_INT));
    redirect(ui::url('job', ['id' => $id]), support::text('submitted'));
}
$urlparams = ['id' => $id, 'errors' => $errors ? 1 : 0];
$total = $DB->count_records_select(jobs::ROW, $where, $params);
$page = max(0, min(optional_param('page', 0, PARAM_INT), (int)ceil($total / 25) - 1));
$counts = jobs::counts($id);
ui::header();
echo $OUTPUT->heading(s(support::text('jobref', $id)), 2);
echo html_writer::tag('p', ui::state($job->state) . ' ' . s($job->runat ? userdate($job->runat) : support::text('immediate')));
if ($job->state === 'draft') {
    echo $OUTPUT->notification(support::text('previewhelp'), 'info');
} else if ($job->state === 'queued') {
    echo html_writer::tag('p', s(support::text($job->cancelled ? 'stopping' : 'cronhelp')));
}
$summary = [];
foreach ($counts as $state => $count) {
    $summary[] = [ui::state($state), (int)$count];
}
ui::table('summary', ['state', 'count'], $summary);
echo html_writer::start_div('d-flex flex-wrap gap-2 mb-3');
if ($job->state === 'draft' && $job->userid == $USER->id && !empty($counts['pending'])) {
    echo $OUTPUT->single_button(ui::url('job', ['id' => $id, 'action' => 'confirm']), support::text('confirm'), 'post');
}
if (in_array($job->state, ['draft', 'queued'], true) && !$job->cancelled) {
    echo $OUTPUT->single_button(ui::url('job', ['id' => $id, 'action' => 'cancel']), support::text('canceljob'), 'post');
}
echo $OUTPUT->single_button(ui::url('job', $urlparams + ['action' => 'csv']), support::text('csv'), 'post');
if ($excel) {
    echo $OUTPUT->single_button(ui::url('job', $urlparams + ['action' => 'excel']), support::text('excel'), 'post');
}
echo html_writer::end_div();
echo html_writer::tag('p', html_writer::link(
    ui::url('job', ['id' => $id, 'errors' => $errors ? 0 : 1]),
    s(support::text($errors ? 'allrows' : 'onlyerrors'))
));
$rows = [];
foreach ($DB->get_records_select(jobs::ROW, $where, $params, 'position ASC', '*', $page * 25, 25) as $row) {
    if (!ui::row_visible($row)) {
        continue;
    }
    $result = $row->errorcode ? s(support::text($row->errorcode)) : '—';
    if (
        $row->state === 'failed' && $row->errorcode !== 'changed' && $row->attempts < 3
            && !$job->cancelled && $job->userid == $USER->id
    ) {
        $url = ui::url('job', ['id' => $id, 'action' => 'retry', 'row' => $row->id]);
        $result .= $OUTPUT->single_button($url, support::text('retry'), 'post');
    }
    $rows[] = [
        (int)$row->position, $row->courseid ?: '—', $row->userid ?: '—', s(support::text('action_' . $row->action)),
        ui::simulation($row), ui::state($row->state), (int)$row->attempts, $result,
    ];
}
if ($rows) {
    ui::table('results', ['line', 'courseid', 'userid', 'action', 'simulation', 'state', 'attempts', 'reason'], $rows);
} else {
    echo $OUTPUT->notification(support::text('norows'), 'info');
}
echo $OUTPUT->paging_bar($total, $page, 25, ui::url('job', $urlparams));
echo $OUTPUT->footer();
