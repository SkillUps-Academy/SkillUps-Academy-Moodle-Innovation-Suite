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
 * Show scoped campaigns and communication statistics.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscommunication\access;
use local_skillupscommunication\store;
use local_skillupscommunication\ui;

require_login();
$scopes = access::scopes();
if (!$scopes) {
    access::fail();
}
$scopeid = optional_param('scopeid', (int)array_key_first($scopes), PARAM_INT);
if (!isset($scopes[$scopeid])) {
    access::fail();
}
$scope = access::scope($scopeid);
$states = ui::options(['draft', 'review', 'queued', 'running', 'complete', 'cancelled']);
$state = optional_param('state', '', PARAM_ALPHA);
$page = max(0, min(10000, optional_param('page', 0, PARAM_INT)));
$conditions = ['scopeid' => $scopeid];
if (isset($states[$state])) {
    $conditions['state'] = $state;
}
ui::page('index', compact('scopeid', 'state', 'page'), $scope);
echo $OUTPUT->header();
echo html_writer::start_div('skillups-comm');
echo $OUTPUT->heading(ui::t('pluginname'), 2);
echo html_writer::start_div('sc-actions');
if (access::can($scope, 'compose')) {
    echo ui::link(ui::url('edit', compact('scopeid')), ui::t('newcampaign'), 'btn btn-primary');
}
if (access::can($scope, 'configure') || access::can($scope, 'compose')) {
    echo ui::link(ui::url('catalog', compact('scopeid')), ui::t('catalog'), 'btn btn-outline-secondary');
}
echo html_writer::end_div();
echo html_writer::start_tag('form', ['method' => 'get', 'action' => ui::url()->out(false), 'class' => 'sc-actions']);
foreach (['scopeid' => [$scopes, $scopeid], 'state' => [['' => ui::t('any')] + $states, $state]] as $key => [$choices, $value]) {
    echo html_writer::div(html_writer::label(ui::t($key), 'sc-' . $key, false) .
        html_writer::select($choices, $key, $value, false, ['id' => 'sc-' . $key, 'class' => 'form-select']));
}
echo html_writer::tag('button', ui::t('filter'), ['type' => 'submit', 'class' => 'btn btn-outline-primary']);
echo html_writer::end_tag('form');
$summary = [];
foreach (['draft', 'review', 'queued', 'complete'] as $key) {
    $summary[] = ['label' => $states[$key], 'count' => $DB->count_records(store::CAMP, ['scopeid' => $scopeid, 'state' => $key])];
}
echo $OUTPUT->render_from_template('local_skillupscommunication/summary', ['items' => $summary]);
$rows = [];
foreach ($DB->get_records(store::CAMP, $conditions, 'updated DESC, id DESC', '*', $page * 30, 30) as $campaign) {
    $rows[] = [
        ui::link(ui::url('view', ['id' => $campaign->id]), $campaign->subject), ui::t($campaign->kind),
        ui::t($campaign->state), $campaign->sendat ? s(userdate($campaign->sendat)) : ui::t('immediate'),
        s(userdate($campaign->updated)),
    ];
}
echo ui::table(['subject', 'kind', 'state', 'sendat', 'updated'], $rows);
echo $OUTPUT->paging_bar($DB->count_records(store::CAMP, $conditions), $page, 30, ui::url('index', compact('scopeid', 'state')));
echo html_writer::end_div();
echo $OUTPUT->footer();
