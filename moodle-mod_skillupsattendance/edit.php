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
 * Create, reschedule or revise a session with Moodle Forms.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\form\session as session_form;
use mod_skillupsattendance\session;
use mod_skillupsattendance\store;

$id = required_param('id', PARAM_INT);
$sid = max(0, optional_param('session', 0, PARAM_INT));
$activity = activity::open($id);
$activity->require('sessions');
$current = $sid ? store::session($activity, $sid) : null;
if ($current && (!$activity->staff_group($current->groupid) || $current->state !== 'scheduled')) {
    activity::fail('unavailable');
}
if (!$activity->groups()) {
    activity::fail('badgroup');
}
$url = $activity->url('edit', array_filter(['session' => $sid]));
$title = activity::text($current ? 'editsession' : 'addsession');
$activity->page($url, $title);
$form = new session_form($url, [
    'activity' => $activity, 'id' => $id, 'session' => $sid, 'revision' => $current->revision ?? 0,
]);
if ($current) {
    unset($current->pin, $current->qrkey);
    $current->session = $sid;
    unset($current->id);
    $form->set_data($current);
} else {
    $start = (int)(ceil(time() / 300) * 300);
    $form->set_data((object)['starts' => $start, 'ends' => $start + HOURSECS, 'teacherid' => $USER->id, 'method' => 'manual']);
}
if ($form->is_cancelled()) {
    redirect($sid ? $activity->url('session', ['session' => $sid]) : $activity->url());
}
if ($data = $form->get_data()) {
    $data->session = $sid;
    $data->teacherid ??= 0;
    $data->frequency ??= 'week';
    try {
        $saved = session::save($activity, $data);
        $target = $activity->url('session', ['session' => $saved]);
        redirect($target, activity::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $error) {
        if ($error->module !== activity::C) {
            throw $error;
        }
        \core\notification::error($error->getMessage());
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
