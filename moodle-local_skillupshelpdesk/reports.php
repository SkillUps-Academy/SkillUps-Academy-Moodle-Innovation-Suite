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
 * Scoped aggregate reports and safe export entry point.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\licensing\entitlements;
use local_skillupshelpdesk\queries;
use local_skillupshelpdesk\storage;
use local_skillupshelpdesk\ui;

$base = access::url('reports');
require_login();
ui::page($base, access::text('reports'));
access::require(access::can('work') || access::can('supervise'));
$dimension = optional_param('dimension', 'assignee', PARAM_ALPHA);
$dimension = array_key_exists($dimension, queries::GROUPS) ? $dimension : 'assignee';
$filter = queries::request_filters();
$query = ['dimension' => $dimension] + $filter;
$format = optional_param('export', '', PARAM_ALPHA);
$jsonenabled = in_array('json_export', entitlements::status()['features'], true);
$headers = [
    'group', 'metric_total', 'metric_open', 'metric_closed', 'responsebreaches', 'resolvebreaches', 'ratedcount', 'ratingmean',
];
$headinglabels = array_map([access::class, 'text'], $headers);
$shape = static function (stdClass $row) use ($dimension): array {
    return [
        'bucket' => ui::bucket($dimension, (int)$row->bucket),
        'totalcount' => (int)$row->totalcount,
        'opencount' => (int)$row->opencount,
        'closedcount' => (int)$row->closedcount,
        'responsecount' => (int)$row->responsecount,
        'resolvecount' => (int)$row->resolvecount,
        'ratedcount' => (int)$row->ratedcount,
        'ratingmean' => $row->ratingmean === null ? '' : number_format((float)$row->ratingmean, 2, '.', ''),
    ];
};
if ($format !== '') {
    require_sesskey();
    access::require($format === 'csv' || ($format === 'json' && $jsonenabled));
    storage::audit(0, 'exportreport');
    $result = queries::report($dimension, $filter);
    if ($format === 'csv') {
        require_once($CFG->libdir . '/csvlib.class.php');
        $csv = new csv_export_writer();
        $csv->set_filename('skillups-helpdesk-' . $dimension);
        $csv->add_data($headinglabels);
        try {
            foreach ($result as $row) {
                $record = $shape($row);
                if (preg_match('/^\s*[=+\-@]/u', $record['bucket']) || preg_match('/^[\t\r\n]/', $record['bucket'])) {
                    $record['bucket'] = "'" . $record['bucket'];
                }
                $csv->add_data(array_values($record));
            }
        } finally {
            $result->close();
        }
        $csv->download_file();
    }
    \core\session\manager::write_close();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="skillups-helpdesk-' . $dimension . '.json"');
    header('Cache-Control: private, no-store');
    echo '{"version":1,"dimension":' . json_encode($dimension) . ',"rows":[';
    $comma = '';
    try {
        foreach ($result as $row) {
            echo $comma . json_encode($shape($row), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            $comma = ',';
        }
    } finally {
        $result->close();
    }
    echo ']}';
    exit;
}
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$dimensions = [];
foreach (array_keys(queries::GROUPS) as $kind) {
    $dimensions[$kind] = access::text('kind_' . $kind);
}
$rows = [];
$result = queries::report($dimension, $filter, $page * 25, 25);
try {
    foreach ($result as $row) {
        $rows[] = $shape($row);
    }
} finally {
    $result->close();
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::COMPONENT . '/reports', ui::nav() + ui::filters($filter, true) + [
    'reportsmode' => true,
    'filterurl' => $base->out(false),
    'dimensions' => ui::options($dimensions, $dimension),
    'headers' => $headinglabels,
    'rows' => $rows,
    'csvurl' => access::url('reports', $query + ['export' => 'csv', 'sesskey' => sesskey()])->out(false),
    'jsonurl' => $jsonenabled ? access::url('reports', $query + ['export' => 'json', 'sesskey' => sesskey()])->out(false) : '',
    'pager' => $OUTPUT->paging_bar(queries::group_count($dimension, $filter), $page, 25, access::url('reports', $query)),
]);
echo $OUTPUT->footer();
