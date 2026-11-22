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
 * Track corrective work and verification at course level.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsquality\review;
use report_skillupsquality\scope;
use report_skillupsquality\store;
use report_skillupsquality\ui;

require_login();
$course = scope::course(required_param('course', PARAM_INT));
require_login($course);
$url = ui::url('plans', ['course' => $course->id]);
ui::header($url, scope::text('plans'), $course);
echo html_writer::tag('p', s(scope::text('compliancehelp')));
$filter = ['courseid' => $course->id];
$total = $DB->count_records(store::PLAN, $filter);
$page = max(0, min(optional_param('page', 0, PARAM_INT), (int)ceil($total / 25) - 1));
$plans = $DB->get_records(store::PLAN, $filter, 'timemodified DESC, id DESC', '*', $page * 25, 25);
$latest = review::latest((int)$course->id);
$states = review::compliance($latest, $plans);
if ($latest) {
    echo html_writer::tag('p', html_writer::link(ui::url('audit', ['id' => $latest->id]), s(scope::text('latestaudit'))));
}
$rows = [];
foreach ($plans as $plan) {
    $name = s(ui::rule_name($plan->rulecode));
    if (scope::allowed($course, 'plan')) {
        $name = html_writer::link(ui::url('plan', ['id' => $plan->id]), $name);
    }
    $due = $plan->due ? s(userdate((int)$plan->due, get_string('strftimedate', 'langconfig'))) : '—';
    if ($plan->due && $plan->due < usergetmidnight(time()) && in_array($plan->state, ['planned', 'working'], true)) {
        $due .= ' ' . ui::state('overdue');
    }
    $rows[] = [$name, ui::target($course, $plan), ui::state($plan->state), $due, ui::state($states[$plan->id] ?? 'unknown')];
}
if ($rows) {
    ui::table('plans', ['rule', 'target', 'state', 'duedate', 'compliance'], $rows);
    echo $OUTPUT->paging_bar($total, $page, 25, $url);
} else {
    echo $OUTPUT->notification(scope::text('noplans'), 'info');
}
echo $OUTPUT->footer();
