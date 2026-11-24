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
 * Dashboard for integrations, monitoring and audited run history.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsintegration\access;
use local_skillupsintegration\connector\factory;
use local_skillupsintegration\problem;
use local_skillupsintegration\runs;
use local_skillupsintegration\store;

require_login();
access::require('view');
$system = context_system::instance();
$url = access::url();
admin_externalpage_setup('skillupsintegration', '', null, $url);
$PAGE->set_cacheable(false);
$canmanage = has_capability(access::CAP . 'manage', $system);
$canpreview = has_capability(access::CAP . 'preview', $system);
$sourceid = max(0, optional_param('sourceid', 0, PARAM_INT));
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$view = optional_param('view', 'runs', PARAM_ALPHA) === 'audit' ? 'audit' : 'runs';

if (data_submitted()) {
    require_sesskey();
    try {
        if (required_param('action', PARAM_ALPHA) !== 'preview') {
            throw new problem('unavailable');
        }
        $id = runs::preview($sourceid);
        redirect(access::url('run', ['id' => $id]));
    } catch (problem $failure) {
        \core\notification::error($failure->getMessage());
    }
}
$sources = [];
$profiles = $DB->get_records(store::SRC, null, 'name ASC, id ASC');
foreach ($profiles as $source) {
    $driver = factory::make($source);
    $sources[] = [
        'id' => $source->id, 'name' => $source->name, 'kind' => access::text('type_' . $source->type),
        'enabled' => $source->enabled, 'auth' => access::text($driver->authentication()),
        'state' => access::text(in_array($source->health, ['new', 'healthy'], true) ? 'state_' . $source->health
            : 'err_' . $source->health),
        'lastcheck' => $source->lastcheck ? userdate($source->lastcheck) : '',
        'haslatency' => $source->latency >= 0 && $source->lastcheck,
        'latencylabel' => access::text('latency', $source->latency),
        'preview' => $canpreview && $source->enabled && in_array('pull', $driver->capabilities(), true),
        'editurl' => $canmanage ? access::url('connector', ['id' => $source->id])->out(false) : '',
        'history' => access::url('index', ['sourceid' => $source->id])->out(false),
    ];
}
$history = [];
$where = $sourceid ? ['sourceid' => $sourceid] : [];
$isruns = $view === 'runs';
$table = $isruns ? store::RUN : store::AUDIT;
$rows = $DB->get_records($table, $where, 'id DESC', '*', $page * 25, 25);
foreach ($rows as $row) {
    $operation = !$isruns && str_starts_with($row->action, 'err_') ? $row->action : 'op_' . ($row->action ?? '');
    $history[] = [
        'id' => $row->id, 'run' => $isruns ? $row->id : $row->runid,
        'name' => $profiles[$row->sourceid]->name ?? $row->sourceid,
        'time' => userdate($row->timecreated), 'actor' => $row->actorid ?: '',
        'label' => access::text($isruns ? 'state_' . $row->state : $operation),
        'url' => $isruns ? access::url('run', ['id' => $row->id])->out(false) : '',
    ];
}
$params = array_filter(['sourceid' => $sourceid, 'view' => $view]);
$entitlement = \local_skillupsintegration\licensing\entitlements::status();
echo $OUTPUT->header();
echo $OUTPUT->heading(access::text('pluginname'));
echo $OUTPUT->render_from_template('local_skillupsintegration/dashboard', [
    'edition' => access::text('edition_' . $entitlement['state']),
    'expiration' => $entitlement['expires'] ? userdate($entitlement['expires']) : '',
    'licensed' => $canmanage && in_array('extended_evidence', $entitlement['features'], true),
    'exporturl' => access::url('export')->out(false),
    'sources' => $sources, 'history' => $history, 'base' => $url->out(false), 'sesskey' => sesskey(),
    'add' => $canmanage ? access::url('connector')->out(false) : '',
    'isruns' => $isruns, 'filter' => $profiles[$sourceid]->name ?? '',
    'runsurl' => access::url('index', ['sourceid' => $sourceid])->out(false),
    'auditurl' => access::url('index', ['sourceid' => $sourceid, 'view' => 'audit'])->out(false),
    'paging' => $OUTPUT->paging_bar($DB->count_records($table, $where), $page, 25, access::url('index', $params)),
]);
echo $OUTPUT->footer();
