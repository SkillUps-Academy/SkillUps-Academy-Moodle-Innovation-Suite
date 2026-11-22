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
 * Course-level grading assistant navigation.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsaigrading\access;
use local_skillupsaigrading\activity\assign;
use local_skillupsaigrading\ui;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$links = [];
foreach (get_fast_modinfo($course)->get_instances_of('assign') as $cm) {
    try {
        $activity = new assign($cm->id);
        $links[] = ui::link('assignment', ['id' => $cm->id], $activity->cm->name);
    } catch (moodle_exception) {
        continue;
    }
}
if (!$links && !has_capability(access::CAP . ':view', $context)) {
    access::fail('accessdenied');
}
ui::header(ui::url('index', ['courseid' => $courseid]), $context, $course, ui::str('pluginname'));
echo html_writer::tag('p', ui::str('overview'));
echo $links ? html_writer::alist($links) : $OUTPUT->notification(ui::str('noassignments'), 'info');
echo $OUTPUT->footer();
