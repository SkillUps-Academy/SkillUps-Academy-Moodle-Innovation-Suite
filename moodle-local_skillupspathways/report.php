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
 * Group-aware progress reports, opt-in aggregate export and explicit version migration.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupspathways\access;
use local_skillupspathways\licensing\entitlements;
use local_skillupspathways\progress;
use local_skillupspathways\store;
use local_skillupspathways\ui;

$courseid = required_param('courseid', PARAM_INT);
$pathid = required_param('pathid', PARAM_INT);
[$course, $context] = access::course($courseid);
require_login($course);
$group = access::report_group($course, optional_param('groupid', 0, PARAM_INT));
$path = store::path($courseid, $pathid);
$url = access::url('report', $courseid, ['pathid' => $pathid, 'groupid' => $group]);
ui::page($course, $url, access::text('reports'));
$manager = has_capability(access::CAP . 'manage', $context);
if (data_submitted()) {
    require_sesskey();
    if (!required_param('confirm', PARAM_BOOL)) {
        access::deny();
    }
    progress::migrate(
        $course,
        $pathid,
        required_param('userid', PARAM_INT),
        required_param('versionid', PARAM_INT),
        $group
    );
    redirect($url, access::text('migrationdone'), null, \core\output\notification::NOTIFY_SUCCESS);
}

[$enrolled, $params] = get_enrolled_sql($context, access::CAP . 'view', $group, true);
$table = store::PROGRESS;
$from = "FROM {{$table}} p JOIN ($enrolled) e ON e.id = p.userid JOIN {user} u ON u.id = p.userid
          WHERE p.pathid = :selected AND u.deleted = 0 AND u.suspended = 0";
$params += ['selected' => $pathid];
$summary = $DB->get_record_sql(
    "SELECT COUNT(*) AS registered, SUM(CASE WHEN p.completedat > 0 THEN 1 ELSE 0 END) AS completed $from",
    $params
);
$registered = (int)$summary->registered;
$completed = (int)$summary->completed;
$licensed = in_array('aggregate_export', entitlements::status()['features'], true);
if (optional_param('export', false, PARAM_BOOL)) {
    require_sesskey();
    if (!$licensed) {
        access::deny();
    }
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename('skillups-pathway-' . $pathid);
    $csv->add_data([access::text('registered'), access::text('completed'), access::text('inprogress')]);
    $csv->add_data([$registered, $completed, $registered - $completed]);
    store::audit($courseid, $pathid, 'export');
    $csv->download_file();
}
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$records = $DB->get_records_sql(
    "SELECT p.id, p.userid, p.versionid, p.passed, p.requiredcount, p.timechecked, p.completedat $from ORDER BY p.userid",
    $params,
    $page * 25,
    25
);
$versions = $DB->get_records(store::VERSION, ['pathid' => $pathid], 'versionnum ASC', 'id,versionnum,name');
$rows = [];
foreach ($records as $record) {
    $person = \core_user::get_user($record->userid);
    if ($person) {
        $rows[] = [
            'name' => fullname($person), 'userid' => $record->userid,
            'version' => access::text('versionvalue', $versions[$record->versionid]->versionnum),
            'score' => access::text('score', (object)['done' => $record->passed, 'total' => $record->requiredcount]),
            'completed' => $record->completedat ? userdate($record->completedat) : '',
            'checked' => userdate($record->timechecked),
            'canmove' => $manager && $path->enabled && $path->activeversion && $path->activeversion != $record->versionid,
        ];
    }
}
$groups = [];
if (!access::limited($course)) {
    $groups[] = ['id' => 0, 'name' => access::text('allgroups'), 'selected' => !$group];
}
foreach (access::groups($course) as $choice) {
    $groups[] = ['id' => $choice->id, 'name' => $choice->name, 'selected' => $choice->id == $group];
}
$name = $versions[$path->activeversion]->name ?? $path->name;
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/report', ui::nav($courseid, $pathid) + [
    'name' => $name, 'url' => $url->out(false), 'groups' => $groups, 'sesskey' => sesskey(),
    'previewurl' => access::url('index', $courseid, ['pathid' => $pathid])->out(false),
    'exporturl' => $licensed ? (new moodle_url($url, ['export' => 1, 'sesskey' => sesskey()]))->out(false) : '',
    'rows' => $rows, 'registered' => $registered, 'completed' => $completed, 'inprogress' => $registered - $completed,
    'versionid' => $path->activeversion,
    'pager' => $OUTPUT->paging_bar($registered, $page, 25, $url),
]);
echo $OUTPUT->footer();
