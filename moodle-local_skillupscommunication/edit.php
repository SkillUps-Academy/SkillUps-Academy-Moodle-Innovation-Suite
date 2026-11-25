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
 * Create or revise an authorized draft communication.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupscommunication\access;
use local_skillupscommunication\campaign;
use local_skillupscommunication\form\editor;
use local_skillupscommunication\store;
use local_skillupscommunication\transport;
use local_skillupscommunication\ui;

require_login();
$id = optional_param('id', 0, PARAM_INT);
$templateid = optional_param('templateid', 0, PARAM_INT);
$record = $id ? store::get(store::CAMP, $id) : null;
$scopeid = $record ? (int)$record->scopeid : required_param('scopeid', PARAM_INT);
$scope = access::scope($scopeid);
if (!access::can($scope, 'compose') || ($record && $record->state !== 'draft')) {
    access::fail();
}
$template = $templateid ? store::get(store::TEMPLATE, $templateid) : null;
if ($template && $template->scopeid != $scopeid) {
    access::fail();
}
ui::page('edit', compact('id', 'scopeid', 'templateid'), $scope);
$fields = [
    'revision' => ['hidden', PARAM_INT, false],
    'subject' => ['text', PARAM_TEXT, true],
    'body' => ['textarea', PARAM_RAW_TRIMMED, true],
    'kind' => ['select', ui::options(campaign::KINDS), false],
    'channel' => ['select', transport::channels(), false],
    'sendat' => ['date_time_selector', ['optional' => true], false],
];
foreach (ui::selectors($scope) as $key => $choices) {
    $fields[$key] = ['autocomplete', $choices, false];
}
$form = new editor(ui::url('edit', compact('id', 'scopeid', 'templateid')), ['fields' => $fields]);
$defaults = $record ? (array)$record + campaign::filters($record) : ($template ? (array)$template : []);
$form->set_data($defaults);
$back = $id ? ui::url('view', ['id' => $id]) : ui::url('index', compact('scopeid'));
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    $id = campaign::save($scope, $id, $data);
    redirect(ui::url('view', compact('id')));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::t($id ? 'editcampaign' : 'newcampaign'));
echo ui::link($back, ui::t('back'));
echo html_writer::tag('p', ui::t('selectorhelp'));
$form->display();
echo $OUTPUT->footer();
