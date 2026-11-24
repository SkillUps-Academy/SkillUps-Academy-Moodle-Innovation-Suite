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
 * Report and export scoped attendance, and explicitly publish authorized grades.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\grades;
use mod_skillupsattendance\report;
use mod_skillupsattendance\session;
use mod_skillupsattendance\store;

$activity = activity::open(required_param('id', PARAM_INT));
$staff = $activity->can('report');
if (!$staff) {
    $activity->require('recordown');
}
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$view = [
    'coursewide' => $staff ? optional_param('coursewide', 0, PARAM_BOOL) : 0,
    'groupid' => max(0, optional_param('groupid', 0, PARAM_INT)),
    'teacherid' => $staff ? max(0, optional_param('teacherid', 0, PARAM_INT)) : 0,
    'studentid' => $staff ? max(0, optional_param('studentid', 0, PARAM_INT)) : 0,
    'modality' => optional_param('modality', '', PARAM_ALPHA),
    'from' => optional_param('from', '', PARAM_RAW_TRIMMED),
    'to' => optional_param('to', '', PARAM_RAW_TRIMMED),
    'riskonly' => $staff ? optional_param('riskonly', 0, PARAM_BOOL) : 0,
];
$view['modality'] = in_array($view['modality'], session::MODALITIES, true) ? $view['modality'] : '';
$url = $activity->url('report', array_filter($view));
$activity->page($url, activity::text('report'));
$params = $view;
$params['from'] = report::day($view['from']);
$params['to'] = report::day($view['to'], true);
if ($params['from'] && $params['to'] && $params['from'] >= $params['to']) {
    activity::fail('badschedule');
}
$formatted = [];
$canexport = $staff && $activity->can('export');
$formats = [];
foreach (['csv', 'excel'] as $format) {
    $plugin = \core_plugin_manager::instance()->get_plugin_info('dataformat_' . $format);
    if ($canexport && $plugin && $plugin->is_enabled()) {
        $formats[] = ['value' => $format, 'label' => activity::text('export_' . $format)];
    }
}
if (data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'publish') {
        if (!optional_param('confirmed', 0, PARAM_BOOL)) {
            activity::fail('confirm');
        }
        $result = grades::publish($activity);
        redirect($url, activity::text('gradepublished', (object)$result), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action !== 'export') {
        activity::fail('unavailable');
    }
}
$rows = report::overview($activity, $params, $staff ? 0 : (int)$USER->id);
foreach ($rows as $row) {
    if (($view['studentid'] && $row['userid'] != $view['studentid']) || ($view['riskonly'] && !$row['flags'])) {
        continue;
    }
    $row['rate'] = $row['percent'] === null ? activity::text('norate') : format_float($row['percent'], 2) . '%';
    $row['risk'] = implode(', ', array_map(static fn(string $flag): string => activity::text('risk_' . $flag), $row['flags']));
    $formatted[] = $row;
}
if (isset($action) && $action === 'export') {
    $activity->require('export');
    $format = required_param('format', PARAM_ALPHA);
    if (!in_array($format, array_column($formats, 'value'), true)) {
        activity::fail('unavailable');
    }
    $keys = ['userid', 'student', 'state_present', 'state_late', 'state_absent', 'state_excused', 'percent', 'risk'];
    $columns = array_map(static fn(string $key): string => activity::text($key), $keys);
    $iterator = (static function () use ($formatted): Generator {
        foreach ($formatted as $row) {
            yield [
                $row['userid'], report::safe_cell($row['name']), $row['present'], $row['late'], $row['absent'],
                $row['excused'], $row['percent'], report::safe_cell($row['risk']),
            ];
        }
    })();
    store::audit($activity, 'exported');
    \core\dataformat::download_data('skillups-attendance-report', $format, $columns, $iterator);
    exit;
}
$options = [];
foreach (session::MODALITIES as $mode) {
    $options[] = ['value' => $mode, 'label' => activity::text('modality_' . $mode), 'selected' => $mode === $view['modality']];
}
$groups = [];
foreach ($activity->groups() as $id => $name) {
    if ($id && ($staff || $activity->eligible($USER->id, $id))) {
        $groups[] = ['value' => $id, 'label' => $name, 'selected' => $id == $view['groupid']];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(activity::text('report'));
echo $OUTPUT->render_from_template('mod_skillupsattendance/report', $view + [
    'id' => $activity->cm->id, 'back' => $activity->url()->out(false), 'sesskey' => sesskey(),
    'url' => $url->out(false), 'base' => $activity->url('report')->out_omit_querystring(), 'staff' => $staff,
    'grades' => grades::allowed($activity), 'formats' => $formats,
    'groupoptions' => $groups, 'modalityoptions' => $options,
    'count' => count($formatted), 'atrisks' => count(array_filter(array_column($formatted, 'flags'))),
    'rows' => array_slice($formatted, $page * 25, 25),
    'paging' => $OUTPUT->paging_bar(count($formatted), $page, 25, $url),
]);
echo $OUTPUT->footer();
