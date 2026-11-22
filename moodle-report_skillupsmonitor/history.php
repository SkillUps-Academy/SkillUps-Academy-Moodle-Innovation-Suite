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
 * View and export availability observations.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsmonitor\availability;
use report_skillupsmonitor\licensing\entitlements;
use report_skillupsmonitor\store;
use report_skillupsmonitor\ui;
use report_skillupsmonitor\util;

require_login();
ui::access();
$hours = optional_param('hours', 24, PARAM_INT);
$mode = optional_param('action', '', PARAM_ALPHA);
$summary = availability::report(time(), $hours);
$licensed = in_array('aggregate_export', entitlements::status()['features'], true);
if (in_array($mode, ['summarycsv', 'timelinecsv'], true)) {
    ui::post();
    if ($mode === 'timelinecsv' && !$licensed) {
        throw new moodle_exception('badrequest', util::C);
    }
    util::audit($mode);
    if ($mode === 'summarycsv') {
        $keys = ['expected', 'up', 'down', 'maintenance', 'unknown', 'available', 'coverage', 'p95us'];
        $columns = [util::str('intervalstart'), util::str('intervalend')];
        $record = [gmdate('c', $summary['from']), gmdate('c', $summary['until'])];
        foreach ($keys as $key) {
            $columns[] = util::str('history_' . $key);
            $value = $summary[$key];
            $record[] = is_float($value) ? sprintf('%.4F', $value) : ($value ?? '');
        }
        \core\dataformat::download_data('skillups-monitor-availability', 'csv', $columns, [$record]);
        exit;
    }
    $records = $DB->get_recordset_select(
        store::SAMPLE,
        'slot >= :fromslot AND slot < :toslot',
        ['fromslot' => $summary['from'], 'toslot' => $summary['until']],
        'slot ASC',
        'id,slot,readiness,latencyus'
    );
    $rows = (static function () use ($records, $summary): Generator {
        $slot = $summary['from'];
        try {
            foreach ($records as $record) {
                while ($slot < $record->slot) {
                    yield [gmdate('c', $slot), 'unknown', ''];
                    $slot += util::SLOT;
                }
                yield [gmdate('c', $record->slot), $record->readiness, $record->latencyus < 0 ? '' : $record->latencyus];
                $slot = $record->slot + util::SLOT;
            }
            while ($slot < $summary['until']) {
                yield [gmdate('c', $slot), 'unknown', ''];
                $slot += util::SLOT;
            }
        } finally {
            $records->close();
        }
    })();
    \core\dataformat::download_data(
        'skillups-monitor-timeline',
        'csv',
        [util::str('intervalstart'), util::str('readiness'), util::str('history_p95us_raw')],
        $rows
    );
    exit;
}

$page = max(0, min(400, optional_param('page', 0, PARAM_INT)));
$args = ['hours' => $hours];
util::audit('history');
ui::header('history', util::str('history'), $args);
echo html_writer::tag('p', s(util::str('availabilityhelp')));
ui::form('history', [], false);
$options = [];
foreach (availability::HOURS as $duration) {
    $options[$duration] = util::str('hourslabel', $duration);
}
ui::select('hours', $options, $hours);
ui::button('apply');
echo html_writer::end_tag('form');
echo html_writer::tag('p', s(util::str('periodlabel', (object)[
    'from' => userdate($summary['from']), 'until' => userdate($summary['until']),
])));
$rows = [];
foreach (['expected', 'up', 'down', 'maintenance', 'unknown', 'available', 'coverage', 'p95us'] as $key) {
    $value = $summary[$key];
    if ($key === 'p95us') {
        $value = ui::latency($value);
    } else if (in_array($key, ['available', 'coverage'], true)) {
        $value = $value === null ? '—' : util::str('percent', format_float($value, 2));
    }
    $rows[] = [s(util::str('history_' . $key)), s((string)$value)];
}
echo ui::table('availabilitysummary', ['metric', 'value'], $rows);
ui::form('history', $args);
ui::button('summarycsv');
if ($licensed) {
    ui::button('timelinecsv');
}
echo html_writer::end_tag('form');

$window = ['fromslot' => $summary['from'], 'toslot' => $summary['until']];
$where = 'slot >= :fromslot AND slot < :toslot';
$total = $DB->count_records_select(store::SAMPLE, $where, $window);
$page = min($page, max(0, (int)ceil($total / 25) - 1));
$records = $DB->get_records_select(store::SAMPLE, $where, $window, 'slot DESC', 'id,captured,readiness,latencyus', $page * 25, 25);
$rows = [];
foreach ($records as $record) {
    $rows[] = [s(userdate($record->captured)), ui::state($record->readiness), s(ui::latency($record->latencyus))];
}
if ($rows) {
    echo ui::table('sampletimeline', ['observed', 'readiness', 'probelatency'], $rows);
    echo $OUTPUT->paging_bar($total, $page, 25, util::url('history', $args));
}
echo $OUTPUT->footer();
