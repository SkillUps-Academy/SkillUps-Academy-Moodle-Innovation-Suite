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
 * SkillUps AI Copilot sources.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscopilot\access;
use local_skillupscopilot\form\document;
use local_skillupscopilot\sources;

$component = 'local_skillupscopilot';
$courseid = required_param('courseid', PARAM_INT);
if ($courseid <= 0 || $courseid === SITEID) {
    throw new moodle_exception('invalidcourse', $component);
}
require_login(get_course($courseid), false);
$context = access::require_chat($courseid);
require_capability('local/skillupscopilot:managesources', $context);
$url = new moodle_url('/local/skillupscopilot/sources.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('sources', $component));
$PAGE->set_heading($COURSE->fullname);

if ($remove = optional_param('remove', 0, PARAM_INT)) {
    $source = $DB->get_record('local_skillupscopilot_source', ['id' => $remove, 'courseid' => $courseid], '*', MUST_EXIST);
    if (data_submitted()) {
        require_sesskey();
        sources::remove($courseid, $remove);
        redirect($url, get_string('sourceremoved', $component), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmsourceremove', $component, s($source->title)),
        new moodle_url($url, ['remove' => $remove, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}

$modules = [0 => get_string('anyactivity', $component)];
$groups = [0 => get_string('anygroup', $component)];
foreach (get_fast_modinfo($COURSE)->get_cms() as $cm) {
    if ($cm->uservisible) {
        $modules[$cm->id] = format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]);
    }
}
foreach (groups_get_all_groups($courseid) as $group) {
    $groups[$group->id] = $group->name;
}
$form = new document($url, ['modules' => $modules, 'groups' => $groups]);
if (($data = $form->get_data()) && ($draft = $form->draft())) {
    try {
        sources::add($courseid, $USER, $data, $draft);
        redirect($url, get_string('sourceadded', $component), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $exception) {
        if ($exception->module !== $component) {
            throw $exception;
        }
        \core\notification::error(get_string($exception->errorcode, $component));
    }
}
$rows = [];
foreach ($DB->get_records('local_skillupscopilot_source', ['courseid' => $courseid], 'id DESC') as $source) {
    $rows[] = [
        'title' => $source->title, 'module' => $modules[$source->cmid] ?? get_string('unavailable', $component),
        'group' => $groups[$source->groupid] ?? get_string('unavailable', $component),
        'url' => (new moodle_url($url, ['remove' => $source->id]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template($component . '/sources', [
    'form' => $form->render(), 'sources' => $rows,
    'chaturl' => (new moodle_url('/local/skillupscopilot/index.php', ['courseid' => $courseid]))->out(false),
]);
echo $OUTPUT->footer();
