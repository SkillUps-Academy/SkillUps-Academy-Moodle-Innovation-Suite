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
 * Display and export the course credential registry.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscertify\access;
use local_skillupscertify\batches;
use local_skillupscertify\licensing\entitlements;
use local_skillupscertify\store;
use local_skillupscertify\ui;

$courseid = required_param('courseid', PARAM_INT);
$course = access::course($courseid);
require_login($course, false);
$context = context_course::instance($courseid);
require_capability(access::CAP . 'viewregistry', $context);
$group = access::group($course, optional_param('groupid', 0, PARAM_INT));
$status = optional_param('status', '', PARAM_ALPHA);
if (!in_array($status, ['valid', 'revoked', 'replaced'], true)) {
    $status = '';
}
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$url = access::url('index', ['courseid' => $courseid, 'groupid' => $group, 'status' => $status]);
ui::page($course, $url, access::text('registry'));
$table = store::ISSUE;
$params = ['courseid' => $courseid];
$from = "FROM {{$table}} i WHERE i.courseid = :courseid";
if ($group) {
    $from .= ' AND EXISTS (SELECT 1 FROM {groups_members} gm WHERE gm.userid = i.userid AND gm.groupid = :groupid)';
    $params['groupid'] = $group;
}
$filter = '';
if ($status !== '') {
    $filter = ' AND i.status = :status';
    $params['status'] = $status;
}
$canexport = has_capability(access::CAP . 'export', $context);
if (data_submitted() && optional_param('export', false, PARAM_BOOL)) {
    require_sesskey();
    require_capability(access::CAP . 'export', $context);
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename('skillups-certificates-' . $courseid);
    $extended = in_array('extended_export', entitlements::status()['features'], true);
    $headings = ['reference', 'title', 'recipient', 'status', 'issuedate', 'fingerprint'];
    if ($extended) {
        $headings = [...$headings, 'revision', 'evidence'];
    }
    $csv->add_data(array_map(static fn(string $key): string => access::text($key), $headings));
    $data = $DB->get_recordset_sql("SELECT i.* $from $filter ORDER BY i.id DESC", $params);
    try {
        foreach ($data as $entry) {
            $row = ui::row($entry);
            $columns = [$row['reference'], $row['title'], $row['recipient'], $row['status'], $row['issuedate'], $entry->pdfhash];
            if ($extended) {
                $snapshot = store::snapshot($entry);
                $revision = store::revision((object)['id' => $entry->templateid], $entry->revisionid);
                $columns = [...$columns, (string)$revision->revision, access::text('source_' . $snapshot['evidence']['kind'])];
            }
            $csv->add_data(array_map(static fn(string $value): string =>
                preg_match('/^[\p{Z}\x00-\x20]*[=+\-@]/u', $value) ? "'" . $value : $value, $columns));
        }
    } finally {
        $data->close();
    }
    store::audit($courseid, 'export', $group);
    $csv->download_file();
}
$groups = access::limited($course) ? [] : [['id' => 0, 'name' => access::text('allgroups'), 'selected' => !$group]];
foreach (access::groups($course) as $option) {
    $groups[] = ['id' => $option->id, 'name' => $option->name, 'selected' => $option->id == $group];
}
$statuses = [['value' => '', 'name' => access::text('allstatuses'), 'selected' => $status === '']];
foreach (['valid', 'revoked', 'replaced'] as $state) {
    $statuses[] = ['value' => $state, 'name' => access::text('status_' . $state), 'selected' => $state === $status];
}
$count = $DB->count_records_sql("SELECT COUNT(1) $from $filter", $params);
$counts = $DB->get_records_sql('SELECT i.status, COUNT(1) AS total ' . $from . ' GROUP BY i.status', ['courseid' => $courseid]
    + ($group ? ['groupid' => $group] : []));
$data = ui::nav($course) + [
    'groups' => $groups, 'statuses' => $statuses, 'courseid' => $courseid, 'url' => $url->out(false),
    'groupid' => $group, 'statusfilter' => $status, 'canexport' => $canexport, 'sesskey' => sesskey(),
    'pager' => $OUTPUT->paging_bar($count, $page, 25, $url),
];
foreach (['valid', 'revoked', 'replaced'] as $state) {
    $data['metrics'][] = ['name' => access::text('status_' . $state), 'count' => $counts[$state]->total ?? 0];
}
foreach ($DB->get_records_sql("SELECT i.* $from $filter ORDER BY i.id DESC", $params, $page * 25, 25) as $entry) {
    $data['rows'][] = ui::row($entry) + [
        'recordurl' => access::url('record', ['courseid' => $courseid, 'id' => $entry->id])->out(false),
    ];
}
$batchcriteria = ['courseid' => $courseid] + ($group ? ['groupid' => $group] : []);
foreach ($DB->get_records(store::BATCH, $batchcriteria, 'id DESC', '*', 0, 15) as $batch) {
    if (batches::visible($course, $batch)) {
        $data['batches'][] = [
            'id' => $batch->id, 'date' => userdate($batch->timecreated), 'state' => access::text('batch_' . $batch->state),
            'url' => access::url('batch', ['courseid' => $courseid, 'id' => $batch->id])->out(false),
        ];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/registry', $data);
echo $OUTPUT->footer();
