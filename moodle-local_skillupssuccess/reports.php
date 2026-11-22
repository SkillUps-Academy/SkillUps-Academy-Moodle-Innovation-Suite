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
 * Desensitized course and programme reporting.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupssuccess\aggregates;
use local_skillupssuccess\licensing\entitlements;
use local_skillupssuccess\scope;
use local_skillupssuccess\store;
use local_skillupssuccess\ui;
use local_skillupssuccess\util;

$courseid = optional_param('course', 0, PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);
require_login($courseid ?: null);
if ($courseid) {
    if ($category) {
        util::fail('invalidrequest');
    }
    $course = scope::course($courseid, 'aggregate');
    $context = context_course::instance($courseid);
    $courses = [$courseid => $course];
} else {
    $courses = scope::reports($category);
    $context = $category ? context_coursecat::instance($category) : context_system::instance();
}
$nowcohort = intdiv(time(), store::HORIZON);
$default = $nowcohort - 2;
$cohort = optional_param('cohort', $default, PARAM_INT);
if ($cohort > $nowcohort - 1 || $cohort < $nowcohort - 53) {
    util::fail('invalidrequest');
}
$mode = optional_param('mode', 'rules', PARAM_ALPHA);
$group = optional_param('grouping', 'courses', PARAM_ALPHA);
if (!in_array($mode, ['rules', 'model'], true) || !in_array($group, ['courses', 'programs'], true)) {
    util::fail('invalidrequest');
}
$base = ['course' => $courseid, 'category' => $category];
$args = $base + ['cohort' => $cohort, 'mode' => $mode, 'grouping' => $group];
[$cases, $quality] = aggregates::build($courses, $group === 'programs', $cohort, $mode === 'model');
$columnnames = ['group', 'version', 'total', 'pending', 'unknown', 'abstained', 'tp', 'fp', 'tn', 'fn',
    'precision', 'recall', 'fpr', 'fnr', 'accuracy', 'brier'];
$licensed = in_array('aggregate_export', entitlements::status()['features'], true);
if (optional_param('action', '', PARAM_ALPHA) === 'export') {
    ui::require_post();
    if (!$licensed || !$quality) {
        util::fail('outofscope');
    }
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename('skillups-success-quality');
    $csv->add_data(array_map([util::class, 'str'], $columnnames));
    foreach ($quality as $entry) {
        $csv->add_data(array_map(static fn($value) => preg_match('/^[\s\p{Z}\p{Cf}\p{Cc}]*[=+\-@]/u', $value) ?
            "'" . $value : $value, $entry));
    }
    util::audit($context, 'export', $cohort);
    $csv->download_file();
    exit;
}
util::audit($context, 'aggregate', $cohort);
ui::header($context, util::url('reports', $args), util::str('reports'));
if ($courseid && has_capability(util::CAP . ':view', $context)) {
    echo html_writer::tag('p', ui::link('index', ['course' => $courseid], util::str('back')));
}
echo $OUTPUT->notification(util::str('aggregationhelp'), 'info');
$cohorts = [];
for ($cursor = $nowcohort - 1; $cursor >= $nowcohort - 53; $cursor--) {
    $cohorts[$cursor] = util::str('cohortrange', (object)[
        'begin' => gmdate('Y-m-d', $cursor * store::HORIZON),
        'end' => gmdate('Y-m-d', ($cursor + 1) * store::HORIZON - 1),
    ]);
}
ui::form('reports', $base, false);
ui::select('cohort', $cohorts, $cohort);
ui::select('mode', ['rules' => util::str('ruleclass'), 'model' => util::str('statistical')], $mode);
ui::select('grouping', ['courses' => util::str('courses'), 'programs' => util::str('programs')], $group);
ui::submit('filter');
echo html_writer::end_tag('form');
$escape = static fn($row) => array_values(array_map('s', $row));
if ($cases) {
    echo ui::table('caseoutcomes', ['group', 'cases', 'resolved', 'assigned', 'supported'], array_map($escape, $cases));
}
if ($quality) {
    $observedcolumns = ['group', 'version', 'total', 'pending', 'unknown', 'abstained', 'tp', 'fp', 'tn', 'fn'];
    $ratecolumns = ['group', 'version', 'precision', 'recall', 'fpr', 'fnr', 'accuracy', 'brier'];
    $observedrows = [];
    $raterows = [];
    foreach ($quality as $entry) {
        $cells = $escape($entry);
        $version = $entry['version'];
        $shortversion = substr($version, 0, -64) . substr($version, -64, 12);
        $cells[1] = html_writer::span(s($shortversion), 'text-nowrap', ['title' => $version]);
        $observedrows[] = array_slice($cells, 0, count($observedcolumns));
        $raterows[] = array_merge(array_slice($cells, 0, 2), array_slice($cells, count($observedcolumns)));
    }
    echo ui::table('observedcounts', $observedcolumns, $observedrows);
    echo ui::table('qualitymetrics', $ratecolumns, $raterows);
    if ($licensed) {
        ui::form('reports', $args);
        ui::submit('export');
        echo html_writer::end_tag('form');
    }
}
if (!$cases && !$quality) {
    echo $OUTPUT->notification(util::str('noaggregates'), 'info');
}
echo $OUTPUT->footer();
