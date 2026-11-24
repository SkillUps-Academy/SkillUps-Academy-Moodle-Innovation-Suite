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
 * Require a trusted Moodle session and explicit confirmation before registering attendance.
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

header('Referrer-Policy: no-referrer');
$activity = activity::open(required_param('id', PARAM_INT));
$activity->require('recordown');
$sid = required_param('session', PARAM_INT);
$record = store::session($activity, $sid);
if (!$activity->eligible($USER->id, $record->groupid) || !session::is_open($record, time()) || $record->method === 'manual') {
    activity::fail('closedwindow');
}
$token = optional_param('qr', '', PARAM_RAW_TRIMMED);
$token = strlen($token) < 64 && preg_match('/^\d{1,12}\.[a-f0-9]{32}$/D', $token) ? $token : '';
$url = $activity->url('checkin', ['session' => $sid]);
$activity->page($url, activity::text('checkin'));
if (data_submitted()) {
    require_sesskey();
    try {
        $pin = optional_param('pin', '', PARAM_RAW_TRIMMED);
        $outcome = marks::self_record($activity, $sid, $pin, $token, optional_param('leave', 0, PARAM_BOOL));
        redirect(
            $activity->url('session', ['session' => $sid]),
            activity::text('state_' . $outcome),
            null,
            \core\output\notification::NOTIFY_SUCCESS,
        );
    } catch (moodle_exception $error) {
        if ($error->module !== activity::C) {
            throw $error;
        }
        \core\notification::error($error->getMessage());
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(activity::text('checkin'));
echo $OUTPUT->render_from_template('mod_skillupsattendance/checkin', [
    'url' => $url->out(false), 'name' => $record->name, 'sesskey' => sesskey(), 'code' => $record->method === 'code',
    'qr' => $record->method === 'qr', 'token' => $token, 'valid' => $record->method !== 'qr' || $token,
    'back' => $activity->url('session', ['session' => $sid])->out(false),
]);
echo $OUTPUT->footer();
