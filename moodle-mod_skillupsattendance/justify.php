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
 * Submit an attendance justification using the Moodle draft file area.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\form\justification as justification_form;
use mod_skillupsattendance\justification;
use mod_skillupsattendance\store;

$id = required_param('id', PARAM_INT);
$sid = required_param('session', PARAM_INT);
$activity = activity::open($id);
$activity->require('recordown');
$record = store::session($activity, $sid);
if (!$activity->eligible((int)$USER->id, $record->groupid) || time() < $record->ends || $record->state === 'cancelled') {
    activity::fail('justunavailable');
}
$url = $activity->url('justify', ['session' => $sid]);
$title = activity::text('submitjust');
$activity->page($url, $title);
$form = new justification_form($url, ['activity' => $activity, 'id' => $id, 'session' => $sid]);
if ($form->is_cancelled()) {
    redirect($activity->url('session', ['session' => $sid]));
}
if ($data = $form->get_data()) {
    try {
        $request = justification::submit($activity, $sid, $data->reason, $data->statement ?? '', (int)$data->evidence);
        $target = $activity->url('review', ['request' => $request]);
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
echo $OUTPUT->box(s($record->name));
$form->display();
echo $OUTPUT->footer();
