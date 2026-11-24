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
 * Display audit evidence, queue scans, review findings and export authorized results.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_skillupssecurity\access;
use report_skillupssecurity\engine;
use report_skillupssecurity\licensing\entitlements;
use report_skillupssecurity\observer;
use report_skillupssecurity\store;
use report_skillupssecurity\ui;

require_login();
access::require();
$tab = optional_param('tab', 'dashboard', PARAM_ALPHA);
$tabs = ['dashboard', 'findings', 'roles', 'inventory', 'events', 'alerts'];
if (!in_array($tab, [...$tabs, 'detail'], true)) {
    $tab = 'dashboard';
}
$personal = access::can('inventory');
if (in_array($tab, ['inventory', 'events', 'alerts'], true)) {
    access::require('inventory');
}
$id = optional_param('id', 0, PARAM_INT);
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$scanid = optional_param('scan', 0, PARAM_INT);
$kind = optional_param('kind', '', PARAM_ALPHA);
$severity = optional_param('severity', '', PARAM_ALPHA);
$presence = optional_param('presence', 'active', PARAM_ALPHA);
if (!in_array($kind, engine::KINDS, true)) {
    $kind = '';
}
if (!in_array($severity, engine::SEVERITIES, true)) {
    $severity = '';
}
if (!in_array($presence, ['active', 'resolved', 'all'], true)) {
    $presence = 'active';
}
$stream = optional_param('stream', '', PARAM_ALPHA);
if (!in_array($stream, ['native', 'report'], true)) {
    $stream = '';
}
$filters = ['tab' => $tab, 'kind' => $kind, 'severity' => $severity, 'presence' => $presence];
if ($stream !== '') {
    $filters['stream'] = $stream;
}
$url = access::url('index', $filters + ($id ? ['id' => $id] : []) + ($scanid ? ['scan' => $scanid] : []));
admin_externalpage_setup('skillupssecurity', '', null, $url);
$PAGE->set_cacheable(false);
$latest = store::latest();
$export = false;
$extended = false;
if (data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    switch ($action) {
        case 'queue':
            $queued = store::queue();
            redirect($url, access::text($queued ? 'queued' : 'alreadyqueued'));
            break;
        case 'review':
            store::review($id, required_param('state', PARAM_ALPHA), required_param('note', PARAM_TEXT));
            redirect($url, access::text('reviewsaved'));
            break;
        case 'export':
            access::require('export');
            if (!in_array($tab, ['findings', 'events', 'inventory'], true)) {
                throw new moodle_exception('unsupportedexport', access::C);
            }
            $export = true;
            $extended = in_array('extended_evidence', entitlements::status()['features'], true);
            break;
        default:
            throw new moodle_exception('invalidreview', access::C);
    }
}
$data = [
    'title' => access::text($tab), 'tab' => $tab, 'sesskey' => sesskey(), 'url' => $url->out(false),
    'note' => access::text('coveragewarning'),
];
foreach ($tabs as $entry) {
    if ($personal || !in_array($entry, ['inventory', 'events', 'alerts'], true)) {
        $data['tabs'][] = ['label' => access::text($entry), 'url' => access::url('index', ['tab' => $entry])->out(false),
            'active' => $entry === $tab];
    }
}
$rows = [];
$count = 0;
$headers = [];
$limit = $export ? 10000 : 25;
$offset = $export ? 0 : $page * 25;
$auditref = 0;
if ($tab === 'dashboard') {
    store::audit('viewdashboard', $latest->id ?? 0);
    $data['dashboard'] = true;
    $data['canrun'] = access::can('run');
    $data['lastscan'] = $latest ? ui::date($latest->timecreated) : access::text('noscan');
    $table = store::FINDING;
    $where = 'present = 1' . ($personal ? '' : ' AND userid = 0');
    $counts = $DB->get_records_sql("SELECT severity, COUNT(*) AS total FROM {{$table}} WHERE $where GROUP BY severity");
    foreach (engine::SEVERITIES as $value) {
        $data['metrics'][] = ['label' => access::text('severity_' . $value), 'value' => $counts[$value]->total ?? 0,
            'url' => access::url('index', ['tab' => 'findings', 'severity' => $value])->out(false)];
    }
    if ($latest) {
        foreach (json_decode($latest->coverage, true, 12, JSON_THROW_ON_ERROR) as $rule => $complete) {
            $data['coverage'][] = [
                'label' => access::text('rule_' . $rule), 'state' => access::text($complete ? 'complete' : 'partial'),
            ];
        }
    }
} else if ($tab === 'detail') {
    $finding = $DB->get_record(store::FINDING, ['id' => $id] + ($personal ? [] : ['userid' => 0]), '*', MUST_EXIST);
    if ($finding->userid) {
        access::require('inventory');
    }
    store::audit('viewdetail', $id);
    $data['detail'] = [
        'id' => $id, 'title' => access::text('rule_' . $finding->ruleid),
        'recommendation' => access::text('fix_' . $finding->ruleid),
        'description' => access::text('about_' . $finding->ruleid),
        'state' => access::text($finding->present ? 'active' : 'resolved'),
        'kind' => access::text('kind_' . $finding->kind),
        'severity' => access::text('severity_' . $finding->severity),
        'review' => access::text('state_' . $finding->reviewstate),
        'person' => $finding->userid ? ui::person($finding->userid) : '',
        'scope' => ui::scope($finding->contextid),
        'first' => ui::date($finding->firstseen), 'last' => ui::date($finding->lastseen),
        'evidence' => ui::evidence($finding),
        'canreview' => access::can('review'),
    ];
    foreach (store::STATES as $state) {
        $data['detail']['states'][] = ['value' => $state, 'label' => access::text('state_' . $state),
            'selected' => $state === $finding->reviewstate];
    }
    foreach ($DB->get_records(store::HISTORY, ['findingid' => $id], 'id DESC', '*', $offset, $limit) as $event) {
        $data['detail']['history'][] = [
            'action' => access::text('state_' . $event->action), 'date' => ui::date($event->timecreated),
            'actor' => $personal ? ui::person($event->actorid) : '', 'note' => $personal ? $event->note : '',
        ];
    }
    $data['pager'] = $OUTPUT->paging_bar($DB->count_records(store::HISTORY, ['findingid' => $id]), $page, 25, $url);
} else {
    $table = true;
    switch ($tab) {
        case 'findings':
            $headers = ['finding', 'kind', 'severity', 'presence', 'reviewstate', 'lastseen'];
            $where = $personal ? '1 = 1' : 'userid = 0';
            $params = [];
            if ($kind) {
                $where .= ' AND kind = :kind';
                $params['kind'] = $kind;
            }
            if ($severity) {
                $where .= ' AND severity = :severity';
                $params['severity'] = $severity;
            }
            if ($presence !== 'all') {
                $where .= ' AND present = :present';
                $params['present'] = (int)($presence === 'active');
            }
            $data['filters'] = [
                [
                    'name' => 'kind', 'label' => access::text('kind'),
                    'choices' => report_skillupssecurity_filter_options(engine::KINDS, $kind, 'kind_'),
                ],
                [
                    'name' => 'severity', 'label' => access::text('severity'),
                    'choices' => report_skillupssecurity_filter_options(engine::SEVERITIES, $severity, 'severity_'),
                ],
                [
                    'name' => 'presence', 'label' => access::text('presence'),
                    'choices' => report_skillupssecurity_filter_options(['active', 'resolved', 'all'], $presence),
                ],
            ];
            $count = $DB->count_records_select(store::FINDING, $where, $params);
            $findings = $DB->get_records_select(store::FINDING, $where, $params, 'lastseen DESC, id DESC', '*', $offset, $limit);
            foreach ($findings as $f) {
                $link = access::url('index', ['tab' => 'detail', 'id' => $f->id])->out(false);
                $cells = [
                    ui::cell(access::text('rule_' . $f->ruleid), $link), ui::cell(access::text('kind_' . $f->kind)),
                    ui::cell(access::text('severity_' . $f->severity)), ui::cell(access::text($f->present ? 'active' : 'resolved')),
                    ui::cell(access::text('state_' . $f->reviewstate)), ui::cell(ui::date($f->lastseen)),
                ];
                if ($export) {
                    $cells[] = ui::cell($personal && $f->userid ? $f->userid : '');
                    $cells[] = ui::cell($f->contextid ?: '');
                    $cells[] = ui::cell($f->evidence);
                    $cells[] = ui::cell(access::text('fix_' . $f->ruleid));
                    if ($extended) {
                        $cells[] = ui::cell($personal ? $f->fingerprint : '');
                        $cells[] = ui::cell($f->lastscan);
                    }
                }
                $rows[] = $cells;
            }
            if ($export) {
                $headers = [...$headers, 'account', 'context', 'evidence', 'recommendation'];
                if ($extended) {
                    $headers = [...$headers, 'signature', 'scanref'];
                }
            }
            break;
        case 'roles':
            $headers = ['role', 'shortname', 'archetype', 'capabilities'];
            [$rows, $count] = ui::roles($offset);
            $data['intro'] = access::text('rolesintro');
            break;
        case 'inventory':
            $headers = ['account', 'context', 'role', 'capabilities', 'presence', 'lastaccess'];
            $scan = $scanid ? $DB->get_record(store::SCAN, ['id' => $scanid], '*', MUST_EXIST) : $latest;
            $data['snapshots'] = [];
            foreach ($DB->get_records(store::SCAN, null, 'id DESC', 'id,timecreated', 0, 30) as $past) {
                $data['snapshots'][] = ['label' => ui::date($past->timecreated),
                    'url' => access::url('index', ['tab' => 'inventory', 'scan' => $past->id])->out(false)];
            }
            if ($scan) {
                $auditref = (int)$scan->id;
                $data['intro'] = access::text('inventorydate', ui::date($scan->timecreated));
                $coverage = json_decode($scan->coverage, true, 12, JSON_THROW_ON_ERROR);
                if (empty($coverage['unusualscope'])) {
                    $data['warning'] = access::text('inventorypartial');
                }
                $count = $DB->count_records(store::PRIVILEGE, ['scanid' => $scan->id]);
                $roles = get_all_roles();
                foreach ($DB->get_records(store::PRIVILEGE, ['scanid' => $scan->id], 'id ASC', '*', $offset, $limit) as $row) {
                    $role = $row->roleid ? ($roles[$row->roleid]->shortname ?? '#' . $row->roleid) : access::text('siteadmin');
                    $caps = json_decode($row->capabilities, true, 12, JSON_THROW_ON_ERROR);
                    $rows[] = [
                        ui::cell(ui::person($row->userid)), ui::cell(ui::scope($row->contextid)), ui::cell($role),
                        ui::cell($row->roleid ? implode(', ', $caps) : access::text('siteadmin')),
                        ui::cell(access::text($row->suspended ? 'suspended' : 'active')), ui::cell(ui::date($row->lastaccess)),
                    ];
                }
            }
            break;
        case 'events':
            $headers = ['date', 'operation', 'actor', 'subject', 'context', 'reference', 'metadata'];
            $where = '1 = 1';
            $params = [];
            if ($stream !== '') {
                [$in, $params] = $DB->get_in_or_equal(
                    array_values(array_unique(observer::EVENTTYPES)),
                    SQL_PARAMS_NAMED,
                    'events',
                    $stream === 'native'
                );
                $where = "action $in";
            }
            $data['filters'] = [[
                'name' => 'stream', 'label' => access::text('stream'),
                'choices' => report_skillupssecurity_filter_options(['native', 'report'], $stream, 'stream_'),
            ]];
            $count = $DB->count_records_select(store::ACTIVITY, $where, $params);
            foreach ($DB->get_records_select(store::ACTIVITY, $where, $params, 'id DESC', '*', $offset, $limit) as $row) {
                $rows[] = [
                    ui::cell(ui::date($row->timecreated)), ui::cell(access::text('action_' . $row->action)),
                    ui::cell(ui::person($row->actorid)), ui::cell($row->subjectid ? ui::person($row->subjectid) : ''),
                    ui::cell(ui::scope($row->contextid)), ui::cell($row->objectid ?: ''), ui::cell($row->detail ?? ''),
                ];
            }
            break;
        case 'alerts':
            $headers = ['date', 'account', 'reference', 'presence'];
            $count = $DB->count_records(store::OUTBOX);
            foreach ($DB->get_records(store::OUTBOX, [], 'id DESC', '*', $offset, $limit) as $row) {
                $rows[] = [ui::cell(ui::date($row->timecreated)), ui::cell(ui::person($row->userid)),
                    ui::cell($row->scanid), ui::cell(access::text('alert_' . $row->state))];
            }
            break;
    }
    store::audit(($export ? 'export' : 'view') . $tab, $auditref);
    if ($export) {
        require_once($CFG->libdir . '/csvlib.class.php');
        $csv = new csv_export_writer();
        $csv->set_filename('skillups-security-' . $tab);
        $csv->add_data(array_map(static fn(string $key): string => access::text($key), $headers));
        foreach ($rows as $row) {
            $csv->add_data(array_map(static function (array $cell): string {
                $value = $cell['text'];
                return preg_match('/^[\p{Z}\x00-\x20]*[=+\-@]/u', $value) ? "'" . $value : $value;
            }, $row));
        }
        $csv->download_file();
    }
    $data['table'] = $table;
    $data['hasfilters'] = !empty($data['filters']);
    $data['hassnapshots'] = !empty($data['snapshots']);
    $data['headings'] = array_map(static fn(string $key): array => ['label' => access::text($key)], $headers);
    $data['rows'] = array_map(static fn(array $cells): array => ['cells' => $cells], $rows);
    $data['pager'] = $OUTPUT->paging_bar($count, $page, 25, $url);
    $data['canexport'] = access::can('export') && in_array($tab, ['findings', 'events', 'inventory'], true);
    $data['exporthelp'] = $data['canexport'] ? access::text('exporthelp') : '';
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/report', $data);
echo $OUTPUT->footer();

/**
 * Build fixed filter choices.
 *
 * @param array $values Choice keys
 * @param string $selected Selection
 * @param string $prefix Translation prefix or empty to use the original choice
 * @return array
 */
function report_skillupssecurity_filter_options(array $values, string $selected, string $prefix = ''): array {
    if ($prefix !== '') {
        array_unshift($values, '');
    }
    return array_map(static fn(string $value): array => [
        'value' => $value, 'label' => access::text($value === '' ? 'all' : $prefix . $value), 'selected' => $value === $selected,
    ], $values);
}
