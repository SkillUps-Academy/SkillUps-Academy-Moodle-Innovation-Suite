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
 * Show an authorized session, its calendar information and modification history.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsscheduler\conflicts;
use local_skillupsscheduler\form\cancel;
use local_skillupsscheduler\planner;
use local_skillupsscheduler\schedule;
use local_skillupsscheduler\scope;
use local_skillupsscheduler\store;

require_login();
$id = required_param('id', PARAM_INT);
$slot = store::get(store::SLOT, $id);
if (!scope::visible($slot)) {
    scope::fail();
}
$site = store::get(store::SITE, (int)$slot->siteid);
$editing = $slot->status !== 'cancelled' && scope::staff($site, 'plan') &&
    ($slot->status !== 'published' || scope::staff($site, 'publish'));
$admin = scope::staff($site, 'plan');
$audit = scope::staff($site, 'audit');
$course = get_course($slot->courseid);
scope::setup_page('session', $slot->title, ['id' => $id]);
if (optional_param('cancelconfirm', 0, PARAM_BOOL)) {
    if (!$editing) {
        scope::fail();
    }
    $form = new cancel(
        scope::url('session', ['id' => $id, 'cancelconfirm' => 1]),
        ['id' => $id, 'revision' => $slot->revision, 'cancelconfirm' => 1],
    );
    if ($form->is_cancelled()) {
        redirect(scope::url('session', ['id' => $id]));
    }
    if ($data = $form->get_data()) {
        require_sesskey();
        schedule::cancel($id, (int)$data->revision, $data->reason);
        redirect(scope::url('session', ['id' => $id]), scope::text('saved'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(scope::text('confirmcancel'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}
$user = $slot->teacherid ? core_user::get_user($slot->teacherid) : false;
$room = $slot->virtualid ? store::get(store::ROOM, (int)$slot->virtualid) : null;
$join = $room && $slot->status === 'published' &&
    ($admin || scope::attends($slot, $course, (int)$USER->id));
$detail = [
    'siteid' => format_string($site->name), 'courseid' => format_string($course->fullname),
    'cycleid' => planner::label(store::CYCLE, (int)$slot->cycleid),
    'groupid' => $slot->groupid ? planner::label('groups', (int)$slot->groupid) : scope::text('allgroup'),
    'teacherid' => $user && !$user->deleted ? fullname($user) : scope::text('none'),
    'mode' => scope::text($slot->mode), 'physicalroom' => planner::label(store::ROOM, (int)$slot->roomid),
    'virtualroom' => $room ? format_string($room->name) : '',
    'blockid' => planner::label(store::BLOCK, (int)$slot->blockid),
    'begin' => userdate($slot->starts), 'finish' => userdate($slot->ends),
];
$rows = [];
foreach ($detail as $key => $value) {
    if ($value !== '') {
        $rows[] = ['label' => scope::text($key), 'value' => $value];
    }
}
$history = [];
$historypage = max(0, optional_param('page', 0, PARAM_INT));
$historycount = 0;
if ($audit) {
    $historycount = $DB->count_records(store::AUDIT, ['slotid' => $id]);
    foreach ($DB->get_records(store::AUDIT, ['slotid' => $id], 'id DESC', '*', $historypage * 50, 50) as $row) {
        $actor = $row->actorid ? core_user::get_user($row->actorid) : null;
        $previous = $row->previousdata ? json_decode($row->previousdata) : null;
        $next = $row->nextdata ? json_decode($row->nextdata) : null;
        $history[] = [
            'actor' => $actor && !$actor->deleted ? fullname($actor) : scope::text('unknown'),
            'operation' => scope::text('log_' . $row->operation), 'when' => userdate($row->created),
            'reason' => $row->reason, 'before' => $previous ? userdate($previous->starts) : '',
            'after' => $next ? userdate($next->starts) : '',
        ];
    }
}
$issues = $admin && $slot->status !== 'cancelled' ? conflicts::detect($slot, $slot->status === 'draft') : [];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(scope::C . '/session', [
    'title' => $slot->title, 'status' => scope::text($slot->status), 'details' => $rows,
    'history' => $history, 'historyallowed' => $audit,
    'conflicts' => array_map(static fn($key): array => ['label' => scope::text($key)], $issues),
    'course' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    'join' => $join ? $room->url : '',
    'edit' => $editing ? scope::url('edit', ['id' => $id])->out(false) : '',
    'cancel' => $editing ? scope::url('session', ['id' => $id, 'cancelconfirm' => 1])->out(false) : '',
    'back' => scope::url('index', ['day' => userdate($slot->starts, '%Y-%m-%d')])->out(false),
]);
if ($audit) {
    echo $OUTPUT->paging_bar($historycount, $historypage, 50, scope::url('session', ['id' => $id]));
}
echo $OUTPUT->footer();
