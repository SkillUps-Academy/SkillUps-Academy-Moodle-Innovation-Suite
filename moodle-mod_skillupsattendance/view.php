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
 * Show the schedule, activity calendar and the learner's attendance summary.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\report;
use mod_skillupsattendance\session;
use mod_skillupsattendance\store;

$activity = activity::open(required_param('id', PARAM_INT));
$zone = \core_date::get_user_timezone_object();
$month = optional_param('month', (new DateTimeImmutable('now', $zone))->format('Y-m'), PARAM_RAW_TRIMMED);
if (!preg_match('/^\d{4}-\d{2}$/D', $month)) {
    activity::fail('badschedule');
}
$from = report::day($month . '-01');
$until = (new DateTimeImmutable('@' . $from))->setTimezone($zone)->modify('+1 month')->getTimestamp();
$url = $activity->url('view', ['month' => $month]);
$activity->page($url, $activity->record->name);
$page = max(0, min(10000, optional_param('page', 0, PARAM_INT)));
$records = $DB->get_records_select(
    store::SESSION,
    'attendanceid = :instance AND starts >= :start AND starts < :end',
    ['instance' => $activity->record->id, 'start' => $from, 'end' => $until],
    'starts ASC, id ASC',
);
$seen = [];
$groups = $activity->groups();
foreach ($records as $record) {
    if (session::visible($activity, $record)) {
        $seen[] = [
            'name' => $record->name, 'status' => activity::text('session_' . $record->state),
            'modality' => activity::text('modality_' . $record->modality),
            'group' => $groups[$record->groupid] ?? '',
            'when' => userdate($record->starts), 'end' => userdate($record->ends),
            'extra' => $record->extra, 'url' => $activity->url('session', ['session' => $record->id])->out(false),
        ];
    }
}
$own = [];
if ($activity->can('recordown') && is_enrolled($activity->context, $USER, '', true)) {
    $data = report::overview($activity, [], (int)$USER->id);
    $row = $data[$USER->id] ?? null;
    if ($row) {
        $own = [
            'percent' => $row['percent'] === null ? activity::text('norate') : format_float($row['percent'], 2) . '%',
            'absent' => $row['absent'], 'excused' => $row['excused'], 'late' => $row['late'],
        ];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->record->name));
if (trim($activity->record->intro ?? '') !== '') {
    echo $OUTPUT->box(format_module_intro('skillupsattendance', $activity->record, $activity->cm->id));
}
echo $OUTPUT->render_from_template('mod_skillupsattendance/dashboard', [
    'id' => $activity->cm->id, 'month' => $month, 'base' => $activity->url()->out(false),
    'rows' => array_slice($seen, $page * 25, 25), 'own' => $own ?: false,
    'add' => $activity->can('sessions') ? $activity->url('edit')->out(false) : '',
    'report' => $activity->can('report') || $activity->can('recordown') ? $activity->url('report')->out(false) : '',
    'audit' => $activity->can('audit') ? $activity->url('audit')->out(false) : '',
    'calendar' => (new moodle_url('/calendar/view.php', ['view' => 'month', 'course' => $activity->course->id]))->out(false),
    'paging' => $OUTPUT->paging_bar(count($seen), $page, 25, $url),
]);
echo $OUTPUT->footer();
