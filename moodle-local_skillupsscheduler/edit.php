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
 * Choose the institutional scope and save a Moodle scheduling form.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsscheduler\scope;
use local_skillupsscheduler\schedule;
use local_skillupsscheduler\store;
use local_skillupsscheduler\form\session;

require_login();
$id = optional_param('id', 0, PARAM_INT);
$slot = $id ? store::get(store::SLOT, $id) : null;
$siteid = $slot ? (int)$slot->siteid : optional_param('siteid', 0, PARAM_INT);
$courseid = $slot ? (int)$slot->courseid : optional_param('courseid', 0, PARAM_INT);
$day = optional_param('day', '', PARAM_TEXT);
$title = scope::text($slot ? 'editsession' : 'newsession');
scope::setup_page('edit', $title, array_filter(['id' => $id, 'siteid' => $siteid, 'courseid' => $courseid, 'day' => $day]));
if (!$siteid || !$courseid) {
    $options = [];
    foreach ($DB->get_records(store::SITE, [], 'name ASC') as $site) {
        if (scope::staff($site, 'plan')) {
            $options[] = [
                'label' => format_string($site->name),
                'url' => scope::url('edit', ['siteid' => $site->id, 'day' => $day])->out(false),
            ];
        }
    }
    $courses = [];
    if ($siteid) {
        $site = store::get(store::SITE, $siteid);
        scope::require_staff($site, 'plan');
        $search = optional_param('search', '', PARAM_TEXT);
        $category = core_course_category::get($site->categoryid, MUST_EXIST, true);
        $records = $category->get_courses(['recursive' => true, 'sort' => ['fullname' => SORT_ASC]]);
        foreach ($records as $course) {
            $name = core_text::strtolower($course->fullname);
            if ($search !== '' && core_text::strpos($name, core_text::strtolower($search)) === false) {
                continue;
            }
            $courses[] = [
                'label' => format_string($course->fullname),
                'url' => scope::url('edit', ['siteid' => $siteid, 'courseid' => $course->id, 'day' => $day])->out(false),
            ];
        }
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    echo $OUTPUT->render_from_template(scope::C . '/choose', [
        'sites' => $options, 'showcourses' => (bool)$siteid, 'courses' => $courses,
        'url' => scope::url('edit')->out(false), 'siteid' => $siteid,
        'day' => $day, 'search' => $search ?? '', 'empty' => !$courses,
    ]);
    echo $OUTPUT->footer();
    exit;
}
$site = store::get(store::SITE, $siteid);
scope::require_staff($site, 'plan');
if ($slot && $slot->status === 'published') {
    scope::require_staff($site, 'publish');
}
$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course || !scope::course_in_site($site, $course) || ($slot && $slot->status === 'cancelled')) {
    scope::fail();
}
$cancel = $slot ? scope::url('session', ['id' => $slot->id]) : scope::url();
$formurl = scope::url('edit', array_filter(['id' => $id, 'siteid' => $siteid, 'courseid' => $courseid]));
$form = new session($formurl, [$site, $course, $slot, $day]);
if ($slot) {
    $zone = new DateTimeZone($site->timezone);
    $start = (new DateTimeImmutable('@' . $slot->starts))->setTimezone($zone);
    $end = (new DateTimeImmutable('@' . $slot->ends))->setTimezone($zone);
    $form->set_data((array)$slot + [
        'day' => $start->format('Y-m-d'), 'begin' => $start->format('H:i'), 'finish' => $end->format('H:i'),
    ]);
}
if ($form->is_cancelled()) {
    redirect($cancel);
}
$error = '';
if ($data = $form->get_data()) {
    require_sesskey();
    if ((int)$data->id !== $id || (int)$data->courseid !== $courseid || (int)$data->siteid !== $siteid) {
        scope::fail();
    }
    try {
        $saved = schedule::save($siteid, $data);
        redirect(scope::url('session', ['id' => reset($saved)]), scope::text('saved'));
    } catch (moodle_exception $failure) {
        if ($failure->module !== scope::C) {
            throw $failure;
        }
        $error = $failure->getMessage();
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::div(s(format_string($course->fullname) . ' · ' . format_string($site->name) . ' · ' . $site->timezone), 'mb-3');
if ($error) {
    echo $OUTPUT->notification(s($error), 'error');
}
$form->display();
echo $OUTPUT->footer();
