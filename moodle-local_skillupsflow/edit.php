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
 * SkillUps Flow Automation edit.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsflow\definition;
use local_skillupsflow\form\workflow;
use local_skillupsflow\store;
use local_skillupsflow\ui;

require_login();
require_capability('local/skillupsflow:manage', context_system::instance());
$id = optional_param('id', 0, PARAM_INT);
ui::page('edit', 'editor', ['id' => $id]);
$flow = $id ? $DB->get_record('local_skillupsflow_flow', ['id' => $id], '*', MUST_EXIST) : null;
$version = $flow ? $DB->get_record('local_skillupsflow_ver', ['id' => $flow->latest], '*', MUST_EXIST) : null;
$graph = $version ? definition::parse($version->definition) : definition::starter();
$form = new workflow(ui::url('edit', ['id' => $id]));
$form->set_data(['name' => $flow->name ?? '', 'baseversion' => $version->id ?? 0, 'graph' => json_encode($graph)]);
if ($form->is_cancelled()) {
    redirect(ui::url());
}
if ($data = $form->get_data()) {
    $id = store::save($id, $data->baseversion, trim($data->name), definition::parse($data->graph), $USER->id);
    redirect(ui::url('edit', ['id' => $id]), get_string('saved', ui::C));
}
$strings = [];
$keys = [
    'name', 'label', 'entry', 'trigger', 'operator', 'minutes', 'start', 'userid', 'courseid', 'roleid', 'value',
    'body', 'tests', 'port_next', 'port_yes', 'port_no', 'port_error', 'emptyport', 'delete', 'up', 'down', 'left',
    'right', 'and', 'or', 'nothingselected', 'test_activeuser', 'test_visiblecourse', 'test_enrolled',
    'test_hasuser', 'test_hascourse', 'targethint', 'triggerhint', 'zero', 'one', 'addnode', 'limitnodes',
];
foreach (array_keys(definition::PORTS) as $type) {
    $keys[] = 'node_' . $type;
}
$triggers = array_merge(['manual', 'once', 'interval'], definition::EVENTS);
foreach ($triggers as $type) {
    $keys[] = 'trigger_' . $type;
}
foreach ($keys as $key) {
    $strings[$key] = get_string($key, ui::C);
}
$PAGE->requires->js_call_amd(ui::C . '/editor', 'init');
$versions = [];
if ($flow) {
    foreach ($DB->get_records('local_skillupsflow_ver', ['flowid' => $id], 'revision DESC', '*', 0, 50) as $item) {
        $versions[] = [
            'id' => $item->id, 'flowid' => $id, 'revision' => $item->revision, 'active' => $item->id == $flow->active,
            'date' => userdate($item->timecreated),
            'export' => ui::url('templates', ['versionid' => $item->id])->out(false),
        ];
    }
}
echo $OUTPUT->header();
echo ui::navigation('');
$config = json_encode(['ports' => definition::PORTS, 'triggerTypes' => $triggers, 'strings' => $strings], JSON_THROW_ON_ERROR);
echo $OUTPUT->render_from_template(ui::C . '/editor', [
    'form' => $form->render(), 'versions' => $versions, 'id' => $id,
    'saveurl' => ui::url()->out(false), 'sesskey' => sesskey(),
    'invalid' => $form->is_submitted(),
    'config' => $config,
]);
echo $OUTPUT->footer();
