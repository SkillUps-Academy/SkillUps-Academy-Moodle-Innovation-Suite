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
 * Scheduled reports and indicator goals.
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
$preset = report::preset(required_param('preset', PARAM_INT), $USER->id);
$config = report::config(json_decode($preset->config, true, 8, JSON_THROW_ON_ERROR));
$context = scope::context($config['category'], $config['course'], 'schedule');
$url = util::url('watches', ['preset' => $preset->id]);
$action = optional_param('action', '', PARAM_ALPHA);
$base = ['userid' => $USER->id, 'presetid' => $preset->id];
if ($action !== '') {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', util::C);
    }
    $lock = util::acquire('owner_' . $USER->id);
    try {
        report::preset($preset->id, $USER->id);
        if ($action === 'addsched') {
            $direction = required_param('direction', PARAM_ALPHA);
            $metric = required_param('metric', PARAM_ALPHA);
            $days = required_param('intervaldays', PARAM_INT);
            $target = required_param('target', PARAM_FLOAT);
            if (
                !in_array($direction, ['digest', 'above', 'below'], true) || !in_array($metric, metrics::IDS, true) ||
                    !in_array($days, [1, 7, 30], true) || !is_finite($target) || $target < 0 ||
                    $target > (metrics::percent($metric) ? 100 : 100000000)
            ) {
                throw new moodle_exception('invalidrequest', util::C);
            }
            if ($DB->count_records(report::WATCH, ['userid' => $USER->id]) >= 100) {
                throw new moodle_exception('limit', util::C);
            }
            $DB->insert_record(report::WATCH, $base + [
                'direction' => $direction, 'metric' => $metric, 'target' => $target, 'intervaldays' => $days,
                'nextrun' => time(), 'laststatus' => 'pending',
            ]);
        } else {
            $watch = $DB->get_record(report::WATCH, $base + ['id' => required_param('watch', PARAM_INT)], '*', MUST_EXIST);
            if ($action === 'delete') {
                $DB->delete_records(report::WATCH, ['id' => $watch->id]);
            } else if ($action === 'toggle') {
                $DB->update_record(report::WATCH, ['id' => $watch->id, 'enabled' => (int)!$watch->enabled,
                    'laststatus' => 'pending', 'nextrun' => time()]);
            } else {
                throw new moodle_exception('invalidrequest', util::C);
            }
        }
    } finally {
        $lock->release();
    }
    util::audit($context, $action, $config['period']);
    redirect($url, util::str('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
ui::header(util::str('scheduling'), $context, $url);
echo html_writer::tag('p', ui::link('presets', ui::params($config), util::str('constructor')) .
    ui::link('index', ['preset' => $preset->id], util::str('openreport')));
echo $OUTPUT->heading(s($preset->name), 3);
echo html_writer::tag('p', util::str('schedulehelp'));
ui::form('watches', ['preset' => $preset->id]);
ui::select('direction', ['digest' => util::str('digest'), 'above' => util::str('above'), 'below' => util::str('below')], 'digest');
$options = [];
foreach (metrics::IDS as $option) {
    $options[$option] = util::str('metric_' . $option);
}
ui::select('metric', $options, 'participation');
ui::input('target', 'number', ['min' => 0, 'step' => '0.01', 'value' => 0]);
ui::select('intervaldays', [1 => util::str('daily'), 7 => util::str('weekly'), 30 => util::str('monthly')], 7);
ui::button('addsched');
echo html_writer::end_tag('form');

$records = $DB->get_records(report::WATCH, $base, 'id DESC');
echo $OUTPUT->heading(util::str('myschedules'), 3);
foreach ($records as $watch) {
    echo html_writer::start_tag('section', ['class' => 'card p-3 mb-3']);
    $label = util::str($watch->direction);
    if ($watch->direction !== 'digest') {
        $label = util::str('goallabel', (object)['direction' => $label, 'metric' => util::str('metric_' . $watch->metric),
            'target' => report::display((float)$watch->target, $watch->metric)]);
    }
    echo $OUTPUT->heading(s($label), 4);
    echo html_writer::tag('p', s($watch->enabled ? util::str($watch->laststatus) : util::str('paused')));
    if ($watch->lastchecked) {
        echo html_writer::tag('p', s(util::str('lastcheck', userdate($watch->lastchecked))));
    }
    if ($watch->enabled) {
        echo html_writer::tag('p', s(util::str('nextcheck', userdate($watch->nextrun))));
    }
    ui::form('watches', ['preset' => $preset->id, 'watch' => $watch->id]);
    ui::button('toggle', false);
    if (optional_param('confirmdelete', 0, PARAM_INT) === (int)$watch->id) {
        echo html_writer::tag('p', util::str('deletewatchhelp'));
        ui::button('delete', false);
    } else {
        echo ' ', ui::link('watches', ['preset' => $preset->id, 'confirmdelete' => $watch->id], util::str('delete'));
    }
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('section');
}
echo $OUTPUT->footer();
