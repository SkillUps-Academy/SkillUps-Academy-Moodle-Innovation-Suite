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
 * Select a native Moodle course section for an authorized resource copy.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsrepository\access;
use local_skillupsrepository\form\fields;
use local_skillupsrepository\reuse;
use local_skillupsrepository\store;
use local_skillupsrepository\ui;

require_login();
$id = required_param('id', PARAM_INT);
$version = required_param('version', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$asset = store::get(store::ASSET, $id);
$revision = store::get(store::REVISION, $version);
if (!access::reuse($asset, $revision)) {
    access::fail();
}
$back = ui::url('view', compact('id', 'version'));
$mod = $revision->kind === 'link' ? 'url' : 'resource';
ui::page('insert', compact('id', 'version', 'courseid'));
if (!$courseid) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(ui::t('choosecourse'));
    echo html_writer::link($back, ui::t('back'));
    $courses = [];
    foreach (get_courses('all') as $course) {
        if (reuse::destination($course, $mod)) {
            $name = format_string($course->fullname);
            $courses[] = html_writer::tag('li', html_writer::link(ui::url('insert', compact('id', 'version') + [
                'courseid' => $course->id,
            ]), $name));
        }
    }
    echo $courses ? html_writer::tag('ul', implode('', $courses)) : html_writer::tag('p', ui::t('empty'));
    echo $OUTPUT->footer();
    exit;
}
$course = get_course($courseid);
if (!reuse::destination($course, $mod)) {
    access::fail();
}
$sections = [];
foreach (get_fast_modinfo($course)->get_section_info_all() as $section) {
    if ($section->visible || has_capability('moodle/course:viewhiddensections', context_course::instance($courseid))) {
        $sections[$section->id] = get_section_name($course, $section);
    }
}
if (!$sections) {
    access::fail();
}
$form = new fields(ui::url('insert', compact('id', 'version', 'courseid')), ['fields' => [
    'sectionid' => ['select', $sections, false], 'requestkey' => ['hidden', PARAM_ALPHANUM, false],
    'confirmcopy' => ['advcheckbox', null, false],
]]);
$form->set_data(['requestkey' => bin2hex(random_bytes(16))]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    if (empty($data->confirmcopy) || !isset($sections[$data->sectionid])) {
        access::fail('confirmrequired');
    }
    $cmid = reuse::insert($id, $version, $courseid, (int)$data->sectionid, $data->requestkey);
    $destination = new moodle_url('/course/view.php', ['id' => $courseid], 'module-' . $cmid);
    redirect($destination, ui::t('copysuccess'));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::t('insert'));
echo html_writer::tag('p', ui::t('copyhelp'));
$form->display();
echo $OUTPUT->footer();
