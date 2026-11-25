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
 * Show an audience preview and require confirmation for editorial transitions.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupscommunication\access;
use local_skillupscommunication\audience;
use local_skillupscommunication\campaign;
use local_skillupscommunication\form\editor;
use local_skillupscommunication\store;
use local_skillupscommunication\ui;

require_login();
$id = required_param('id', PARAM_INT);
$op = required_param('op', PARAM_ALPHA);
$page = max(0, min(2000, optional_param('page', 0, PARAM_INT)));
$record = store::get(store::CAMP, $id);
$scope = access::scope((int)$record->scopeid);
$actions = [
    'submit' => ['draft'], 'approve' => ['review'], 'return' => ['review'],
    'cancel' => ['draft', 'review', 'queued', 'running'], 'retry' => ['complete'],
];
$author = access::can($scope, 'compose');
$reviewer = access::can($scope, 'approve');
if (
    !isset($actions[$op]) || ($op === 'submit' && !$author) ||
    ($op === 'cancel' && !$author && !$reviewer) ||
    (!in_array($op, ['submit', 'cancel'], true) && !$reviewer)
) {
    access::fail();
}
if (!in_array($record->state, $actions[$op], true)) {
    access::fail('stale');
}
ui::page('action', compact('id', 'op', 'page'), $scope);
$fields = ['revision' => ['hidden', PARAM_INT, false], 'confirmoperation' => ['advcheckbox', null, false]];
$defaults = ['revision' => $record->revision];
$ids = [];
if ($op === 'submit') {
    $ids = audience::ids($scope, campaign::filters($record), (int)$USER->id);
    $fields['fingerprint'] = ['hidden', PARAM_ALPHANUM, false];
    $defaults['fingerprint'] = audience::fingerprint($ids, (int)$record->revision);
}
$form = new editor(ui::url('action', compact('id', 'op', 'page')), ['fields' => $fields]);
$form->set_data($defaults);
$back = ui::url('view', ['id' => $id]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    if (empty($data->confirmoperation)) {
        access::fail('confirmrequired');
    }
    campaign::change($id, (int)$data->revision, $op, $data->fingerprint ?? '');
    redirect($back, ui::t('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::t($op === 'submit' ? 'preview' : $op));
echo ui::link($back, ui::t('back'));
echo $OUTPUT->heading(s($record->subject), 3);
if ($op === 'submit') {
    echo html_writer::tag('p', ui::t('targetcount', count($ids)));
    $rows = [];
    foreach (array_slice($ids, $page * 30, 30) as $userid) {
        $user = \core_user::get_user($userid);
        if ($user && !$user->deleted) {
            $rows[] = [s(fullname($user))];
        }
    }
    echo ui::table(['recipient'], $rows);
    echo $OUTPUT->paging_bar(count($ids), $page, 30, ui::url('action', compact('id', 'op')));
}
echo html_writer::tag('p', ui::t('confirmationhelp'));
if ($op !== 'submit' || $ids) {
    $form->display();
}
echo $OUTPUT->footer();
