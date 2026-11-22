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
 * SkillUps Question Studio assessment.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsquestionstudio\access;
use local_skillupsquestionstudio\assessment;
use local_skillupsquestionstudio\bank\publisher;
use local_skillupsquestionstudio\bank\xml;
use local_skillupsquestionstudio\view;

$courseid = required_param('courseid', PARAM_INT);
require_login(get_course($courseid), false);
access::actor($USER->id, $courseid);
$context = access::context($courseid);
$id = required_param('id', PARAM_INT);
$project = access::project($id, $courseid);
$variantid = required_param('variantid', PARAM_INT);
$variant = assessment::find($id, $variantid);
$params = ['courseid' => $courseid, 'id' => $id, 'variantid' => $variantid];
$url = view::url('assessment', $params);
view::page($url, $courseid, 'assessment');
$publish = has_capability('local/skillupsquestionstudio:publish', $context);
$key = optional_param('key', 0, PARAM_BOOL);
if (data_submitted()) {
    require_sesskey();
    $count = publisher::publish($project, $variantid, required_param('categoryid', PARAM_INT), $USER->id);
    redirect($url, get_string('publishedcount', access::C, $count));
}
$stale = false;
$records = [];
$lock = access::lock($project->id);
try {
    try {
        $records = assessment::questions($project, $variant);
    } catch (\moodle_exception $exception) {
        if (!in_array($exception->errorcode, ['stalevariant', 'invalidcompetency', 'invalidrecord'], true)) {
            throw $exception;
        }
        $stale = true;
    }
    if (optional_param('export', 0, PARAM_BOOL)) {
        require_capability('local/skillupsquestionstudio:publish', $context);
        if ($stale) {
            access::fail('stalevariant');
        }
        $body = xml::generate($records);
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="skillups-question-studio.xml"');
        header('Cache-Control: private, no-store');
        echo $body;
        exit;
    }
} finally {
    $lock->release();
}
$categories = [];
if ($publish && !$stale) {
    foreach (publisher::options($courseid) as $categoryid => $label) {
        $categories[] = ['id' => $categoryid, 'label' => $label];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/assessment', [
    'name' => $variant->name, 'back' => view::url('project', ['courseid' => $courseid, 'id' => $id])->out(false),
    'records' => array_values(array_map(static fn($data) => view::question($data, (bool)$key), $records)),
    'export' => $publish && !$stale ? view::url('assessment', $params + ['export' => 1])->out(false) : null,
    'categories' => $categories, 'canimport' => (bool)$categories, 'publish' => $publish && !$stale,
    'url' => $url->out(false), 'stale' => $stale, 'sesskey' => sesskey(), 'key' => $key,
    'keyurl' => view::url('assessment', $params + ['key' => $key ? 0 : 1])->out(false),
]);
echo $OUTPUT->footer();
