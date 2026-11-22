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
 * Present one course-scoped finding and its corrective plan link.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsquality\scope;
use report_skillupsquality\store;
use report_skillupsquality\ui;

require_login();
[$run, $item] = store::finding(required_param('id', PARAM_INT));
$course = scope::course((int)$run->courseid);
require_login($course);
ui::header(ui::url('finding', ['id' => $item->id]), ui::rule_name($item->rulecode), $course);
$rows = [
    [s(scope::text('ruleid')), s($item->rulecode)],
    [s(scope::text('kind')), s(scope::text('kind_' . $item->kind))],
    [s(scope::text('severity')), s(scope::text('severity_' . $item->severity))],
    [s(scope::text('target')), ui::target($course, $item)],
];
foreach (ui::evidence($item) as $label => $value) {
    $rows[] = [s($label), s($value)];
}
ui::table('evidence', ['field', 'value'], $rows);
$condition = 'condition_' . $item->rulecode;
$recommendation = 'recommend_' . $item->rulecode;
foreach (['condition' => $condition, 'recommendation' => $recommendation] as $heading => $key) {
    if (get_string_manager()->string_exists($key, scope::COMPONENT)) {
        echo $OUTPUT->heading(scope::text($heading), 2);
        echo html_writer::tag('p', s(scope::text($key)));
    }
}
if (scope::allowed($course, 'plan')) {
    $planurl = ui::url('plan', ['finding' => $item->id]);
    echo html_writer::link($planurl, s(scope::text('editplan')), ['class' => 'btn btn-primary me-3']);
}
echo html_writer::link(ui::url('audit', ['id' => $run->id]), s(scope::text('backaudit')));
echo $OUTPUT->footer();
