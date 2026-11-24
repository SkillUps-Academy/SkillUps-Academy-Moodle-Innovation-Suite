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
 * Show attendance history within the viewer's authorized groups.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\licensing\entitlements;
use mod_skillupsattendance\report;
use mod_skillupsattendance\store;

$activity = activity::open(required_param('id', PARAM_INT));
$activity->require('audit');
$url = $activity->url('audit');
$activity->page($url, activity::text('audit'));
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$conditions = 'a.attendanceid = :instance';
$params = ['instance' => $activity->record->id];
if (!$activity->staff_group(0)) {
    $groups = array_keys($activity->groups());
    if ($groups) {
        [$in, $args] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'allowedgroup');
        $conditions .= " AND s.groupid $in";
        $params += $args;
    } else {
        $conditions .= ' AND 1 = 0';
    }
}
$table = store::AUDIT;
$sessions = store::SESSION;
$from = "FROM {{$table}} a LEFT JOIN {{$sessions}} s ON s.id = a.sessionid WHERE $conditions";
$license = entitlements::status();
$csv = \core_plugin_manager::instance()->get_plugin_info('dataformat_csv');
$download = $activity->can('export') && $csv && $csv->is_enabled() && in_array('extended_evidence', $license['features'], true);
if (data_submitted()) {
    require_sesskey();
    if (!$download || required_param('action', PARAM_ALPHA) !== 'export') {
        activity::fail('unavailable');
    }
    store::audit($activity, 'exported');
    $iterator = (static function () use ($DB, $from, $params): Generator {
        $records = $DB->get_recordset_sql("SELECT a.* $from ORDER BY a.id ASC", $params);
        try {
            foreach ($records as $record) {
                yield [
                    $record->id, gmdate(DATE_ATOM, $record->timecreated), report::safe_cell($record->operation),
                    $record->sessionid, $record->actorid, $record->userid,
                    report::safe_cell($record->beforestate ?? ''), report::safe_cell($record->afterstate ?? ''),
                ];
            }
        } finally {
            $records->close();
        }
    })();
    $columns = ['event_id', 'recorded_utc', 'operation', 'session_id', 'operator_id', 'student_id', 'previous', 'result'];
    \core\dataformat::download_data('skillups-attendance-audit', 'csv', $columns, $iterator);
    exit;
}
$rows = [];
foreach ($DB->get_records_sql("SELECT a.* $from ORDER BY a.id DESC", $params, $page * 25, 25) as $row) {
    $rows[] = [
        'when' => userdate($row->timecreated), 'operation' => activity::text('op_' . $row->operation),
        'actor' => $row->actorid ?: activity::text('system'), 'student' => $row->userid ?: '',
        'session' => $row->sessionid ?: '', 'before' => $row->beforestate, 'after' => $row->afterstate,
    ];
}
echo $OUTPUT->header();
echo $OUTPUT->heading(activity::text('audit'));
echo $OUTPUT->render_from_template('mod_skillupsattendance/audit', [
    'back' => $activity->url()->out(false), 'rows' => $rows, 'download' => $download,
    'url' => $url->out(false), 'sesskey' => sesskey(), 'edition' => activity::text('edition_' . $license['state']),
    'paging' => $OUTPUT->paging_bar($DB->count_records_sql("SELECT COUNT(a.id) $from", $params), $page, 25, $url),
]);
echo $OUTPUT->footer();
