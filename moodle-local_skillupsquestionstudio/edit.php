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
 * SkillUps Question Studio edit.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\competencies;
use local_skillupsquestionstudio\form\questionform;
use local_skillupsquestionstudio\studio;
use local_skillupsquestionstudio\view;

$courseid = required_param('courseid', PARAM_INT);
require_login(get_course($courseid), false);
access::actor($USER->id, $courseid);
$context = access::context($courseid);
$id = required_param('id', PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$project = access::project($id, $courseid);
$params = ['courseid' => $courseid, 'id' => $id];
$url = view::url('edit', $params + ['itemid' => $itemid]);
view::page($url, $courseid, 'editor');
$item = $itemid ? studio::item($id, $itemid) : null;
$versionid = optional_param('revisionid', 0, PARAM_INT);
$previous = $item && $versionid && $versionid != $item->currentid;
$rev = $item ? studio::revision($itemid, $versionid ?: $item->currentid) : null;
$values = $rev ? json_decode($rev->contents, true, 10, JSON_THROW_ON_ERROR) : null;

if ($item && data_submitted() && ($decision = optional_param('decision', '', PARAM_ALPHA))) {
    require_sesskey();
    if (!in_array($decision, ['approve', 'reopen'], true)) {
        access::fail('accessdenied');
    }
    studio::review($project, $itemid, required_param('reviewid', PARAM_INT), $decision === 'approve', $USER->id);
    redirect($url, get_string($decision === 'approve' ? 'approved' : 'draft', access::C));
}
$form = $previous ? null : new questionform($url, competencies::options($courseid));
if ($form && $values) {
    $fields = questionform::fields($values);
    $fields['correct'] = ($values['correct'] ?? 0) + 1;
    $fields['base'] = $rev->id;
    $form->set_data($fields);
}
if ($form && $form->is_cancelled()) {
    redirect(view::url('project', $params));
}
if ($form && $data = $form->get_data()) {
    $saved = studio::save($project, $itemid, $data->base, questionform::model($data), $USER->id);
    redirect(view::url('edit', $params + ['itemid' => $saved]), get_string('saved', access::C));
}
$versions = [];
if ($item) {
    foreach ($DB->get_records(access::C . '_rev', ['itemid' => $itemid], 'seq DESC', 'id,seq,timecreated', 0, 30) as $row) {
        $versions[] = [
            'number' => $row->seq, 'date' => userdate($row->timecreated),
            'url' => (new moodle_url($url, ['revisionid' => $row->id]))->out(false),
        ];
    }
}
$near = $values ? studio::nearest($id, $values, $itemid) : 0;
$source = $item && $item->sourceid ? $DB->get_field(
    access::C . '_source',
    'state',
    ['id' => $item->sourceid, 'projectid' => $id]
) : false;
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/edit', [
    'name' => $project->name, 'back' => view::url('project', $params)->out(false),
    'form' => $form?->render(), 'url' => $url->out(false), 'revision' => $rev->id ?? 0,
    'sesskey' => sesskey(), 'preview' => $values ? view::question($values) : null, 'versions' => $versions,
    'approved' => $rev && $rev->id == $item->approved, 'old' => $previous,
    'canreview' => $rev && !$previous && has_capability('local/skillupsquestionstudio:review', $context),
    'duplicateurl' => $near ? view::url('edit', $params + ['itemid' => $near])->out(false) : null,
    'source' => $source === 'ready' ? view::url('project', $params + ['sourceid' => $item->sourceid])->out(false) : null,
]);
echo $OUTPUT->footer();
