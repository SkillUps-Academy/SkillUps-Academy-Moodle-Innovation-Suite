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
 * Show course audits, history and remediation findings.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsaccessibility\auditor;
use report_skillupsaccessibility\report;
use report_skillupsaccessibility\scope;
use report_skillupsaccessibility\store;

require_login();
$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
if (!scope::allowed($course)) {
    scope::fail();
}
$context = context_course::instance($id);
scope::setup('course', $context, ['id' => $id]);
$url = scope::url('course', ['id' => $id]);
if (data_submitted()) {
    require_sesskey();
    auditor::enqueue($course);
    redirect($url, scope::text('queuednotice'));
}
$jobid = optional_param('jobid', 0, PARAM_INT);
$latest = report::latest($id);
$selected = $jobid ? store::get(store::JOB, $jobid) : $latest;
if ($selected && ($selected->courseid != $id || $selected->status !== 'complete')) {
    scope::fail();
}
$metrics = report::metrics($selected ? report::rows($course, $selected) : []);
$stats = [];
foreach (['inventoried', 'checked', 'confirmed', 'review'] as $key) {
    $stats[] = ['name' => scope::text($key), 'value' => $metrics[$key]];
}
$stats[] = ['name' => scope::text('index'), 'value' => $metrics['index'] === null ?
    scope::text('nodata') : format_float($metrics['index'], 1) . '%'];
$format = core_plugin_manager::instance()->get_plugin_info('dataformat_csv');
$canexport = $selected && scope::allowed($course, 'export') && $format && $format->is_enabled();
$observed = $selected ? report::observations(report::rows($course, $selected)) : [];
if (optional_param('download', 0, PARAM_BOOL)) {
    if (!$canexport) {
        scope::fail();
    }
    $columns = ['source', 'location', 'rule', 'classification', 'severity', 'criterion', 'state', 'recommendation'];
    $columns = array_map(static fn($key) => scope::text($key), $columns);
    core\dataformat::download_data('skillups-accessibility-findings', 'csv', $columns, $observed, static fn($row) => [
        report::safe_cell($row['label']), $row['location'], scope::text('rule_' . $row['ruleid']),
        scope::text($row['classification']), scope::text($row['severity']), $row['criterion'],
        scope::text($row['state']), scope::text('fix_' . $row['ruleid']),
    ]);
    exit;
}
$items = [];
$count = 0;
$page = max(0, optional_param('page', 0, PARAM_INT));
$filter = optional_param('state', '', PARAM_ALPHA);
if (!in_array($filter, ['', 'pending', 'corrected', 'dismissed'], true)) {
    scope::fail();
}
$showfixed = $filter === 'corrected' && $selected && $latest && $selected->id == $latest->id;
if ($showfixed) {
    $observed = (static function () use ($observed, $DB, $course): Generator {
        yield from $observed;
        $assets = store::ASSET;
        $findings = store::FINDING;
        $found = $DB->get_recordset_sql(
            "SELECT f.*, a.label FROM {{$findings}} f JOIN {{$assets}} a ON a.id = f.assetid
              WHERE a.courseid = :course AND f.state = :state AND f.present = 0",
            ['course' => $course->id, 'state' => 'corrected'],
        );
        try {
            foreach ($found as $row) {
                if (scope::asset($course, store::get(store::ASSET, (int)$row->assetid))) {
                    $rule = \report_skillupsaccessibility\rules::CATALOG[$row->ruleid];
                    yield (array)$row + ['severity' => $rule[0], 'classification' => $rule[1], 'criterion' => $rule[2]];
                }
            }
        } finally {
            $found->close();
        }
    })();
}
foreach ($observed as $issue) {
    if ($filter !== '' && $filter !== $issue['state']) {
        continue;
    }
    if ($count >= 50 * $page && $count < 50 * ($page + 1)) {
        $items[] = [
            'source' => $issue['label'], 'location' => $issue['location'],
            'fact' => $issue['ruleid'] === 'pdfstructure' ? scope::text($issue['fact']) : $issue['fact'],
            'name' => scope::text('rule_' . $issue['ruleid']), 'criterion' => $issue['criterion'],
            'classification' => scope::text($issue['classification']),
            'severity' => scope::text($issue['severity']), 'state' => scope::text($issue['state']),
            'href' => $issue['id'] ? scope::url('finding', ['id' => $issue['id']])->out(false) : '',
        ];
    }
    $count++;
}
$history = [];
foreach ($DB->get_records(store::JOB, ['courseid' => $id, 'status' => 'complete'], 'finished DESC, id DESC', '*', 0, 12) as $past) {
    $values = report::metrics(report::rows($course, $past));
    $history[] = [
        'date' => userdate($past->finished), 'checked' => $values['checked'], 'confirmed' => $values['confirmed'],
        'index' => $values['index'] === null ? scope::text('nodata') : format_float($values['index'], 1) . '%',
        'href' => scope::url('course', ['id' => $id, 'jobid' => $past->id])->out(false),
    ];
}
$last = $DB->get_records(store::JOB, ['courseid' => $id], 'id DESC', '*', 0, 1);
$last = reset($last);
$states = [];
foreach (
    ['' => scope::text('all'), 'pending' => scope::text('pending'), 'corrected' => scope::text('corrected'),
    'dismissed' => scope::text('dismissed')] as $key => $label
) {
    $states[] = ['value' => $key, 'label' => $label, 'selected' => $key === $filter];
}
$reasons = [];
foreach ($metrics['reasons'] as $reason => $value) {
    $reasons[] = ['label' => scope::text($reason), 'count' => $value];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(scope::C . '/course', [
    'title' => format_string($course->fullname), 'stats' => $stats, 'findings' => $items, 'jobs' => $history, 'reasons' => $reasons,
    'scan' => scope::allowed($course, 'scan'), 'states' => $states,
    'partial' => $selected && $selected->truncated, 'selection' => $selected ? userdate($selected->finished) : '',
    'jobstatus' => $last ? scope::text($last->status) : scope::text('nodata'),
    'error' => $last && $last->errorcode ? scope::text($last->errorcode) : '',
    'processed' => $selected && $selected->actorid == $USER->id &&
        $metrics['inventoried'] == $selected->changed + $selected->reused ?
        scope::text('cachecounts', (object)['changed' => $selected->changed, 'reused' => $selected->reused]) : '',
    'courseid' => $id, 'jobid' => $selected->id ?? 0, 'sesskey' => sesskey(), 'form' => scope::url('course')->out(false),
    'download' => $canexport ? scope::url('course', ['id' => $id, 'jobid' => $selected->id, 'download' => 1])->out(false) : '',
]);
echo $OUTPUT->paging_bar($count, $page, 50, scope::url('course', ['id' => $id, 'jobid' => $selected->id ?? 0, 'state' => $filter]));
echo $OUTPUT->footer();
