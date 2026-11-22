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
 * Private report builder and preset management.
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

require_login();
$config = ui::filters();
$requested = optional_param('preset', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$selected = $requested ? report::preset($requested, $USER->id) : null;
$context = scope::context($config['category'], $config['course'], 'personalise');
$url = util::url('presets', ui::params($config));

if ($action !== '') {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', util::C);
    }
    $lock = util::acquire('owner_' . $USER->id);
    try {
        if ($action === 'savepreset') {
            $name = trim(required_param('name', PARAM_TEXT));
            if ($name === '' || core_text::strlen($name) > 120 || !optional_param_array('metrics', [], PARAM_ALPHA)) {
                throw new moodle_exception('invalidrequest', util::C);
            }
            if (!report::build($config)['current']) {
                throw new moodle_exception('noperiods', util::C);
            }
            if ($selected) {
                $saved = report::config(json_decode($selected->config, true, 8, JSON_THROW_ON_ERROR));
                scope::context($saved['category'], $saved['course'], 'personalise');
                $DB->update_record(report::PRESET, ['id' => $selected->id, 'name' => $name, 'config' => util::json($config)]);
            } else {
                if ($DB->count_records(report::PRESET, ['userid' => $USER->id]) >= 50) {
                    throw new moodle_exception('limit', util::C);
                }
                $DB->insert_record(
                    report::PRESET,
                    ['userid' => $USER->id, 'name' => $name, 'config' => util::json($config), 'timecreated' => time()]
                );
            }
        } else if ($action === 'delete' && $selected) {
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records(report::WATCH, ['userid' => $USER->id, 'presetid' => $selected->id]);
            $DB->delete_records(report::PRESET, ['userid' => $USER->id, 'id' => $selected->id]);
            $transaction->allow_commit();
        } else {
            throw new moodle_exception('invalidrequest', util::C);
        }
    } finally {
        $lock->release();
    }
    util::audit($context, $action, $config['period']);
    redirect($url, util::str('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($selected) {
    $config = report::config(json_decode($selected->config, true, 8, JSON_THROW_ON_ERROR));
    $context = scope::context($config['category'], $config['course'], 'personalise');
}
$base = ['category' => $config['category'], 'course' => $config['course']];
ui::header(util::str('constructor'), $context, $url);
echo html_writer::tag('p', ui::link('index', ui::params($config), util::str('dashboard')));
echo html_writer::tag('p', util::str('builderhelp'));
$view = report::build($config);
if ($view['current']) {
    ui::form('presets', $base + ['preset' => $requested]);
    ui::input('name', 'text', ['maxlength' => 120, 'value' => $selected->name ?? '']);
    $periods = array_map(static fn($period) => $period->name, $view['periods']);
    ui::select('period', [0 => util::str('latest')] + $periods, $config['period']);
    ui::select('compare', [0 => util::str('nocmp')] + $periods, $config['compare']);
    ui::select('group', ['course' => util::str('course'), 'category' => util::str('category'),
        'program' => util::str('program')], $config['group']);
    ui::select('program', ['' => util::str('allprograms')] + $view['programs'], $config['program']);
    echo html_writer::start_tag('fieldset', ['class' => 'mb-3']);
    echo html_writer::tag('legend', util::str('indicators'));
    foreach (metrics::IDS as $metric) {
        echo html_writer::div(html_writer::checkbox(
            'metrics[]',
            $metric,
            in_array($metric, $config['metrics'], true),
            util::str('metric_' . $metric),
            ['id' => 'metric-' . $metric]
        ), 'form-check');
    }
    echo html_writer::end_tag('fieldset');
    ui::button('savepreset');
    echo html_writer::end_tag('form');
} else {
    echo $OUTPUT->notification(util::str('noperiods'), 'info');
}
echo $OUTPUT->heading(util::str('mypresets'), 3);
$records = $DB->get_records(report::PRESET, ['userid' => $USER->id], 'timecreated DESC', '*', 0, 50);
foreach ($records as $preset) {
    $saved = report::config(json_decode($preset->config, true, 8, JSON_THROW_ON_ERROR));
    try {
        $presetcontext = scope::context($saved['category'], $saved['course']);
    } catch (moodle_exception) {
        continue;
    }
    $params = ['category' => $saved['category'], 'course' => $saved['course'], 'preset' => $preset->id];
    echo html_writer::start_tag('section', ['class' => 'card p-3 mb-3']);
    echo $OUTPUT->heading(s($preset->name), 4);
    $links = ui::link('index', $params, util::str('openreport')) . ui::link('presets', $params, util::str('edit'));
    echo html_writer::tag('p', $links);
    if (has_capability(util::CAP . ':schedule', $presetcontext)) {
        echo html_writer::tag('p', ui::link('watches', $params, util::str('scheduling')));
    }
    if (optional_param('confirmdelete', 0, PARAM_INT) === (int)$preset->id) {
        echo html_writer::tag('p', util::str('deletehelp'));
        ui::form('presets', $base + ['preset' => $preset->id]);
        ui::button('delete', false);
        echo html_writer::end_tag('form');
    } else {
        echo html_writer::tag('p', ui::link('presets', $base + ['confirmdelete' => $preset->id], util::str('delete')));
    }
    echo html_writer::end_tag('section');
}
echo $OUTPUT->footer();
