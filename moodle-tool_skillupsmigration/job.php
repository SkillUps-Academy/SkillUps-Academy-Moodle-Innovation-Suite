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
 * Display a migration's selections, compatibility checks, outcomes and audit.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_skillupsmigration\support;
use tool_skillupsmigration\ui;

require_login();
require_capability(support::CAP . 'manage', context_system::instance());
$id = required_param('id', PARAM_INT);
$job = support::get(support::JOB, $id);
$context = support::category((int)$job->categoryid, (int)$USER->id);
$items = $DB->get_records(support::ITEM, ['jobid' => $id], 'id ASC');
ui::page('job', compact('id'));
echo $OUTPUT->header();
echo html_writer::start_div('skillups-migration');
echo ui::link(ui::url(), ui::t('back'));
echo $OUTPUT->heading(ui::t('jobnumber', $id));
echo html_writer::tag('p', s(implode(' · ', [ui::t($job->state), format_string($context->get_context_name()), $job->endpoint])));
$actions = [];
$allready = !array_filter($items, static fn($item) => !in_array($item->state, ['ready', 'done'], true));
$retryable = array_filter($items, static fn($item) => $item->state === 'blocked' && !$item->courseid);
if ($job->actor == $USER->id || is_siteadmin()) {
    if ($job->state === 'draft') {
        $actions[] = 'simulate';
    } else if ($job->state === 'review' && $allready) {
        $actions[] = 'execute';
    }
    if (in_array($job->state, ['review', 'complete'], true) && $retryable) {
        $actions[] = 'retry';
    }
    if (!in_array($job->state, ['complete', 'cancelled'], true)) {
        $actions[] = 'cancel';
    }
}
echo html_writer::start_div('sm-actions');
foreach ($actions as $action) {
    echo ui::link(
        ui::url('action', compact('id', 'action')),
        ui::t($action),
        in_array($action, ['simulate', 'execute'], true) ? 'btn btn-primary' : 'btn btn-outline-secondary'
    );
}
echo html_writer::end_div();
if (in_array($job->state, ['preflight', 'running'], true)) {
    echo html_writer::tag('p', ui::t('cronrunning'));
}
if ($job->state === 'review' && !$allready) {
    echo html_writer::div(ui::t('blockedbatch'), 'alert alert-warning');
}
foreach ($items as $item) {
    $data = support::decode($item->inventory);
    $report = support::decode($item->report);
    echo html_writer::start_tag('details', ['class' => 'sm-report', 'open' => 'open']);
    echo html_writer::tag('summary', s($data['name'] . ' → ' . $item->shortname . ' · ' . ui::t($item->state)));
    echo html_writer::tag('p', s($data['category'] . ' · ' . $data['release'] . ' · ' . $data['format']));
    if ($item->courseid) {
        echo ui::link(new moodle_url('/course/view.php', ['id' => $item->courseid]), ui::t('opencourse'));
    }
    if (!empty($report['issues'])) {
        $problems = [];
        foreach ($report['issues'] as $problem) {
            $problems[] = [s(ui::t($problem['code'])), s($problem['detail'])];
        }
        echo ui::table(['issues', 'detail'], $problems);
    }
    if (!empty($report['lastretry'])) {
        echo html_writer::tag('p', s(ui::t('deferred') . ' · ' . ui::t($report['lastretry'])));
    }
    $summary = [
        [ui::t('sectionlist'), count($data['sections'])], [ui::t('activitylist'), count($data['modules'])],
        [ui::t('groups'), $data['groups']],
    ];
    if (!empty($report['artifacts'])) {
        $summary[] = [ui::t('files'), (int)$report['artifacts']['files']];
        $summary[] = [ui::t('questiontypes'), s(implode(', ', $report['artifacts']['questiontypes']))];
        $summary[] = [ui::t('archivesize'), display_size((int)$report['artifacts']['bytes'])];
    }
    if (!empty($report['precheck'])) {
        $details = [s(ui::t('checkcount', (object)$report['precheck']))];
        foreach ($report['precheck']['messages'] ?? [] as $message) {
            $details[] = s($message);
        }
        $summary[] = [ui::t('nativeprecheck'), implode(html_writer::empty_tag('br'), $details)];
    }
    if (!empty($report['verification'])) {
        $check = $report['verification'];
        $summary[] = [ui::t('verification'), ui::t($check['match'] ? 'verified' : 'difference')];
        $summary[] = [ui::t('missingsections'), s(implode(', ', $check['missingsections']))];
        $summary[] = [ui::t('extrasections'), s(implode(', ', $check['extrasections']))];
        $summary[] = [ui::t('activitydiff'), s(implode(', ', $check['activities']))];
        $summary[] = [ui::t('groups'), s($check['expectedgroups'] . ' → ' . $check['actualgroups'])];
        $summary[] = [ui::t('datedifference'), get_string($check['datedifference'] ? 'yes' : 'no')];
        $summary[] = [ui::t('staff'), $check['mapped']];
    }
    echo ui::table(['detail', 'value'], $summary);
    echo html_writer::start_tag('details');
    echo html_writer::tag('summary', ui::t('activitylist'));
    $rows = [];
    foreach ($data['modules'] as $module) {
        $rows[] = [(int)$module['id'], s($module['type']), s($module['name'])];
    }
    echo ui::table(['identifier', 'type', 'name'], $rows);
    echo html_writer::end_tag('details');
    echo html_writer::end_tag('details');
}
echo $OUTPUT->heading(ui::t('audit'), 3);
$page = max(0, min(10000, optional_param('page', 0, PARAM_INT)));
$events = [];
foreach ($DB->get_records(support::AUDIT, ['jobid' => $id], 'created DESC, id DESC', '*', $page * 30, 30) as $log) {
    $events[] = [s(userdate($log->created)), $log->itemid ?: '–', $log->actor ?: '–', ui::t($log->action)];
}
echo ui::table(['updated', 'identifier', 'actor', 'action'], $events);
echo $OUTPUT->paging_bar($DB->count_records(support::AUDIT, ['jobid' => $id]), $page, 30, ui::url('job', ['id' => $id]));
echo html_writer::end_div();
echo $OUTPUT->footer();
