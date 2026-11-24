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
 * Display one session, the visible roster and its justification history.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\marks;
use mod_skillupsattendance\session;
use mod_skillupsattendance\store;

$activity = activity::open(required_param('id', PARAM_INT));
$sid = required_param('session', PARAM_INT);
$record = store::session($activity, $sid);
if (!session::visible($activity, $record)) {
    activity::fail('unavailable');
}
$page = max(0, min(10000, optional_param('page', 0, PARAM_INT)));
$jpage = max(0, min(10000, optional_param('jpage', 0, PARAM_INT)));
$url = $activity->url('session', ['session' => $sid]);
$activity->page($url, $record->name);
$staff = $activity->staff_group($record->groupid);
$canmark = $staff && $activity->can('mark');
$manage = $staff && $activity->can('sessions');
$review = $staff && $activity->can('review');
$student = $activity->eligible($USER->id, $record->groupid);
if (data_submitted()) {
    require_sesskey();
    try {
        switch (required_param('action', PARAM_ALPHA)) {
            case 'mark':
                marks::manual($activity, $sid, optional_param_array('states', [], PARAM_ALPHA));
                break;
            case 'finalize':
                if (!marks::finalize($activity, $sid)) {
                    activity::fail('closedwindow');
                }
                break;
            case 'cancel':
                if (!required_param('confirmed', PARAM_BOOL)) {
                    activity::fail('confirm');
                }
                session::cancel($activity, $sid);
                break;
            default:
                activity::fail('unavailable');
        }
        redirect(
            $activity->url('session', ['session' => $sid, 'page' => $page]),
            activity::text('saved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS,
        );
    } catch (moodle_exception $error) {
        if ($error->module !== activity::C) {
            throw $error;
        }
        \core\notification::error($error->getMessage());
    }
    $record = store::session($activity, $sid);
}
$now = time();
$open = session::is_open($record, $now);
$scheduled = $record->state === 'scheduled';
$cancelled = $record->state === 'cancelled';
$afterclose = $now >= $record->ends + $record->grace * MINSECS;
$released = $canmark && !$cancelled && $now >= $record->starts - $record->early * MINSECS;
$mine = $student ? $DB->get_record(store::MARK, ['sessionid' => $sid, 'userid' => $USER->id]) : null;
$roster = $canmark ? $activity->roster($record->groupid, $page * 25, 26) : [];
$more = count($roster) > 25;
$roster = array_slice($roster, 0, 25, true);
$stored = [];
if ($roster) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($roster), SQL_PARAMS_NAMED, 'person');
    $stored = $DB->get_records_select(
        store::MARK,
        "sessionid = :session AND userid $insql",
        $params + ['session' => $sid],
        '',
        'userid,id,state,timein,timeexit'
    );
}
$students = [];
foreach ($roster as $person) {
    $mark = $stored[$person->id] ?? null;
    $state = $mark->state ?? 'pending';
    $states = $record->state === 'final' ? array_diff(marks::STATES, ['pending']) : marks::STATES;
    $options = array_map(static fn(string $value): array => [
        'value' => $value, 'label' => activity::text('state_' . $value), 'selected' => $state === $value,
    ], array_values($states));
    $students[] = [
        'userid' => $person->id, 'name' => $person->firstname . ' ' . $person->lastname,
        'state' => activity::text('state_' . $state), 'options' => $options,
        'roweditable' => $released && ($mark || $record->state !== 'final'),
        'timein' => empty($mark->timein) ? '' : userdate($mark->timein),
        'timeexit' => empty($mark->timeexit) ? '' : userdate($mark->timeexit),
    ];
}
$requests = [];
$total = 0;
if ($review || $student) {
    $scope = $review ? ['sessionid' => $sid] : ['sessionid' => $sid, 'userid' => $USER->id];
    $total = $DB->count_records(store::JUST, $scope);
    foreach ($DB->get_records(store::JUST, $scope, 'id DESC', '*', $jpage * 20, 20) as $request) {
        $requests[] = [
            'id' => $request->id, 'userid' => $request->userid, 'reason' => $request->reason,
            'state' => activity::text('just_' . $request->state), 'date' => userdate($request->timecreated),
            'url' => $activity->url('review', ['request' => $request->id])->out(false),
        ];
    }
}
$studentstate = $mine->state ?? 'pending';
$extras = [
    'qr' => $manage && $open && $record->method === 'qr' ? $activity->url('qr', ['session' => $sid, 't' => $now])->out(false) : '',
    'scan' => $student && $open && $record->method === 'qr' && $studentstate === 'pending',
    'checkin' => $student && $open && in_array($record->method, ['open', 'code'], true) && $studentstate === 'pending'
        ? $activity->url('checkin', ['session' => $sid])->out(false) : '',
    'exit' => $student && $open && $record->method !== 'manual' && $record->checkout && !empty($mine->timein)
        && empty($mine->timeexit) && in_array($studentstate, ['present', 'late'], true),
    'justify' => $student && !$cancelled && $now >= $record->ends && in_array($studentstate, ['absent', 'pending', 'late'], true)
        ? $activity->url('justify', ['session' => $sid])->out(false) : '',
];
echo $OUTPUT->header();
echo $OUTPUT->heading(s($record->name));
echo $OUTPUT->render_from_template('mod_skillupsattendance/session', $extras + [
    'back' => $activity->url()->out(false), 'actionurl' => $url->out(false),
    'edit' => $manage && $scheduled ? $activity->url('edit', ['session' => $sid])->out(false) : '',
    'state' => activity::text('session_' . $record->state), 'modality' => activity::text('modality_' . $record->modality),
    'method' => activity::text('method_' . $record->method), 'group' => $activity->groups()[$record->groupid] ?? '',
    'starts' => userdate($record->starts), 'ends' => userdate($record->ends),
    'duration' => format_time($record->ends - $record->starts),
    'student' => $student, 'studentstate' => activity::text('state_' . $studentstate),
    'entrytime' => empty($mine->timein) ? '' : userdate($mine->timein),
    'exittime' => empty($mine->timeexit) ? '' : userdate($mine->timeexit),
    'exiturl' => $activity->url('checkin', ['session' => $sid])->out(false),
    'canmark' => $canmark, 'saveable' => in_array(true, array_column($students, 'roweditable'), true),
    'finalize' => $canmark && $scheduled && $afterclose,
    'cancel' => $manage && !$cancelled, 'students' => $students, 'page' => $page, 'sesskey' => sesskey(),
    'previous' => $page ? $activity->url('session', ['session' => $sid, 'page' => $page - 1])->out(false) : '',
    'next' => $more ? $activity->url('session', ['session' => $sid, 'page' => $page + 1])->out(false) : '',
    'showrequests' => $review || $student, 'requests' => $requests,
    'requestpaging' => $OUTPUT->paging_bar($total, $jpage, 20, $url, 'jpage'),
]);
echo $OUTPUT->footer();
