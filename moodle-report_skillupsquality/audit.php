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
 * Present an authorized review and export its structured evidence.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsquality\licensing\entitlements;
use report_skillupsquality\review;
use report_skillupsquality\scope;
use report_skillupsquality\store;
use report_skillupsquality\ui;

require_login();
$run = store::run(required_param('id', PARAM_INT));
$course = scope::course((int)$run->courseid);
require_login($course);
$action = optional_param('action', '', PARAM_ALPHA);
$licensed = in_array('aggregate_export', entitlements::status()['features'], true);
if ($action === 'csv' || $action === 'excel') {
    ui::post();
    if ($run->state !== 'done' || ($action === 'excel' && !$licensed)) {
        throw new moodle_exception('invalidaction', scope::COMPONENT);
    }
    review::export($run, $action);
    exit;
}
$compareid = optional_param('compare', 0, PARAM_INT);
$kind = optional_param('kind', 'all', PARAM_ALPHA);
if (!in_array($kind, ['all', 'objective', 'advisory'], true)) {
    $kind = 'all';
}
$earlier = $run->state === 'done' ? review::earlier($run) : [];
$past = $compareid ? ($earlier[$compareid] ?? null) : (reset($earlier) ?: null);
if ($compareid && !$past) {
    throw new moodle_exception('scopefailure', scope::COMPONENT);
}
$params = ['id' => $run->id, 'kind' => $kind];
if ($compareid) {
    $params['compare'] = $compareid;
}
$url = ui::url('audit', $params);
ui::header($url, scope::text('auditdetails'), $course);
echo html_writer::tag('p', s(userdate((int)$run->timecreated)) . ' ' . ui::state($run->state));
if ($run->state !== 'done') {
    echo $OUTPUT->notification(scope::text('progress_' . $run->state), 'info');
    echo html_writer::link($url, s(scope::text('refresh')));
    echo $OUTPUT->footer();
    exit;
}
echo $OUTPUT->render_from_template('report_skillupsquality/summary', ['metrics' => [
    ['label' => scope::text('score'), 'value' => $run->score < 0 ? '—' : $run->score . ' / 100'],
    ['label' => scope::text('kind_objective'), 'value' => $run->objective],
    ['label' => scope::text('kind_advisory'), 'value' => $run->advisory],
]]);
echo html_writer::tag('p', s(scope::text('scorehelp')));
$coverage = store::decode($run->policy)['unverified'] ?? [];
if ($coverage) {
    echo $OUTPUT->notification(scope::text('partiallinks', count($coverage)), 'info');
}
ui::button('audit', ['id' => $run->id, 'action' => 'csv'], 'csv');
if ($licensed) {
    ui::button('audit', ['id' => $run->id, 'action' => 'excel'], 'excel');
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => ui::url('audit')->out(false), 'class' => 'mb-4']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $run->id]);
echo html_writer::label(scope::text('kind'), 'quality-kind', false, ['class' => 'form-label']);
$kinds = array_combine(['all', 'objective', 'advisory'], array_map(
    static fn($type) => scope::text('kind_' . $type),
    ['all', 'objective', 'advisory'],
));
echo html_writer::select($kinds, 'kind', $kind, false, ['id' => 'quality-kind', 'class' => 'form-select mb-2']);
if ($earlier) {
    $options = [];
    foreach ($earlier as $item) {
        $options[$item->id] = userdate((int)$item->timecreated);
    }
    echo html_writer::label(scope::text('comparison'), 'quality-compare', false, ['class' => 'form-label']);
    echo html_writer::select($options, 'compare', $past->id, false, ['id' => 'quality-compare', 'class' => 'form-select mb-2']);
}
echo html_writer::tag('button', s(scope::text('apply')), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
if ($past) {
    $comparison = review::compare($run, $past);
    echo $OUTPUT->heading(scope::text('comparison'), 2);
    if ($comparison === null) {
        echo $OUTPUT->notification(scope::text('comparisonpolicy'), 'info');
    } else {
        if ($run->score >= 0 && $past->score >= 0) {
            echo html_writer::tag('p', s(scope::text('scoredelta', (object)[
                'before' => $past->score, 'after' => $run->score, 'change' => (int)$run->score - (int)$past->score,
            ])));
        }
        $summary = [];
        foreach ($comparison as $key => $items) {
            $summary[] = [s(scope::text('delta_' . $key)), count($items)];
        }
        ui::table('comparison', ['state', 'count'], $summary);
        foreach (['new', 'absent'] as $key) {
            if (!$comparison[$key]) {
                continue;
            }
            $rows = [];
            foreach (array_slice($comparison[$key], 0, 20) as $entry) {
                $rows[] = [
                    html_writer::link(ui::url('finding', ['id' => $entry->id]), s(ui::rule_name($entry->rulecode))),
                    ui::target($course, $entry),
                ];
            }
            ui::table('delta_' . $key, ['rule', 'target'], $rows);
        }
    }
}
$filter = ['runid' => $run->id];
if ($kind !== 'all') {
    $filter['kind'] = $kind;
}
$total = $DB->count_records(store::FINDING, $filter);
$page = max(0, min(optional_param('page', 0, PARAM_INT), (int)ceil($total / 25) - 1));
$findings = $DB->get_records(store::FINDING, $filter, 'kind DESC, id ASC', '*', $page * 25, 25);
$rows = [];
foreach ($findings as $item) {
    $rows[] = [
        html_writer::link(ui::url('finding', ['id' => $item->id]), s(ui::rule_name($item->rulecode))),
        s(scope::text('kind_' . $item->kind)),
        s(scope::text('severity_' . $item->severity)),
        ui::target($course, $item),
    ];
}
if ($rows) {
    ui::table('findings', ['rule', 'kind', 'severity', 'target'], $rows);
    echo $OUTPUT->paging_bar($total, $page, 25, $url);
} else {
    echo $OUTPUT->notification(scope::text('nofindings'), 'info');
}
echo $OUTPUT->footer();
