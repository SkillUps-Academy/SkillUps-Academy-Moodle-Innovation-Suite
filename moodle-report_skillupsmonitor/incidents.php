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
 * View operational incidents and record authorized reviews.
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
$context = ui::access();
$id = optional_param('incident', 0, PARAM_INT);
$view = optional_param('view', 'all', PARAM_ALPHA);
$key = optional_param('check', '', PARAM_ALPHA);
$checks = array_merge(collector::CHECKS, ['coverage']);
if (!in_array($view, ['all', 'open', 'recovered'], true) || ($key !== '' && !in_array($key, $checks, true))) {
    throw new moodle_exception('badrequest', util::C);
}
$args = ['view' => $view, 'check' => $key, 'incident' => $id];
if (optional_param('action', '', PARAM_ALPHA) === 'acknowledge') {
    ui::post();
    store::acknowledge($id);
    redirect(util::url('incidents', $args), util::str('acknowledged'), null, \core\output\notification::NOTIFY_SUCCESS);
}
util::audit('incidents', $id);
$detail = $id ? $DB->get_record(store::INCIDENT, ['id' => $id], '*', MUST_EXIST) : null;
ui::header('incidents', util::str('incidents'), $args);
if ($detail) {
    echo $OUTPUT->heading(s(util::str('check_' . $detail->checkkey)), 3);
    $rows = [
        [s(util::str('firstobserved')), s(userdate($detail->opened))],
        [s(util::str('detected')), s(userdate($detail->detected))],
        [s(util::str('recovered')), $detail->closed ? s(userdate($detail->closed)) : '—'],
        [s(util::str('acktime')), $detail->timeack ? s(userdate($detail->timeack)) : '—'],
    ];
    echo ui::table('incidentdetail', ['metric', 'value'], $rows);
    if (!$detail->timeack && has_capability(util::CAP . ':manage', $context)) {
        ui::form('incidents', $args);
        ui::button('acknowledge');
        echo html_writer::end_tag('form');
    }
    $table = store::NOTICE;
    $counts = $DB->get_records_sql(
        "SELECT state, COUNT(1) AS amount FROM {{$table}} WHERE incidentid = :incident GROUP BY state",
        ['incident' => $id]
    );
    if ($counts) {
        $rows = [];
        foreach ($counts as $row) {
            $rows[] = [s(util::str('status_' . $row->state)), s((string)$row->amount)];
        }
        echo ui::table('delivery', ['state', 'value'], $rows);
    }
    echo html_writer::tag('p', html_writer::link(util::url('incidents'), s(util::str('backlist'))));
} else {
    $groups = ['' => util::str('allchecks')];
    foreach ($checks as $name) {
        $groups[$name] = util::str('check_' . $name);
    }
    ui::form('incidents', [], false);
    ui::select('view', ['all' => util::str('view_all'), 'open' => util::str('status_open'),
        'recovered' => util::str('status_recovered')], $view);
    ui::select('check', $groups, $key);
    ui::button('apply');
    echo html_writer::end_tag('form');
    $where = match ($view) {
        'open' => 'closed = 0',
        'recovered' => 'closed > 0',
        default => '1 = 1',
    };
    $params = [];
    if ($key !== '') {
        $where .= ' AND checkkey = :checkkey';
        $params['checkkey'] = $key;
    }
    $total = $DB->count_records_select(store::INCIDENT, $where, $params);
    $page = max(0, min(optional_param('page', 0, PARAM_INT), max(0, (int)ceil($total / 25) - 1)));
    $records = $DB->get_records_select(store::INCIDENT, $where, $params, 'opened DESC, id DESC', '*', $page * 25, 25);
    $rows = [];
    foreach ($records as $row) {
        $rows[] = [
            html_writer::link(util::url('incidents', ['incident' => $row->id]), s(util::str('check_' . $row->checkkey))),
            ui::state($row->closed ? 'recovered' : 'open'), ui::state($row->severity),
            s(userdate($row->opened)), $row->closed ? s(userdate($row->closed)) : '—',
        ];
    }
    if ($rows) {
        echo ui::table('incidents', ['check', 'state', 'severity', 'firstobserved', 'recovered'], $rows);
        echo $OUTPUT->paging_bar($total, $page, 25, util::url('incidents', $args));
    } else {
        echo $OUTPUT->notification(util::str('noincidents'), 'info');
    }
}
echo $OUTPUT->footer();
