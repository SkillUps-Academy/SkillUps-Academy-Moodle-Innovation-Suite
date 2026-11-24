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
 * Institutional landing page and authorized courses and programs.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscompetency\access;
use local_skillupscompetency\store;

require_login();
access::setup('index', context_system::instance());
$categories = core_course_category::make_categories_list(access::CAP . 'manage');
$courses = [];
$caps = [access::CAP . 'view', access::CAP . 'assess', access::CAP . 'review', access::CAP . 'reports'];
foreach (enrol_get_my_courses() as $course) {
    $context = context_course::instance($course->id);
    if (
        access::course_visible($course) &&
        has_any_capability($caps, $context)
    ) {
        $courses[] = [
            'label' => format_string($course->fullname), 'url' => access::url('course', ['id' => $course->id])->out(false),
        ];
    }
}
$programs = [];
foreach ($DB->get_records(store::PROG, [], 'name') as $record) {
    $context = context_coursecat::instance($record->categoryid, IGNORE_MISSING);
    if ($context && has_any_capability([access::CAP . 'manage', access::CAP . 'reports'], $context)) {
        $programs[] = [
            'title' => $record->name, 'code' => $record->code, 'description' => $record->summary,
            'link' => access::url('matrix', ['programid' => $record->id])->out(false),
        ];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/home', [
    'catalog' => access::url('catalog')->out(false),
    'newprogram' => $categories ? access::url('matrix', ['op' => 'program'])->out(false) : '',
    'courses' => $courses, 'programs' => $programs,
]);
echo $OUTPUT->footer();
