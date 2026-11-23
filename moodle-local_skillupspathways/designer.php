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
 * Editing and publishing course itineraries.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupspathways\access;
use local_skillupspathways\form\designer;
use local_skillupspathways\store;
use local_skillupspathways\ui;

$courseid = required_param('courseid', PARAM_INT);
$pathid = optional_param('pathid', 0, PARAM_INT);
[$course] = access::course($courseid);
require_login($course);
access::manage($courseid);
$url = access::url('designer', $courseid, $pathid ? ['pathid' => $pathid] : []);
ui::page($course, $url, access::text('designer'));
$path = $pathid ? store::path($courseid, $pathid) : null;
$form = new designer($url);
if ($path) {
    $form->set_data([
        'name' => $path->name, 'description' => $path->description, 'enabled' => $path->enabled,
        'certificate' => $path->certificate, 'revision' => $path->revision, 'graph' => $path->draft,
    ]);
}
if ($form->is_cancelled()) {
    redirect(access::url('index', $courseid, $pathid ? ['pathid' => $pathid] : []));
}
$message = '';
if ($input = $form->get_data()) {
    $publish = !empty($input->publish);
    try {
        $saved = store::save($courseid, $pathid, (int)$input->revision, $input, $publish);
        redirect(
            access::url('designer', $courseid, ['pathid' => $saved]),
            access::text($publish ? 'published' : 'draftsaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $failure) {
        $message = $failure->getMessage();
    }
}

$versions = [];
if ($pathid) {
    $records = $DB->get_records(store::VERSION, ['pathid' => $pathid], 'versionnum DESC', 'id,versionnum,timecreated', 0, 30);
    foreach ($records as $version) {
        $versions[] = [
            'name' => access::text('versionvalue', $version->versionnum), 'date' => userdate($version->timecreated),
            'current' => $version->id == $path->activeversion,
        ];
    }
}

$PAGE->requires->js_call_amd(access::C . '/designer', 'init', ['skillups-pathways-designer', $courseid]);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/designer', ui::nav($courseid, $pathid) + [
    'versions' => $versions, 'hasversions' => (bool)$versions,
    'previewurl' => $pathid ? access::url('index', $courseid, ['pathid' => $pathid])->out(false) : '',
    'error' => $message,
]);
$form->display();
echo $OUTPUT->footer();
