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
 * Submit or review a protected competency assessment.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');

use local_skillupscompetency\access;
use local_skillupscompetency\assessment;
use local_skillupscompetency\catalog;
use local_skillupscompetency\form\editor;
use local_skillupscompetency\form\proposal;
use local_skillupscompetency\rubric;
use local_skillupscompetency\store;

$id = optional_param('id', 0, PARAM_INT);
$row = $id ? store::get(store::ASSESS, $id) : null;
$courseid = $row ? (int)$row->courseid : required_param('courseid', PARAM_INT);
$userid = $row ? (int)$row->userid : required_param('userid', PARAM_INT);
$cid = $row ? (int)$row->competencyid : required_param('competencyid', PARAM_INT);
$cmid = $row ? (int)$row->cmid : optional_param('cmid', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
if (!access::can_see_subject($course, $userid, $cmid) || ($row && !access::read_assessment($row))) {
    access::fail();
}
$item = access::linked($course, $cid);
$back = access::url('course', ['id' => $courseid, 'userid' => $userid]);
$levels = catalog::levels($item);
$subject = fullname(core_user::get_user($userid, '*', MUST_EXIST));
$error = '';
if ($row) {
    $url = access::url('evaluate', ['id' => $id]);
    access::setup('evaluate', $context, ['id' => $id]);
    $review = $row->status === 'pending' && has_capability(access::CAP . 'review', $context) &&
        $row->authorid != $USER->id && $row->userid != $USER->id;
    $form = null;
    if ($review) {
        $options = ['' => access::text('choose'), '0' => access::text('rejected')];
        if (has_capability('moodle/competency:competencygrade', $context)) {
            $options['1'] = access::text('approved');
        }
        $fields = ['decision' => ['select', $options], 'feedback' => ['textarea', []]];
        $form = new editor($url, ['review', ['id' => $id], $fields, 'recorddecision']);
        if ($form->is_cancelled()) {
            redirect($back);
        }
        if ($data = $form->get_data()) {
            require_sesskey();
            try {
                if (!in_array((string)$data->decision, ['0', '1'], true)) {
                    access::fail('invalidinput');
                }
                assessment::decide($id, (bool)$data->decision, $data->feedback);
                redirect($url, access::text('saved'));
            } catch (moodle_exception $failure) {
                if ($failure->module !== access::C) {
                    throw $failure;
                }
                $error = $failure->getMessage();
            }
        }
    }
    $files = [];
    foreach (get_file_storage()->get_area_files($context->id, access::C, assessment::AREA, $id, 'filename', false) as $file) {
        $files[] = [
            'name' => $file->get_filename(),
            'url' => moodle_url::make_pluginfile_url(
                $context->id,
                access::C,
                assessment::AREA,
                $id,
                '/',
                $file->get_filename(),
                true,
            )->out(false),
        ];
    }
    $author = $row->authorid ? core_user::get_user($row->authorid) : null;
    $reviewer = $row->reviewerid ? core_user::get_user($row->reviewerid) : null;
    $snapshot = $row->rubricdata ? json_decode($row->rubricdata, true) : [];
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template(access::C . '/evaluation', [
        'back' => $back->out(false), 'student' => $subject, 'title' => format_string($item->get('shortname')),
        'date' => userdate($row->created), 'status' => access::text($row->status), 'kind' => access::text($row->kind),
        'grade' => $row->scaleid == $item->get_scale()->id ? ($levels[$row->levelvalue] ?? '') : access::text('scalechanged'),
        'oldgrade' => $row->scaleid == $item->get_scale()->id ? ($levels[$row->basegrade] ?? access::text('unrated')) : '',
        'observations' => $row->observations, 'feedback' => $row->feedback, 'files' => $files,
        'reviewed' => $row->reviewed ? userdate($row->reviewed) : '',
        'author' => $author && !$author->deleted ? fullname($author) : '',
        'reviewer' => $reviewer && !$reviewer->deleted ? fullname($reviewer) : '', 'criteria' => $snapshot['criteria'] ?? [],
    ]);
    if ($error) {
        echo $OUTPUT->notification(s($error), 'error');
    }
    if ($form) {
        echo $OUTPUT->heading(access::text('reviewheading'), 2);
        $form->display();
    }
    echo $OUTPUT->footer();
    exit;
}
$self = $userid === (int)$USER->id;
require_capability(access::CAP . ($self ? 'self' : 'assess'), $context);
$use = !$self && (bool)optional_param('rubric', 0, PARAM_BOOL);
$definition = $use ? rubric::definition($course, $cmid) : [];
if ($use && !$definition) {
    access::fail('invalidrubric');
}
$parameters = ['courseid' => $courseid, 'userid' => $userid, 'competencyid' => $cid, 'cmid' => $cmid, 'rubric' => (int)$use];
access::setup('evaluate', $context, $parameters);
if ($cmid) {
    access::linked($course, $cid, $cmid);
}
$form = new proposal(access::url('evaluate', $parameters), [$parameters, $levels, $definition]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    require_sesskey();
    try {
        $data->rubric = $use;
        $saved = assessment::submit($course, $userid, $cid, $cmid, $data);
        redirect(access::url('evaluate', ['id' => $saved]), access::text('saved'));
    } catch (moodle_exception $failure) {
        if ($failure->module !== access::C) {
            throw $failure;
        }
        $error = $failure->getMessage();
    }
}
$links = [];
if (!$self && !$cmid) {
    foreach (get_fast_modinfo($course)->get_cms() as $cm) {
        if (
            $cm->modname !== 'assign' || !access::can_see_subject($course, $userid, (int)$cm->id) ||
            !rubric::definition($course, (int)$cm->id)
        ) {
            continue;
        }
        try {
            access::linked($course, $cid, (int)$cm->id);
        } catch (moodle_exception $e) {
            continue;
        }
        $links[] = ['name' => format_string($cm->name),
            'url' => access::url('evaluate', ['cmid' => $cm->id, 'rubric' => 1] + $parameters)->out(false)];
    }
}
echo $OUTPUT->header();
echo html_writer::link($back, access::text('back'));
echo $OUTPUT->heading(access::text($use ? 'rubric' : ($self ? 'self' : 'teacher')));
echo html_writer::tag('p', s($subject . ' · ' . $item->get('shortname')));
foreach ($links as $link) {
    echo html_writer::div(html_writer::link($link['url'], access::text('userubric', $link['name'])), 'mb-2');
}
if ($error) {
    echo $OUTPUT->notification(s($error), 'error');
}
$form->display();
echo $OUTPUT->footer();
