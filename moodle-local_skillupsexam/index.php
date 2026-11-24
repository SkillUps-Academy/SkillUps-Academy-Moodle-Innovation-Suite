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
 * Institutional exam calendar and distribution report.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');

use local_skillupsexam\report;
use local_skillupsexam\scope;
use local_skillupsexam\store;
use local_skillupsexam\ui;

require_login();
$tz = \core_date::get_user_timezone_object();
$today = new DateTimeImmutable('today', $tz);
$month = optional_param('month', $today->format('Y-m'), PARAM_TEXT);
if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $month)) {
    scope::fail('invalidtime');
}
$start = new DateTimeImmutable($month . '-01', $tz);
$end = $start->modify('+1 month');
$courseid = optional_param('courseid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$periodid = optional_param('periodid', 0, PARAM_INT);
$campus = optional_param('campus', '', PARAM_TEXT);
$status = optional_param('status', '', PARAM_ALPHA);
$export = optional_param('download', 0, PARAM_BOOL);
$courses = report::courses();
$categories = [0 => scope::text('all')];
foreach ($courses as $course) {
    $cat = \core_course_category::get($course->category, IGNORE_MISSING, true);
    if ($cat) {
        $catcontext = context_coursecat::instance($cat->id);
        $categories[$cat->id] = format_string($cat->name, true, ['escape' => false, 'context' => $catcontext]);
    }
}
$catalogs = \core_course_category::make_categories_list(scope::CAP . 'catalog');
if (!$courses && !$catalogs) {
    scope::fail();
}
if ($courseid && !isset($courses[$courseid]) || $categoryid && !isset($categories[$categoryid])) {
    scope::fail();
}
scope::page('index', $courseid ? context_course::instance($courseid) : context_system::instance());
$courseoptions = [0 => scope::text('all')];
foreach ($courses as $course) {
    $coursecontext = context_course::instance($course->id);
    $courseoptions[$course->id] = format_string($course->fullname, true, ['escape' => false, 'context' => $coursecontext]);
}
$selected = array_filter($courses, static fn($course) =>
    (!$courseid || $course->id == $courseid) && (!$categoryid || $course->category == $categoryid));
$states = ['' => scope::text('all')];
foreach (['draft', 'scheduled', 'running', 'finished', 'cancelled'] as $key) {
    $states[$key] = scope::text($key);
}
$periods = [0 => scope::text('all')];
$campuses = ['' => scope::text('all')];
$rows = [];
$distribution = [];
$counts = [];
$days = [];
$csv = [];
$rooms = [];
$canexport = false;
if ($export) {
    require_sesskey();
    if (!array_filter($selected, static fn($course) => scope::course($course, 'export'))) {
        scope::fail();
    }
}
foreach (report::exams($selected, $start->getTimestamp(), $end->getTimestamp(), (bool)$export) as $exam) {
    $period = $DB->get_record(store::PERIOD, ['id' => $exam->periodid]);
    if ($exam->roomid && !array_key_exists($exam->roomid, $rooms)) {
        $rooms[$exam->roomid] = $DB->get_record(store::ROOM, ['id' => $exam->roomid]);
    }
    $room = $rooms[$exam->roomid] ?? null;
    $pname = $period && scope::inside($courses[$exam->courseid], (int)$period->categoryid) ? $period->name : '';
    $site = $room && scope::inside($courses[$exam->courseid], (int)$room->categoryid) ? $room->campus : scope::text('nosite');
    if ($pname !== '') {
        $periods[$period->id] = $pname;
    }
    $campuses[$site] = $site;
    $state = ui::state($exam);
    if (($periodid && $exam->periodid != $periodid) || ($campus !== '' && $campus !== $site) || ($status && $state !== $status)) {
        continue;
    }
    $data = [$exam->name, $courseoptions[$exam->courseid], userdate($exam->starts), userdate($exam->ends),
        $pname, $site, scope::text($exam->mode), $states[$state]];
    if ($export) {
        report::audit((int)$exam->id, 'scheduleexport');
        $csv[] = array_map([ui::class, 'cell'], $data);
        continue;
    }
    $counts[$state] = ($counts[$state] ?? 0) + 1;
    $canexport = $canexport || scope::exam($exam, $courses[$exam->courseid], 'export');
    $row = array_map('s', $data);
    $link = ui::link(scope::url('exam', ['id' => $exam->id]), $exam->name);
    $row[0] = $link;
    $rows[] = $row;
    $visible = max($start->getTimestamp(), (int)$exam->starts);
    $date = (new DateTimeImmutable('@' . $visible))->setTimezone($tz)->setTime(0, 0);
    while ($date < $end && $date->getTimestamp() < $exam->ends) {
        $days[$date->format('Y-m-d')][] = $link;
        $date = $date->modify('+1 day');
    }
    $distribution[$pname][$site] = ($distribution[$pname][$site] ?? 0) + 1;
}
$labels = array_map([scope::class, 'text'], ['name', 'courseid', 'starts', 'ends', 'periodid', 'campus', 'mode', 'status']);
if ($export) {
    \core\dataformat::download_data('exam-schedule-' . $month, 'csv', $labels, $csv);
    exit;
}
$menu = '';
if ($catalogs) {
    $menu .= ui::link(scope::url('catalog'), scope::text('catalog'), 'btn btn-outline-secondary');
}
if (report::courses('plan')) {
    $menu .= ui::link(scope::url('edit', ['courseid' => $courseid]), scope::text('newexam'), 'btn btn-primary');
}
$params = compact('courseid', 'categoryid', 'periodid', 'campus', 'status');
echo $OUTPUT->header();
echo html_writer::start_div('skillups-exam');
echo $OUTPUT->heading(scope::text('pluginname'));
echo html_writer::div($menu, 'exam-actions');
echo ui::filters(scope::url(), [
    'month' => [$month, null], 'courseid' => [$courseid, $courseoptions], 'categoryid' => [$categoryid, $categories],
    'periodid' => [$periodid, $periods], 'campus' => [$campus, $campuses], 'status' => [$status, $states],
]);
$cards = [];
foreach (['draft', 'scheduled', 'running', 'finished', 'cancelled'] as $key) {
    $cards[] = ['count' => $counts[$key] ?? 0, 'label' => $states[$key]];
}
echo $OUTPUT->render_from_template('local_skillupsexam/summary', ['states' => $cards]);
echo $OUTPUT->heading(scope::text('calendar'), 3);
echo html_writer::div(
    ui::link(scope::url('index', $params + ['month' => $start->modify('-1 month')->format('Y-m')]), scope::text('previous')) .
    html_writer::tag('strong', s(userdate($start->getTimestamp(), '%B %Y'))) .
    ui::link(scope::url('index', $params + ['month' => $end->format('Y-m')]), scope::text('next')),
    'exam-actions',
);
echo html_writer::start_tag('ol', ['class' => 'exam-calendar']);
$offset = ((int)$start->format('w') - (int)calendar_get_starting_weekday() + 7) % 7;
for ($i = 0; $i < $offset; $i++) {
    echo html_writer::tag('li', '', ['class' => 'exam-calendar-offset', 'aria-hidden' => 'true']);
}
for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
    $key = $day->format('Y-m-d');
    $heading = html_writer::tag('time', s(userdate($day->getTimestamp(), '%a %d')), ['datetime' => $key]);
    echo html_writer::tag('li', $heading . html_writer::div(implode('<br>', $days[$key] ?? [])));
}
echo html_writer::end_tag('ol');
echo $OUTPUT->heading(scope::text('scheduledreport'), 3);
if ($canexport) {
    echo ui::link(
        scope::url('index', $params + ['month' => $month, 'download' => 1, 'sesskey' => sesskey()]),
        scope::text('download'),
        'btn btn-outline-secondary'
    );
}
echo ui::table($labels, $rows);
echo $OUTPUT->heading(scope::text('distribution'), 3);
$grouped = [];
foreach ($distribution as $period => $sites) {
    foreach ($sites as $place => $count) {
        $grouped[] = [s($period), s($place), $count];
    }
}
echo ui::table([scope::text('periodid'), scope::text('campus'), scope::text('count')], $grouped);
echo html_writer::end_div();
echo $OUTPUT->footer();
