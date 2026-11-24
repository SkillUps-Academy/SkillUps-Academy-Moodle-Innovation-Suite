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
 * Show an authorized institutional or category accessibility comparison.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsaccessibility\report;
use report_skillupsaccessibility\scope;
use report_skillupsaccessibility\task\bulk;

require_login();
$category = optional_param('category', 0, PARAM_INT);
$context = $category ? context_coursecat::instance($category) : context_system::instance();
require_capability(scope::CAP . 'bulk', $context);
$base = ['category' => $category];
scope::setup('index', $context, $base);
if (data_submitted()) {
    require_sesskey();
    bulk::enqueue($category, (int)$USER->id);
    redirect(scope::url('index', $base), scope::text('queuednotice'));
}
$inputs = [];
foreach (['from', 'to', 'comparefrom', 'compareto'] as $key) {
    $inputs[$key] = optional_param($key, '', PARAM_TEXT);
}
$start = report::date($inputs['from']);
$finish = report::date($inputs['to'], true);
$previousstart = report::date($inputs['comparefrom']);
$previousfinish = report::date($inputs['compareto'], true);
$compare = $inputs['comparefrom'] !== '' || $inputs['compareto'] !== '';
if ($start >= $finish || ($compare && $previousstart >= $previousfinish)) {
    scope::fail('badperiod');
}
$page = max(0, optional_param('page', 0, PARAM_INT));
$number = 0;
$rows = [];
$wide = ['inventoried' => 0, 'checked' => 0, 'clear' => 0, 'confirmed' => 0, 'review' => 0];
$earlier = ['checked' => 0, 'clear' => 0];
$frequency = [];
$exporting = (bool)optional_param('download', 0, PARAM_BOOL);
$format = core_plugin_manager::instance()->get_plugin_info('dataformat_csv');
$canexport = $format && $format->is_enabled();
if ($exporting && !$canexport) {
    scope::fail();
}
foreach (scope::courses($category, $exporting ? 'export' : 'view') as $course) {
    $job = report::latest((int)$course->id, $start, $finish);
    $stats = report::metrics($job ? report::rows($course, $job) : []);
    $past = $compare ? report::latest((int)$course->id, $previousstart, $previousfinish) : null;
    $old = report::metrics($past ? report::rows($course, $past) : []);
    foreach ($wide as $metric => $value) {
        $wide[$metric] += $stats[$metric];
    }
    $earlier['checked'] += $old['checked'];
    $earlier['clear'] += $old['clear'];
    foreach ($stats['frequency'] as $rule => $amount) {
        $frequency[$rule] = ($frequency[$rule] ?? 0) + $amount;
    }
    if ($exporting || ($number >= 40 * $page && $number < 40 * ($page + 1))) {
        $rows[] = [
            'name' => format_string($course->fullname),
            'link' => scope::url('course', ['id' => $course->id] + ($job ? ['jobid' => $job->id] : []))->out(false),
            'checked' => $stats['checked'], 'coverage' => $stats['inventoried'],
            'confirmed' => $stats['confirmed'], 'review' => $stats['review'],
            'index' => $stats['index'] === null ? scope::text('nodata') : format_float($stats['index'], 1) . '%',
            'previous' => $old['index'] === null ? scope::text('nodata') : format_float($old['index'], 1) . '%',
            'date' => $job ? userdate($job->finished) : scope::text('nodata'),
            'partial' => $job && $job->truncated,
        ];
    }
    $number++;
}
if ($exporting) {
    $columns = ['course', 'checked', 'inventoried', 'confirmed', 'review', 'index', 'previous', 'date'];
    $columns = array_map(static fn($key) => scope::text($key), $columns);
    core\dataformat::download_data('skillups-accessibility-overview', 'csv', $columns, $rows, static fn($row) => [
        report::safe_cell($row['name']), $row['checked'], $row['coverage'], $row['confirmed'], $row['review'],
        $row['index'], $row['previous'], $row['date'],
    ]);
    exit;
}
$history = [];
arsort($frequency);
foreach (array_slice($frequency, 0, 10, true) as $rule => $count) {
    if ($count > 1) {
        $history[] = ['name' => scope::text('rule_' . $rule), 'count' => $count];
    }
}
$stats = [];
foreach (['checked', 'inventoried', 'confirmed', 'review'] as $key) {
    $stats[] = ['name' => scope::text($key), 'value' => $wide[$key]];
}
$stats[] = ['name' => scope::text('index'), 'value' => $wide['checked'] ?
    format_float(100 * $wide['clear'] / $wide['checked'], 1) . '%' : scope::text('nodata')];
if ($compare) {
    $stats[] = ['name' => scope::text('previous'), 'value' => $earlier['checked'] ?
        format_float(100 * $earlier['clear'] / $earlier['checked'], 1) . '%' : scope::text('nodata')];
}
$dates = [];
foreach ($inputs as $name => $value) {
    $dates[] = ['name' => $name, 'label' => scope::text($name), 'value' => $value];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(scope::C . '/overview', [
    'title' => $category ? $context->get_context_name() : scope::text('institution'),
    'form' => scope::url('index')->out(false), 'category' => $category, 'sesskey' => sesskey(),
    'rows' => $rows, 'stats' => $stats, 'compare' => $compare, 'periods' => $dates, 'recurring' => $history,
    'download' => $canexport ? scope::url('index', ['download' => 1] + $base + $inputs)->out(false) : '',
]);
echo $OUTPUT->paging_bar($number, $page, 40, scope::url('index', $base + $inputs));
echo $OUTPUT->footer();
