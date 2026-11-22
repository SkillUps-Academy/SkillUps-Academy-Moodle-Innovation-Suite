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
 * SkillUps Flow Automation approvals.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsflow\definition;
use local_skillupsflow\engine;
use local_skillupsflow\ui;

require_login();
require_capability('local/skillupsflow:approve', context_system::instance());
admin_externalpage_setup(ui::C . '_approvals');
$url = ui::url('approvals');
if (data_submitted()) {
    require_sesskey();
    $choice = required_param('decision', PARAM_ALPHA);
    if (!in_array($choice, ['approve', 'reject'], true)) {
        throw new moodle_exception('decisionunavailable', ui::C);
    }
    engine::decide(required_param('runid', PARAM_INT), $choice === 'approve');
    redirect($url, get_string('decisionsaved', ui::C));
}
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$params = ['status' => 'approval', 'due' => time(), 'actor' => $USER->id];
$from = "FROM {local_skillupsflow_run} r
         JOIN {local_skillupsflow_flow} f ON f.id = r.flowid
         JOIN {local_skillupsflow_ver} v ON v.id = r.versionid
        WHERE r.status = :status AND r.due > :due AND f.active > 0 AND v.userid <> :actor";
$rows = [];
foreach (
    $DB->get_records_sql(
        "SELECT r.*, f.name, v.definition, v.revision $from ORDER BY r.due, r.id",
        $params,
        $page * 25,
        25
    ) as $run
) {
    $nodes = array_column(definition::parse($run->definition)['nodes'], null, 'id');
    $rows[] = [
        'id' => $run->id, 'name' => $run->name, 'label' => $nodes[$run->node]['label'] ?: get_string('node_approval', ui::C),
        'revision' => $run->revision, 'userid' => $run->userid ?: '—', 'courseid' => $run->courseid ?: '—',
        'due' => userdate($run->due),
    ];
}
echo $OUTPUT->header();
echo ui::navigation('approvals');
echo $OUTPUT->render_from_template(ui::C . '/approvals', [
    'rows' => $rows, 'url' => $url->out(false), 'sesskey' => sesskey(),
    'paging' => $OUTPUT->paging_bar($DB->count_records_sql("SELECT COUNT(*) $from", $params), $page, 25, $url),
]);
echo $OUTPUT->footer();
