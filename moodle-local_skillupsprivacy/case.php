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
 * Review a privacy case and its authorized transition history.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsprivacy\access;
use local_skillupsprivacy\bridge;
use local_skillupsprivacy\dashboard;
use local_skillupsprivacy\form\routing;
use local_skillupsprivacy\monitor;
use local_skillupsprivacy\store;
use local_skillupsprivacy\workflow;

require_login();
access::require();
$id = required_param('id', PARAM_INT);
$page = max(0, min(1000000, optional_param('page', 0, PARAM_INT)));
$url = access::url('case', ['id' => $id]);
admin_externalpage_setup('skillupsprivacy', '', null, $url);
$PAGE->set_cacheable(false);
$case = store::get($id);
if ($case->archived) {
    throw new moodle_exception('archived', access::C);
}
$editable = $case->state !== 'closed';
$manage = access::allowed($USER->id, 'manage');
$decide = access::allowed($USER->id, 'decide');
$form = $editable && $manage ? new routing($url, ['revision' => $case->revision]) : null;
if ($form) {
    $form->set_data($case);
}
if (data_submitted()) {
    require_sesskey();
    try {
        if ($submission = $form?->get_data()) {
            workflow::route($id, $submission->revision, $submission->ownerid, $submission->due);
        } else if ($action = optional_param('action', '', PARAM_ALPHA)) {
            switch ($action) {
                case 'transition':
                    workflow::transition(
                        $id,
                        required_param('revision', PARAM_INT),
                        required_param('value', PARAM_ALPHA),
                        required_param('reason', PARAM_TEXT)
                    );
                    break;
                case 'retention':
                    workflow::assess(
                        $id,
                        required_param('revision', PARAM_INT),
                        required_param('value', PARAM_ALPHA),
                        required_param('reason', PARAM_TEXT)
                    );
                    break;
                case 'refresh':
                    access::require('manage');
                    monitor::refresh($id);
                    store::audit('refresh', $id);
                    break;
                default:
                    throw new moodle_exception('invaliddecision', access::C);
            }
        }
        if (isset($submission) || !empty($action)) {
            redirect($url, access::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $error) {
        if ($error->module !== access::C) {
            throw $error;
        }
        \core\notification::error($error->getMessage());
    }
}
store::atomic(static function () use ($id): void {
    if (store::get($id)->archived) {
        throw new moodle_exception('archived', access::C);
    }
    store::audit('viewcase', $id);
});
$context = bridge::context((int)$case->contextid, (int)$case->userid);
$history = [];
$historywhere = 'caseid = :caseid AND operation <> :view';
$historyparams = ['caseid' => $id, 'view' => 'viewcase'];
$records = $DB->get_records_select(
    store::AUDIT,
    $historywhere,
    $historyparams,
    'id DESC',
    '*',
    $page * dashboard::PAGE,
    dashboard::PAGE
);
foreach ($records as $row) {
    $before = $row->previous;
    $after = $row->result;
    if ($row->operation === 'transition' || $row->operation === 'retention') {
        $prefix = $row->operation === 'transition' ? 'state_' : 'retention_';
        $before = access::text($prefix . $before);
        $after = access::text($prefix . $after);
    } else if ($row->operation === 'synced') {
        $before = dashboard::native_status((int)$before);
        $after = dashboard::native_status((int)$after);
    } else if ($row->operation === 'routed') {
        $before = userdate((int)$before) . ' #' . $row->priorid;
        $after = userdate((int)$after) . ' #' . $row->targetid;
    } else if ($row->operation === 'notified') {
        $after = access::text('notice_' . $after);
    }
    $history[] = [
        'when' => userdate($row->timecreated), 'actor' => $row->actorid ?: access::text('systemactor'),
        'operation' => access::text('op_' . $row->operation), 'previous' => $before, 'result' => $after, 'note' => $row->note,
    ];
}
$next = array_values(array_filter(workflow::next($case->state), static fn(string $state): bool =>
    $state === 'review' ? $manage : $decide));
$historycount = $DB->count_records_select(store::AUDIT, $historywhere, $historyparams);
$data = [
    'id' => $id, 'revision' => $case->revision, 'userid' => $case->userid, 'ownerid' => $case->ownerid,
    'back' => access::url()->out(false), 'actionurl' => $url->out(false), 'sesskey' => sesskey(),
    'kind' => access::text('kind_' . $case->kind), 'state' => access::text('state_' . $case->state),
    'retention' => access::text('retention_' . $case->retention), 'deadline' => userdate($case->due),
    'contextname' => $context ? $context->get_context_name(false, true, false) : access::text('notrecorded'),
    'purpose' => $context ? bridge::retention($context) : null,
    'nativeid' => $case->nativeid, 'nativeurl' => bridge::url('datarequests')->out(false),
    'nativestatus' => dashboard::native_status((int)$case->nativestatus),
    'checkedat' => $case->checkedat ? userdate($case->checkedat) : '',
    'refresh' => $manage && $case->nativeid,
    'transitions' => dashboard::options('state', $next), 'hastransitions' => !empty($next),
    'canassess' => $editable && $decide, 'assessments' => dashboard::options('retention', workflow::RETENTION, $case->retention),
    'history' => $history, 'hasrouting' => $form !== null, 'routing' => $form?->render(),
    'paging' => $OUTPUT->paging_bar($historycount, $page, dashboard::PAGE, $url),
];
echo $OUTPUT->header();
echo $OUTPUT->heading(access::text('casedetail', $id));
echo $OUTPUT->render_from_template('local_skillupsprivacy/case', $data);
echo $OUTPUT->footer();
