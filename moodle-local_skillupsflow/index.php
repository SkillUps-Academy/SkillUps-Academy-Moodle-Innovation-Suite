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
 * SkillUps Flow Automation index.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsflow\store;
use local_skillupsflow\ui;
use local_skillupsflow\licensing\entitlements;

require_login();
require_capability('local/skillupsflow:manage', context_system::instance());
admin_externalpage_setup(ui::C . '_dashboard');
$license = entitlements::status();
$exportable = in_array('aggregate_export', $license['features'], true);
$stats = $DB->get_records_sql("SELECT status, COUNT(*) AS total FROM {local_skillupsflow_run}
    WHERE timecreated >= ? GROUP BY status", [time() - 30 * DAYSECS]);

if (data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'exportstats' && $exportable) {
        $export = array_map(static fn($row) => [
            'status' => get_string('state_' . $row->status, ui::C), 'total' => (int)$row->total,
        ], array_values($stats));
        \core\dataformat::download_data('skillupsflow', 'csv', [
            'status' => get_string('status', ui::C), 'total' => get_string('count', ui::C),
        ], $export);
        exit;
    }
    $id = required_param('id', PARAM_INT);
    if ($action === 'activate' || $action === 'pause') {
        store::activate($id, $action === 'activate' ? required_param('revisionid', PARAM_INT) : 0);
        redirect(ui::url(), get_string($action === 'activate' ? 'activated' : 'paused', ui::C));
    }
    if ($action === 'run') {
        $nonce = required_param('nonce', PARAM_ALPHANUM);
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $nonce)) {
            throw new moodle_exception('invaliddefinition', ui::C);
        }
        $run = store::admit($id, 'manual', $nonce);
        if (!$run) {
            throw new moodle_exception('rununavailable', ui::C);
        }
        redirect(ui::url('history', ['runid' => $run]));
    }
}
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$limit = 20;
$sql = "SELECT f.*, v.revision AS latestrevision, a.revision AS activerevision
          FROM {local_skillupsflow_flow} f
          JOIN {local_skillupsflow_ver} v ON v.id = f.latest
     LEFT JOIN {local_skillupsflow_ver} a ON a.id = f.active
      ORDER BY f.timemodified DESC, f.id DESC";
$rows = [];
foreach ($DB->get_records_sql($sql, [], $page * $limit, $limit) as $flow) {
    $rows[] = [
        'id' => $flow->id, 'name' => $flow->name, 'active' => (bool)$flow->active,
        'newrevision' => (int)$flow->latest !== (int)$flow->active,
        'published' => $flow->activerevision ?: '—', 'draft' => $flow->latestrevision, 'revisionid' => $flow->latest,
        'trigger' => get_string('trigger_' . $flow->triggertype, ui::C),
        'nonce' => bin2hex(random_bytes(16)),
        'edit' => ui::url('edit', ['id' => $flow->id])->out(false),
        'history' => ui::url('history', ['flowid' => $flow->id])->out(false),
    ];
}
echo $OUTPUT->header();
echo ui::navigation('index');
echo $OUTPUT->render_from_template(ui::C . '/dashboard', [
    'rows' => $rows, 'new' => ui::url('edit')->out(false), 'url' => ui::url()->out(false), 'sesskey' => sesskey(),
    'completed' => $stats['completed']->total ?? 0, 'failed' => $stats['failed']->total ?? 0,
    'waiting' => $stats['approval']->total ?? 0,
    'exportable' => $exportable, 'edition' => get_string('edition_' . $license['state'], ui::C),
    'expiry' => $license['expires'] ? userdate($license['expires']) : '',
    'siteid' => entitlements::site_id(),
    'paging' => $OUTPUT->paging_bar($DB->count_records('local_skillupsflow_flow'), $page, $limit, ui::url()),
]);
echo $OUTPUT->footer();
