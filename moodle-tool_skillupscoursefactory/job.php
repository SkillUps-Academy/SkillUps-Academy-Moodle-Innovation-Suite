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
 * Review, authorize, cancel, retry or export courses in one operation.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_skillupscoursefactory\jobs;
use tool_skillupscoursefactory\licensing\entitlements;
use tool_skillupscoursefactory\support;
use tool_skillupscoursefactory\ui;

require_login();
$id = required_param('id', PARAM_INT);
ui::setup('job', 'jobdetails', ['id' => $id]);
$job = jobs::visible($id);
$action = optional_param('action', '', PARAM_ALPHA);
$errors = optional_param('errors', false, PARAM_BOOL);
$filter = ['jobid' => $id];
$where = 'jobid = :jobid';
if ($errors) {
    $where .= " AND state IN ('invalid', 'failed', 'attention')";
}
$excel = in_array('aggregate_export', entitlements::status()['features'], true);
if ($action) {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidaction', support::C);
    }
    if ($action === 'csv' || ($action === 'excel' && $excel)) {
        jobs::audit($id, 'exported');
        $rows = $DB->get_recordset_select(jobs::ROW, $where, $filter, 'position ASC');
        $entries = (static function () use ($rows): Generator {
            try {
                foreach ($rows as $row) {
                    $data = json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR);
                    $columns = [
                        $row->position, $data['fullname'] ?? '', $data['shortname'] ?? '', $data['idnumber'] ?? '',
                        $data['period'] ?? '', $data['category'] ?? '', $data['source'] ?? '', $row->courseid ?: '',
                        support::text('state_' . $row->state), $row->attempts,
                        $row->errorcode ? support::text($row->errorcode) : '',
                    ];
                    yield array_map([ui::class, 'csv_safe'], $columns);
                }
            } finally {
                $rows->close();
            }
        })();
        $heading = array_map([support::class, 'text'], [
            'line', 'fullname', 'shortname', 'idnumber', 'period', 'category', 'source', 'courseid', 'state', 'attempts', 'reason',
        ]);
        \core\dataformat::download_data('skillups-course-factory-' . $id, $action, $heading, $entries);
        exit;
    }
    jobs::command($id, $action, optional_param('row', 0, PARAM_INT));
    redirect(ui::url('job', ['id' => $id]), support::text('submitted'));
}
$total = $DB->count_records_select(jobs::ROW, $where, $filter);
$page = max(0, min(optional_param('page', 0, PARAM_INT), (int)ceil($total / 25) - 1));
$counts = jobs::counts($id);
$params = ['id' => $id, 'errors' => $errors ? 1 : 0];
ui::header();
echo $OUTPUT->heading(s(support::text('jobref', $id)), 2);
echo html_writer::tag('p', ui::state($job->state) . ' ' . s(support::text(
    'scheduledfor',
    $job->runat ? userdate($job->runat) : support::text('immediate')
)));
if ($job->state === 'draft') {
    echo $OUTPUT->notification(support::text('previewhelp'), 'info');
}
if ($job->state === 'queued') {
    echo html_writer::tag('p', s(support::text($job->cancelled ? 'stopping' : 'cronhelp')));
}
$stats = [];
foreach ($counts as $state => $count) {
    $stats[] = [ui::state($state), (int)$count];
}
ui::table('summary', ['state', 'count'], $stats);
echo html_writer::start_div('d-flex flex-wrap gap-2 mb-3');
if ($job->state === 'draft' && $job->userid == $USER->id && !empty($counts['pending'])) {
    echo $OUTPUT->single_button(ui::url('job', ['id' => $id, 'action' => 'confirm']), support::text('confirm'), 'post');
}
if (in_array($job->state, ['draft', 'queued'], true) && !$job->cancelled) {
    echo $OUTPUT->single_button(ui::url('job', ['id' => $id, 'action' => 'cancel']), support::text('canceljob'), 'post');
}
echo $OUTPUT->single_button(ui::url('job', $params + ['action' => 'csv']), support::text('csv'), 'post');
if ($excel) {
    echo $OUTPUT->single_button(ui::url('job', $params + ['action' => 'excel']), support::text('excel'), 'post');
}
echo html_writer::end_div();
$filterurl = ui::url('job', ['id' => $id, 'errors' => $errors ? 0 : 1]);
echo html_writer::tag('p', html_writer::link($filterurl, s(support::text($errors ? 'allrows' : 'onlyerrors'))));
$rows = [];
foreach ($DB->get_records_select(jobs::ROW, $where, $filter, 'position ASC', '*', $page * 25, 25) as $row) {
    $data = json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR);
    $result = $row->errorcode ? s(support::text($row->errorcode)) : '—';
    if ($row->state === 'failed' && $row->attempts < 3 && !$job->cancelled && $job->userid == $USER->id) {
        $retry = ui::url('job', ['id' => $id, 'row' => $row->id, 'action' => 'retry']);
        $result .= $OUTPUT->single_button($retry, support::text('retry'), 'post');
    }
    if (
        $row->courseid && ($course = $DB->get_record('course', ['id' => $row->courseid]))
            && core_course_category::can_view_course_info($course)
    ) {
        $result .= html_writer::div(html_writer::link(
            new moodle_url('/course/view.php', ['id' => $row->courseid]),
            s(support::text('courseid') . ' ' . $row->courseid)
        ));
    }
    $configuration = [];
    foreach (['shortname', 'idnumber', 'period', 'category', 'source'] as $key) {
        if (!empty($data[$key])) {
            $configuration[] = html_writer::tag('div', s(support::text($key) . ' ' . $data[$key]));
        }
    }
    foreach (['startdate', 'enddate'] as $key) {
        if (!empty($data[$key])) {
            $configuration[] = html_writer::tag('div', s(support::text($key) . ' ' . userdate($data[$key])));
        }
    }
    if (isset($data['visible'])) {
        $configuration[] = html_writer::tag('div', s(support::text($data['visible'] ? 'published' : 'hidden')));
    }
    foreach ($data['staff'] ?? [] as $person) {
        $configuration[] = html_writer::tag('div', s(support::text('assignment', (object)$person)));
    }
    $rows[] = [
        (int)$row->position, s($data['fullname'] ?? ''), implode('', $configuration), ui::state($row->state),
        s(support::text('phase_' . $row->phase)), (int)$row->attempts, $result,
    ];
}
if ($rows) {
    ui::table('results', ['line', 'fullname', 'configuration', 'state', 'phase', 'attempts', 'reason'], $rows);
    echo $OUTPUT->paging_bar($total, $page, 25, ui::url('job', $params));
} else {
    echo $OUTPUT->notification(support::text('norows'), 'info');
}
echo $OUTPUT->footer();
