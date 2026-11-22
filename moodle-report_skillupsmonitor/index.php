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
 * Display site operational monitoring observations.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsmonitor\collector;
use report_skillupsmonitor\store;
use report_skillupsmonitor\ui;
use report_skillupsmonitor\util;

require_login();
ui::access();
$now = time();
$policy = util::policy();
$row = store::latest();
$data = $row ? store::payload($row) : [];
$cron = collector::cron($now, $policy);
$open = $DB->count_records(store::INCIDENT, ['closed' => 0]);
util::audit('dashboard');

ui::header('index', util::str('pluginname'));
if (!$row || $now - $row->captured > $policy['stale']) {
    echo $OUTPUT->notification(util::str($row ? 'stalesample' : 'nosamples'), 'warning');
}
echo $OUTPUT->render_from_template(util::C . '/dashboard', [
    'cards' => [
        ['label' => util::str('readiness'), 'value' => util::str('status_' . ($row->readiness ?? 'unknown'))],
        ['label' => util::str('probelatency'), 'value' => ui::latency($row->latencyus ?? null)],
        ['label' => util::str('livecron'), 'value' => util::str('status_' . $cron['state'])],
        ['label' => util::str('openincidents'), 'value' => (string)$open],
    ],
    'sample' => $row ? util::str('capturedlabel', userdate($row->captured)) : '',
]);
echo html_writer::tag('p', s($cron['last'] ? util::str('lastcronlabel', userdate($cron['last'])) : util::str('cronunobserved')));
$checks = [];
foreach ($data['checks'] ?? [] as $key => $value) {
    $checks[] = [s(util::str('check_' . $key)), ui::state($value)];
}
if ($checks) {
    echo ui::table('checks', ['check', 'state'], $checks);
}
$queue = $data['queue'] ?? [];
if ($queue) {
    $rows = [];
    foreach (['enabled', 'disabled', 'due', 'failed', 'running', 'adhocdue', 'adhocfailed'] as $key) {
        $rows[] = [s(util::str('queue_' . $key)), s(ui::count_floor($queue[$key], !empty($queue[$key . 'capped'])))];
    }
    $recent = $data['recentfailures'];
    $rows[] = [s(util::str('recentfailures')), s(ui::count_floor($recent, $recent === util::CAPCOUNT))];
    echo ui::table('queuesummary', ['metric', 'value'], $rows);
}
if (!empty($data['usage'])) {
    $usage = $data['usage'];
    echo ui::table('usage', ['metric', 'value'], [
        [s(util::str('courses')), s((string)$usage['courses'])],
        [s(util::str('activeaccounts')), s((string)$usage['activeaccounts'])],
    ]);
    echo html_writer::tag('p', s(util::str('capturedlabel', userdate($usage['captured']))), ['class' => 'text-muted']);
}
echo html_writer::tag('p', s(util::str('internalnotice')));
echo $OUTPUT->footer();
