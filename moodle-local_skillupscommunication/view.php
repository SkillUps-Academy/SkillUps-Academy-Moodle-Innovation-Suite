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
 * Read campaign content, frozen recipients, processing and audit history.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscommunication\access;
use local_skillupscommunication\store;
use local_skillupscommunication\transport;
use local_skillupscommunication\ui;

require_login();
$id = required_param('id', PARAM_INT);
$record = store::get(store::CAMP, $id);
$scope = access::scope((int)$record->scopeid);
if (!access::can($scope, 'view')) {
    access::fail();
}
$panel = optional_param('panel', 'recipients', PARAM_ALPHA);
$filter = optional_param('filter', '', PARAM_ALPHA);
$page = max(0, min(10000, optional_param('page', 0, PARAM_INT)));
$author = access::can($scope, 'compose');
$reviewer = access::can($scope, 'approve');
ui::page('view', compact('id', 'panel', 'filter', 'page'), $scope);
echo $OUTPUT->header();
echo html_writer::start_div('skillups-comm');
echo ui::link(ui::url('index', ['scopeid' => $scope->id]), ui::t('back'));
echo $OUTPUT->heading(s($record->subject));
echo html_writer::tag('p', s(implode(' · ', [
    ui::t($record->kind), ui::t($record->state), transport::channels()[$record->channel] ?? ui::t('channelunavailable'),
    $record->sendat ? userdate($record->sendat) : ui::t('immediate'),
])));
echo html_writer::div(s($record->body), 'sc-body');
echo html_writer::start_div('sc-actions');
if ($author && $record->state === 'draft') {
    echo ui::link(ui::url('edit', ['id' => $id]), ui::t('editcampaign'), 'btn btn-outline-secondary');
    echo ui::link(ui::url('action', ['id' => $id, 'op' => 'submit']), ui::t('preview'), 'btn btn-primary');
}
$actions = [];
if (($author || $reviewer) && in_array($record->state, ['draft', 'review', 'queued', 'running'], true)) {
    $actions[] = 'cancel';
}
if ($reviewer && $record->state === 'review') {
    array_push($actions, 'approve', 'return');
}
if (
    $reviewer && $record->state === 'complete' && $DB->record_exists_select(
        store::REC,
        "campaignid = :id AND state = 'failed' AND attempts < 6",
        ['id' => $id]
    )
) {
    $actions[] = 'retry';
}
foreach ($actions as $op) {
    echo ui::link(ui::url('action', compact('id', 'op')), ui::t($op), 'btn btn-outline-secondary');
}
echo html_writer::end_div();

$states = ['pending', 'sending', 'accepted', 'delivered', 'failed', 'skipped', 'uncertain', 'cancelled'];
$counts = [];
foreach ($states as $state) {
    $counts[$state] = $DB->count_records(store::REC, ['campaignid' => $id, 'state' => $state]);
}
$processed = array_sum($counts) - $counts['pending'] - $counts['sending'] - $counts['cancelled'];
$summary = [['label' => ui::t('processed'), 'count' => $processed]];
foreach (['accepted', 'delivered', 'failed', 'uncertain', 'skipped'] as $state) {
    $summary[] = ['label' => ui::t($state), 'count' => $counts[$state]];
}
echo $OUTPUT->render_from_template('local_skillupscommunication/summary', ['items' => $summary]);
echo html_writer::tag('p', ui::t('metrichelp'));
echo html_writer::div(
    ui::link(ui::url('view', ['id' => $id, 'panel' => 'recipients']), ui::t('recipients'), 'btn btn-outline-secondary') .
    ui::link(ui::url('view', ['id' => $id, 'panel' => 'audit']), ui::t('audit'), 'btn btn-outline-secondary'),
    'sc-actions'
);
$conditions = ['campaignid' => $id];
$rows = [];
if ($panel === 'audit') {
    $table = store::LOG;
    foreach ($DB->get_records($table, $conditions, 'created DESC, id DESC', '*', $page * 30, 30) as $log) {
        $actor = $log->userid ? \core_user::get_user($log->userid) : null;
        $rows[] = [s(userdate($log->created)), s($actor && !$actor->deleted ? fullname($actor) : ui::t('automation')),
            ui::t($log->action)];
    }
    echo ui::table(['updated', 'actor', 'operation'], $rows);
} else {
    $table = store::REC;
    echo html_writer::start_tag('form', ['action' => ui::url('view')->out(false), 'method' => 'get', 'class' => 'sc-actions']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
    echo html_writer::label(ui::t('state'), 'sc-recipient-state', false);
    echo html_writer::select(['' => ui::t('any')] + ui::options($states), 'filter', $filter, false, ['id' => 'sc-recipient-state']);
    echo html_writer::tag('button', ui::t('filter'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']);
    echo html_writer::end_tag('form');
    if (in_array($filter, $states, true)) {
        $conditions['state'] = $filter;
    }
    foreach ($DB->get_records($table, $conditions, 'id ASC', '*', $page * 30, 30) as $recipient) {
        $user = $recipient->userid ? \core_user::get_user($recipient->userid) : null;
        $rows[] = [
            s($user && !$user->deleted ? fullname($user) : ui::t('unknownuser')),
            ui::t($recipient->state), (int)$recipient->attempts, $recipient->failure ? ui::t($recipient->failure) : '–',
            $recipient->messageid ?: '–', s(userdate($recipient->updated)),
        ];
    }
    echo ui::table(['recipient', 'state', 'attempts', 'failure', 'reference', 'updated'], $rows);
}
echo $OUTPUT->paging_bar($DB->count_records($table, $conditions), $page, 30, ui::url('view', compact('id', 'panel', 'filter')));
echo html_writer::end_div();
echo $OUTPUT->footer();
