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
 * List the attendance activities visible in this course.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;

$course = get_course(required_param('id', PARAM_INT));
require_login($course);
$PAGE->set_url('/mod/skillupsattendance/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(activity::text('modulenameplural'));
echo $OUTPUT->header();
echo $OUTPUT->heading(activity::text('modulenameplural'));
$links = [];
foreach (get_all_instances_in_course('skillupsattendance', $course) as $instance) {
    $module = activity::by_instance($instance->id);
    if ($module->can('view')) {
        $links[] = html_writer::link($module->url(), format_string($instance->name, true, ['context' => $module->context]));
    }
}
echo $links ? html_writer::alist($links) : $OUTPUT->notification(activity::text('empty'), 'info');
echo $OUTPUT->footer();
