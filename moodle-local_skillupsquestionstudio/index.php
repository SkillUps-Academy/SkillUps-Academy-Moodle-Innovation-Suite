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
 * SkillUps Question Studio index.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\licensing\entitlements;
use local_skillupsquestionstudio\studio;
use local_skillupsquestionstudio\view;

$courseid = required_param('courseid', PARAM_INT);
require_login(get_course($courseid), false);
access::actor($USER->id, $courseid);
$context = access::context($courseid);
$url = view::url('index', ['courseid' => $courseid]);
view::page($url, $courseid, 'dashboard');
$license = entitlements::status();
$exportable = has_capability('local/skillupsquestionstudio:publish', $context) &&
    in_array('aggregate_export', $license['features'], true);
$items = 'FROM {local_skillupsquestionstudio_item} i
    JOIN {local_skillupsquestionstudio_project} p ON p.id = i.projectid';
$stats = $DB->get_records_sql("SELECT i.origin, COUNT(*) AS total $items WHERE p.courseid = ? GROUP BY i.origin", [$courseid]);
if (data_submitted()) {
    require_sesskey();
    if (optional_param('exportstats', 0, PARAM_BOOL)) {
        if (!$exportable) {
            access::fail('accessdenied');
        }
        $export = array_map(static fn($row) => [
            'origin' => get_string('provider_' . $row->origin, access::C), 'total' => (int)$row->total,
        ], array_values($stats));
        \core\dataformat::download_data('skillups-question-studio', 'csv', [
            'origin' => get_string('provider', access::C), 'total' => get_string('count', access::C),
        ], $export);
        exit;
    }
    $name = required_param('name', PARAM_TEXT);
    $id = studio::create($courseid, $USER->id, $name);
    redirect(view::url('project', ['courseid' => $courseid, 'id' => $id]));
}
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$scope = ['courseid' => $courseid];
$projects = $DB->get_records(access::C . '_project', $scope, 'timemodified DESC,id DESC', '*', $page * 20, 20);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/dashboard', [
    'url' => $url->out(false), 'sesskey' => sesskey(),
    'edition' => get_string('edition_' . $license['state'], access::C),
    'expiry' => $license['expires'] ? userdate($license['expires']) : '',
    'exportable' => $exportable,
    'siteid' => is_siteadmin() ? entitlements::site_id() : '',
    'manual' => $stats['manual']->total ?? 0, 'local' => $stats['local']->total ?? 0, 'ai' => $stats['ai']->total ?? 0,
    'projects' => array_values(array_map(static fn($project) => [
        'name' => $project->name, 'date' => userdate($project->timemodified),
        'href' => view::url('project', ['courseid' => $courseid, 'id' => $project->id])->out(false),
    ], $projects)),
    'paging' => $OUTPUT->paging_bar($DB->count_records(access::C . '_project', $scope), $page, 20, $url),
]);
echo $OUTPUT->footer();
