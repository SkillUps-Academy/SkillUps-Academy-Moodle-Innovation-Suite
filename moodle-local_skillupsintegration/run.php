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
 * Review a simulation, explicitly approve it and monitor execution.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsintegration\access;
use local_skillupsintegration\problem;
use local_skillupsintegration\profile;
use local_skillupsintegration\runs;
use local_skillupsintegration\store;

require_login();
access::require('view');
$id = required_param('id', PARAM_INT);
$url = access::url('run', ['id' => $id]);
admin_externalpage_setup('skillupsintegration', '', null, $url);
$PAGE->set_cacheable(false);
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
if (data_submitted()) {
    require_sesskey();
    try {
        switch (required_param('action', PARAM_ALPHA)) {
            case 'approve':
                if (!required_param('reviewed', PARAM_BOOL)) {
                    throw new problem('confirm');
                }
                runs::approve($id, required_param('sealed', PARAM_INT));
                break;
            case 'retry':
                runs::retry($id);
                break;
            default:
                throw new problem('unavailable');
        }
        redirect($url, access::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (problem $failure) {
        \core\notification::error($failure->getMessage());
    }
}
$run = runs::get($id);
$source = profile::get($run->sourceid);
$system = context_system::instance();
$canapply = has_capability(access::CAP . 'apply', $system);
$canpreview = has_capability(access::CAP . 'preview', $system);
$active = $source->enabled && $source->revision == $run->sourceversion && $run->timecreated > time() - runs::lifetime();
$planned = $DB->count_records(store::ITEM, ['runid' => $id, 'state' => 'planned']);
$state = optional_param('state', '', PARAM_ALPHA);
$filter = ['runid' => $id];
if (in_array($state, ['planned', 'failed', 'conflict', 'same', 'applied'], true)) {
    $filter['state'] = $state;
    $url->param('state', $state);
}
$rows = [];
$fields = $canpreview || $canapply ? '*' : 'id,entity,decision,state,errorcode';
foreach ($DB->get_records(store::ITEM, $filter, 'id ASC', $fields, $page * 25, 25) as $item) {
    $values = !empty($item->payload) ? json_decode($item->payload, true) : [];
    $labels = [];
    foreach ($values as $field => $value) {
        $labels[] = ['key' => access::text('field_' . $field), 'value' => $value];
    }
    $rows[] = [
        'id' => $item->id, 'entity' => access::text('entity_' . $item->entity),
        'decision' => access::text('decision_' . $item->decision),
        'state' => access::text('state_' . $item->state), 'fields' => $labels,
        'error' => $item->errorcode ? access::text('err_' . $item->errorcode) : '',
    ];
}
$tabs = [];
foreach (['planned', 'conflict', 'failed', 'same', 'applied'] as $value) {
    $tabs[] = [
        'count' => $DB->count_records(store::ITEM, ['runid' => $id, 'state' => $value]),
        'url' => access::url('run', ['id' => $id, 'state' => $value])->out(false),
        'label' => access::text('state_' . $value),
    ];
}
echo $OUTPUT->header();
echo $OUTPUT->heading(access::text('rundetail', $id));
echo $OUTPUT->render_from_template('local_skillupsintegration/run', [
    'base' => access::url()->out(false), 'url' => access::url('run', ['id' => $id])->out(false),
    'sesskey' => sesskey(), 'name' => $source->name, 'state' => access::text('state_' . $run->state),
    'current' => $active, 'origin' => access::text('origin_' . $run->origin), 'when' => userdate($run->timecreated),
    'approvedby' => $run->approverid, 'retries' => $run->attempts, 'records' => $run->records,
    'lasterror' => $run->lasterror ? access::text('err_' . $run->lasterror) : '',
    'next' => in_array($run->state, ['queued', 'applying'], true) && $run->nextat > time() ? userdate($run->nextat) : '',
    'sealed' => $run->sealed, 'seesrows' => $canapply || $canpreview,
    'approve' => $canapply && $active && $planned && $run->state === 'ready',
    'retry' => $active && $run->state === 'failed' && (
        $run->step === 'apply' ? $canapply && $run->approverid == $USER->id : $canpreview),
    'tabs' => $tabs, 'rows' => $rows,
    'paging' => $OUTPUT->paging_bar($DB->count_records(store::ITEM, $filter), $page, 25, $url),
]);
echo $OUTPUT->footer();
