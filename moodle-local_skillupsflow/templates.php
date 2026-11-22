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
 * SkillUps Flow Automation templates.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsflow\definition;
use local_skillupsflow\form\import;
use local_skillupsflow\store;
use local_skillupsflow\ui;

require_login();
require_capability('local/skillupsflow:manage', context_system::instance());
$url = ui::url('templates');
ui::page('templates', 'templates');
$example = [
    'format' => 1, 'trigger' => ['type' => 'manual'], 'entry' => 'review',
    'nodes' => [
        ['id' => 'review', 'type' => 'approval', 'x' => 45, 'y' => 100, 'yes' => 'delay', 'no' => 'end'],
        ['id' => 'delay', 'type' => 'delay', 'x' => 335, 'y' => 45, 'minutes' => 5, 'next' => 'end'],
        ['id' => 'end', 'type' => 'stop', 'x' => 615, 'y' => 100],
    ],
];
$id = optional_param('versionid', 0, PARAM_INT);
if ($id || optional_param('sample', 0, PARAM_BOOL)) {
    $graph = $id ?
        definition::parse($DB->get_field('local_skillupsflow_ver', 'definition', ['id' => $id], MUST_EXIST)) : $example;
    // Export structure and safe defaults, with site-specific user and course IDs removed.
    foreach ($graph['nodes'] as &$node) {
        unset($node['userid'], $node['courseid']);
    }
    unset($node, $graph['trigger']['userid'], $graph['trigger']['courseid']);
    $body = json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="skillups-flow-template.json"');
    header('Cache-Control: private, no-store');
    echo $body;
    exit;
}
$form = new import($url);
if ($data = $form->get_data()) {
    $id = store::save(0, 0, trim($data->name), $form->graph(), $USER->id);
    redirect(ui::url('edit', ['id' => $id]), get_string('imported', ui::C));
}
echo $OUTPUT->header();
echo ui::navigation('templates');
echo $OUTPUT->render_from_template(ui::C . '/templates', [
    'download' => ui::url('templates', ['sample' => 1])->out(false), 'form' => $form->render(),
]);
echo $OUTPUT->footer();
