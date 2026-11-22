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
 * Independent outcome assessment.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupssuccess\scope;
use local_skillupssuccess\store;
use local_skillupssuccess\ui;
use local_skillupssuccess\util;

$courseid = required_param('course', PARAM_INT);
require_login($courseid);
$course = scope::course($courseid, 'assess');
$context = context_course::instance($courseid);
$url = util::url('assess', ['course' => $courseid]);
$policy = store::policy($courseid);
if (optional_param('action', '', PARAM_ALPHA) === 'label') {
    ui::require_post();
    store::assess($course, required_param('sample', PARAM_INT), required_param('outcome', PARAM_INT));
    redirect($url, util::str('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$choices = [2 => util::str('outcomeunknown'), 0 => util::str('outcomeno'), 1 => util::str('outcomeyes')];
$sample = optional_param('sample', 0, PARAM_INT);
$current = null;
if ($sample && $policy->study) {
    $current = $DB->get_record_select(
        store::SAMPLE,
        'id = :id AND courseid = :course AND due <= :now AND outcome = -1',
        ['id' => $sample, 'course' => $courseid, 'now' => time()],
        'id,userid,captured,due',
        MUST_EXIST
    );
    if (!scope::learner($course, $current->userid)) {
        util::fail('outofscope');
    }
}
util::audit($context, 'evaluation', $sample);
ui::header($context, $url, util::str('assess'));
if (has_capability(util::CAP . ':view', $context)) {
    echo html_writer::tag('p', ui::link('index', ['course' => $courseid], util::str('back')));
}
echo $OUTPUT->notification(util::str('assessmenthelp'), 'info');
if (!$policy->study) {
    echo $OUTPUT->notification(util::str('studydisabled'), 'info');
} else if ($current) {
    $student = core_user::get_user($current->userid, '*', MUST_EXIST);
    echo $OUTPUT->heading(s(fullname($student)), 3);
    echo html_writer::tag('p', s(util::str('assessmentwindow', (object)[
        'start' => userdate($current->captured), 'end' => userdate($current->due),
    ])));
    ui::form('assess', ['course' => $courseid, 'sample' => $current->id]);
    ui::select('outcome', $choices, 2);
    ui::submit('label');
    echo html_writer::end_tag('form');
} else {
    [$population, $params] = scope::population($course);
    $samples = store::SAMPLE;
    $where = "FROM {{$samples}} s JOIN ($population) enrolled ON enrolled.id = s.userid
        WHERE s.courseid = :course AND s.due <= :now AND s.outcome = -1";
    $params += ['course' => $courseid, 'now' => time()];
    $total = $DB->count_records_sql("SELECT COUNT(1) $where", $params);
    $page = max(0, min(optional_param('page', 0, PARAM_INT), max(0, (int)ceil($total / 25) - 1)));
    $sql = "SELECT s.id, s.userid, s.captured, s.due $where ORDER BY s.due, s.id";
    $records = $DB->get_records_sql($sql, $params, $page * 25, 25);
    $rows = [];
    foreach ($records as $record) {
        $student = core_user::get_user($record->userid, '*', MUST_EXIST);
        $rows[] = [s(fullname($student)), s(userdate($record->captured)), s(userdate($record->due)),
            ui::link('assess', ['course' => $courseid, 'sample' => $record->id], util::str('assessone'))];
    }
    if ($rows) {
        echo ui::table('assessmentqueue', ['learner', 'observed', 'due', 'action'], $rows);
        echo $OUTPUT->paging_bar($total, $page, 25, $url);
    } else {
        echo $OUTPUT->notification(util::str('noassessments'), 'info');
    }
}
echo $OUTPUT->footer();
