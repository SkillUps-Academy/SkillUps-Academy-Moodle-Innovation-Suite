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
 * Executive dashboard and historical snapshots.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsinsight\metrics;
use report_skillupsinsight\report;
use report_skillupsinsight\scope;
use report_skillupsinsight\ui;
use report_skillupsinsight\util;
use report_skillupsinsight\warehouse;

require_login();
$presetid = optional_param('preset', 0, PARAM_INT);
$config = ui::filters();
if ($presetid) {
    $preset = report::preset($presetid, $USER->id);
    $config = report::config(json_decode($preset->config, true, 8, JSON_THROW_ON_ERROR));
}
$context = scope::context($config['category'], $config['course']);
if ($config['course']) {
    require_login(get_course($config['course']));
}
$PAGE->set_context($context);
$view = report::build($config);
$config['period'] = (int)($view['current']->id ?? 0);
$url = util::url('index', ui::params($config));
$heading = $preset->name ?? util::str('pluginname');
$page = max(0, min(optional_param('page', 0, PARAM_INT), max(0, (int)ceil(count($view['rows']) / 25) - 1)));
util::audit($context, 'dashboard', $config['period']);
ui::header($heading, $context, $url);

$contextparams = ['category' => $config['category'], 'course' => $config['course']];
$actionlinks = [];
if (has_capability(util::CAP . ':manage', \context_system::instance())) {
    $actionlinks[] = ui::link('periods', [], util::str('periodadmin'));
}
if (has_capability(util::CAP . ':personalise', $context)) {
    $actionlinks[] = ui::link('presets', ui::params($config), util::str('constructor'));
}
if ($actionlinks) {
    echo html_writer::div(implode(' ', $actionlinks), 'mb-3');
}
ui::filter($config, $view);
if (!$view['run']) {
    echo $OUTPUT->notification(util::str($view['current'] ? 'awaiting' : 'noperiods'), 'info');
}
$cards = [];
foreach ($config['metrics'] as $metric) {
    $value = $view['summary'][$metric];
    $prior = $view['baseline'][$metric];
    $cards[] = [
        'label' => util::str('metric_' . $metric), 'value' => report::display($value, $metric),
        'hascomparison' => $view['comparison'], 'prior' => report::display($prior, $metric),
        'delta' => report::display($value !== null && $prior !== null ? $value - $prior : null, $metric, true),
        'comparisontext' => util::str('tablecompare', (object)[
            'prior' => report::display($prior, $metric),
            'delta' => report::display($value !== null && $prior !== null ? $value - $prior : null, $metric, true),
        ]),
    ];
}
echo $OUTPUT->render_from_template(util::C . '/dashboard', [
    'cards' => $cards,
    'scope' => util::str('coveragedetail', (object)['current' => $view['coverage'], 'total' => $view['scope']]),
    'hasprior' => $view['comparison'],
    'priorscope' => util::str('priorcoverage', (object)['current' => $view['priorcoverage'], 'total' => $view['scope']]),
    'capture' => $view['run'] ? util::str('captured', userdate($view['run']->completed)) : '',
]);
if ($view['run']) {
    if (has_capability(util::CAP . ':export', $context)) {
        $export = $config;
        $export['run'] = (int)$view['run']->id;
        $export['comparerun'] = (int)($view['older']->id ?? 0);
        ui::form('export', ['config' => util::json($export)]);
        ui::select('format', ['csv' => 'CSV', 'excel' => 'XLSX', 'pdf' => 'PDF'], 'csv');
        ui::button('download');
        echo html_writer::end_tag('form');
    }
    echo $OUTPUT->heading(util::str('history'), 3);
    $records = $DB->get_records_select(
        warehouse::RUN,
        'periodid = :period AND completed > 0',
        ['period' => $config['period']],
        'completed DESC, id DESC',
        'id,completed',
        0,
        30
    );
    $history = [];
    foreach ($records as $generation) {
        $query = ui::params($config);
        $query['run'] = $generation->id;
        $history[] = ui::link('index', $query, userdate($generation->completed));
    }
    echo html_writer::alist($history, ['class' => 'list-inline']);
}
ui::table($view['rows'], $config['metrics'], $view['comparison'], $page, $url);
echo html_writer::tag('p', util::str('metriclimits'));
echo $OUTPUT->footer();
