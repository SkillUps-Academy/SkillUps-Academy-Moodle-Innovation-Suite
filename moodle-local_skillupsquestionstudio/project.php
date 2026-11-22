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
 * SkillUps Question Studio project.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\assessment;
use local_skillupsquestionstudio\competencies;
use local_skillupsquestionstudio\form\document;
use local_skillupsquestionstudio\form\generation;
use local_skillupsquestionstudio\generation\native;
use local_skillupsquestionstudio\question;
use local_skillupsquestionstudio\view;
use local_skillupsquestionstudio\work;

$courseid = required_param('courseid', PARAM_INT);
require_login(get_course($courseid), false);
access::actor($USER->id, $courseid);
$context = access::context($courseid);
$id = required_param('id', PARAM_INT);
$project = access::project($id, $courseid);
$scope = ['projectid' => $id];
$params = ['courseid' => $courseid, 'id' => $id];
$url = view::url('project', $params);
view::page($url, $courseid, 'project');

if (data_submitted() && optional_param('action', '', PARAM_ALPHA)) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'remove') {
        work::remove($project, required_param('sourceid', PARAM_INT));
        redirect($url, get_string('sourceremoved', access::C));
    }
    if ($action === 'variant') {
        $size = required_param('size', PARAM_INT);
        $variant = assessment::create($project, required_param('name', PARAM_TEXT), $size, $USER->id);
        redirect(view::url('assessment', $params + ['variantid' => $variant]));
    }
    if ($action === 'delete') {
        if (!is_siteadmin() && (int)$project->userid !== (int)$USER->id) {
            access::fail('accessdenied');
        }
        \local_skillupsquestionstudio\erasure::project($id, $courseid, true);
        redirect(view::url('index', ['courseid' => $courseid]), get_string('projectdeleted', access::C));
    }
}
$upload = new document($url);
if ($upload->get_data()) {
    $draft = $upload->draft();
    work::upload($project, $draft, $USER->id);
    $draft->delete();
    redirect($url, get_string('sourcequeued', access::C));
}
$ready = $DB->get_records_menu(access::C . '_source', $scope + ['state' => 'ready'], 'id DESC', 'id,name');
$gen = $ready ? new generation($url, [
    'sources' => $ready, 'competencies' => competencies::options($courseid), 'ai' => native::enabled($context, $USER->id),
]) : null;
if ($gen && ($data = $gen->get_data())) {
    work::request($project, (int)$data->sourceid, [
        'provider' => $data->provider, 'kind' => $data->kind, 'bloom' => $data->bloom, 'level' => $data->level,
        'unit' => $data->unit ?? '', 'count' => (int)$data->count, 'competency' => (int)$data->competency,
        'consent' => !empty($data->consent),
    ], $USER->id);
    redirect($url, get_string('generationqueued', access::C));
}
$selected = optional_param('sourceid', 0, PARAM_INT);
$preview = $selected ? $DB->get_record(
    access::C . '_source',
    $scope + ['id' => $selected, 'state' => 'ready'],
    'id,name,content',
    MUST_EXIST
) : null;
$sources = [];
foreach (
    $DB->get_records_select(
        access::C . '_source',
        'projectid = ? AND state <> ?',
        [$id, 'deleted'],
        'id DESC',
        'id,name,state,errorcode',
        0,
        30
    ) as $source
) {
    $sources[] = [
        'id' => $source->id, 'name' => $source->name ?: get_string('source', access::C),
        'state' => get_string('state_' . $source->state, access::C),
        'error' => $source->errorcode ? get_string($source->errorcode, access::C) : '',
        'preview' => $source->state === 'ready' ? view::url('project', $params + ['sourceid' => $source->id])->out(false) : null,
    ];
}
$jobs = array_values(array_map(static fn($job) => [
    'id' => $job->id, 'state' => get_string('state_' . $job->state, access::C),
    'count' => $job->resultcount, 'date' => userdate($job->timecreated),
], $DB->get_records(access::C . '_job', $scope, 'id DESC', '*', 0, 10)));
$variants = array_values(array_map(static fn($variant) => [
    'name' => $variant->name, 'date' => userdate($variant->timecreated),
    'href' => view::url('assessment', $params + ['variantid' => $variant->id])->out(false),
], $DB->get_records(access::C . '_variant', $scope, 'id DESC', 'id,name,timecreated')));
$kind = optional_param('kind', '', PARAM_ALPHANUM);
$unit = optional_param('unit', '', PARAM_TEXT);
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$sql = "SELECT i.*, r.contents FROM {local_skillupsquestionstudio_item} i
    JOIN {local_skillupsquestionstudio_rev} r ON r.id = i.currentid
    WHERE i.projectid = ? ORDER BY i.timemodified DESC, i.id DESC";
$rows = [];
$approved = 0;
$competencynames = competencies::options($courseid);
foreach ($DB->get_records_sql($sql, [$id]) as $row) {
    $data = json_decode($row->contents, true);
    $approved += $row->approved > 0 ? 1 : 0;
    $matchesunit = $unit === '' || \core_text::strtolower($unit) === \core_text::strtolower($data['unit']);
    if (($kind && $kind !== $row->kind) || !$matchesunit) {
        continue;
    }
    $rows[] = [
        'name' => $data['name'], 'type' => get_string('type_' . $row->kind, access::C),
        'approved' => (bool)$row->approved, 'origin' => get_string('provider_' . $row->origin, access::C),
        'unit' => $data['unit'], 'bloom' => get_string('bloom_' . $data['bloom'], access::C),
        'level' => get_string('level_' . $data['level'], access::C),
        'competency' => $competencynames[$data['competency']] ?? '—',
        'href' => view::url('edit', $params + ['itemid' => $row->id])->out(false),
    ];
}
$types = ['' => get_string('all', access::C)] + view::choices('type_', question::TYPES);
$options = [];
foreach ($types as $key => $label) {
    $options[] = ['value' => $key, 'label' => $label, 'selected' => $kind === $key];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/project', [
    'name' => $project->name, 'courseid' => $courseid, 'id' => $id, 'url' => $url->out(false), 'sesskey' => sesskey(),
    'back' => view::url('index', ['courseid' => $courseid])->out(false), 'edit' => view::url('edit', $params)->out(false),
    'upload' => $upload->render(), 'generation' => $gen?->render(), 'sources' => $sources, 'jobs' => $jobs, 'variants' => $variants,
    'preview' => $preview, 'kinds' => $options, 'unitfilter' => $unit, 'items' => array_slice($rows, $page * 25, 25),
    'approved' => $approved, 'canvariant' => $approved > 0,
    'candelete' => is_siteadmin() || (int)$project->userid === (int)$USER->id,
    'paging' => $OUTPUT->paging_bar(count($rows), $page, 25, view::url('project', $params + ['kind' => $kind, 'unit' => $unit])),
]);
echo $OUTPUT->footer();
