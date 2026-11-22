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
 * Assignment-level review dashboard.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The adapter validates login for the assignment and course.
require_once(__DIR__ . '/../../config.php');

use local_skillupsaigrading\access;
use local_skillupsaigrading\activity\assign;
use local_skillupsaigrading\licensing\entitlements;
use local_skillupsaigrading\ui;

$id = required_param('id', PARAM_INT);
$activity = new assign($id, 'view', true);
$url = ui::url('assignment', ['id' => $id]);
$participants = $activity->participants();
[$stats, $recent] = ui::statistics($activity, $participants);

ui::header($url, $activity->context, $activity->course, ui::str('assignmentreviews'));
echo html_writer::tag('p', ui::link('index', ['courseid' => $activity->course->id], ui::str('pluginname')));
echo $OUTPUT->notification(ui::str('humanonly'), 'info');
groups_print_activity_menu($activity->cm, $url);
try {
    $activity->rubric();
} catch (moodle_exception $e) {
    echo $OUTPUT->notification(s($e->getMessage()), 'warning');
}
echo $OUTPUT->heading(ui::str('statistics'), 3);
echo $OUTPUT->render_from_template(access::C . '/statistics', $stats);
if (
    has_capability(access::CAP . ':review', $activity->context) &&
        in_array('aggregate_export', entitlements::status()['features'], true)
) {
    ui::form('report', ['id' => $id]);
    ui::button('exportsummary');
    echo html_writer::end_tag('form');
}
echo $OUTPUT->heading(ui::str('participants'), 3);
$links = [];
foreach ($participants as $participant) {
    $links[] = ui::link('review', ['id' => $id, 'student' => $participant->id], fullname($participant));
}
echo $links ? html_writer::alist($links) : html_writer::tag('p', ui::str('noparticipants'));
echo $OUTPUT->heading(ui::str('history'), 3);
$links = [];
foreach ($recent as $row) {
    $subject = core_user::get_user($row->studentid);
    $label = fullname($subject) . ' · ' . ui::str($row->state) . ' · ' . ui::str($row->reviewstate);
    $links[] = ui::link('job', ['id' => $id, 'job' => $row->id], $label);
}
echo $links ? html_writer::alist($links) : html_writer::tag('p', ui::str('nohistory'));
echo $OUTPUT->footer();
