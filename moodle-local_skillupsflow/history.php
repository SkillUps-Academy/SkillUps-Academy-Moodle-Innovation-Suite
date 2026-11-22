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
 * SkillUps Flow Automation history.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsflow\engine;
use local_skillupsflow\store;
use local_skillupsflow\ui;

require_login();
require_capability('local/skillupsflow:manage', context_system::instance());
$flowid = max(0, optional_param('flowid', 0, PARAM_INT));
$runid = max(0, optional_param('runid', 0, PARAM_INT));
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$size = 25;
$url = ui::url('history', ['runid' => $runid, 'flowid' => $flowid]);
ui::page('history', 'history', ['runid' => $runid, 'flowid' => $flowid]);
if ($runid && data_submitted() && optional_param('cancelrun', 0, PARAM_BOOL)) {
    require_sesskey();
    engine::cancel($runid);
    redirect($url);
}
$sql = "SELECT r.*, f.name, v.revision
          FROM {local_skillupsflow_run} r
          JOIN {local_skillupsflow_flow} f ON f.id = r.flowid
          JOIN {local_skillupsflow_ver} v ON v.id = r.versionid";
$data = ['url' => $url->out(false), 'sesskey' => sesskey(), 'back' => ui::url('history')->out(false)];
if ($runid) {
    $run = $DB->get_record_sql($sql . ' WHERE r.id = :id', ['id' => $runid], MUST_EXIST);
    $logs = $DB->get_records('local_skillupsflow_log', ['runid' => $runid], 'id', '*', $page * $size, $size);
    $data += [
        'detail' => true, 'name' => $run->name, 'id' => $run->id, 'revision' => $run->revision,
        'status' => get_string('state_' . $run->status, ui::C), 'canstop' => in_array($run->status, store::OPEN, true),
        'userid' => $run->userid ?: '—', 'courseid' => $run->courseid ?: '—',
        'due' => $run->due ? userdate($run->due) : '—',
        'records' => array_values(array_map(static fn($log) => [
            'node' => $log->node ?: '—', 'outcome' => get_string('outcome_' . $log->outcome, ui::C),
            'actor' => $log->actorid ?: '—', 'date' => userdate($log->timecreated),
        ], $logs)),
        'paging' => $OUTPUT->paging_bar($DB->count_records('local_skillupsflow_log', ['runid' => $runid]), $page, $size, $url),
    ];
} else {
    $status = optional_param('status', '', PARAM_ALPHA);
    $options = ['', 'queued', 'sending', 'approval', 'completed', 'failed', 'cancelled'];
    $status = in_array($status, $options, true) ? $status : '';
    $filters = [];
    if ($flowid) {
        $filters['flowid'] = $flowid;
    }
    if ($status) {
        $filters['status'] = $status;
    }
    $clauses = array_map(static fn($field) => "r.$field = :$field", array_keys($filters));
    $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    $runs = $DB->get_records_sql($sql . $where . ' ORDER BY r.id DESC', $filters, $page * $size, $size);
    $data += [
        'filters' => array_map(static fn($option) => [
            'value' => $option, 'label' => get_string($option ? 'state_' . $option : 'all', ui::C),
            'selected' => $option === $status,
        ], $options),
        'flowid' => $flowid ?: '', 'filterurl' => ui::url('history')->out(false),
        'runs' => array_values(array_map(static fn($run) => [
            'id' => $run->id, 'name' => $run->name, 'revision' => $run->revision,
            'status' => get_string('state_' . $run->status, ui::C), 'date' => userdate($run->timecreated),
            'url' => ui::url('history', ['runid' => $run->id])->out(false),
        ], $runs)),
        'paging' => $OUTPUT->paging_bar(
            $DB->count_records('local_skillupsflow_run', $filters),
            $page,
            $size,
            ui::url('history', ['flowid' => $flowid, 'status' => $status])
        ),
    ];
}
echo $OUTPUT->header();
echo ui::navigation('history');
echo $OUTPUT->render_from_template(ui::C . '/history', $data);
echo $OUTPUT->footer();
