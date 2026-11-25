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
 * Submit, review, archive or change resource audience using Moodle Forms.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsrepository\access;
use local_skillupsrepository\form\fields;
use local_skillupsrepository\library;
use local_skillupsrepository\store;
use local_skillupsrepository\ui;

require_login();
$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$asset = store::get(store::ASSET, $id);
$categoryid = (int)$asset->categoryid;
$moderator = access::can('moderate', $categoryid);
if (
    !in_array($action, ['submit', 'approve', 'reject', 'archive', 'restore', 'audience'], true) ||
    (!$moderator && ($action !== 'submit' || !access::internal($asset) || !access::can('contribute', $categoryid)))
) {
    access::fail();
}
ui::page('action', compact('id', 'action'), context_coursecat::instance($categoryid));
$definitions = ['generation' => ['hidden', PARAM_INT, false]];
if (in_array($action, ['approve', 'reject'], true)) {
    $definitions['reviewnote'] = ['textarea', PARAM_RAW_TRIMMED, $action === 'reject'];
}
if ($action === 'audience') {
    $audiences = access::public_enabled() ? library::AUDIENCES : ['private', 'institution'];
    $definitions['audience'] = ['select', ui::options($audiences), false];
}
$form = new fields(ui::url('action', compact('id', 'action')), ['fields' => $definitions]);
$form->set_data(['generation' => $asset->generation, 'audience' => $asset->audience]);
$back = ui::url('view', ['id' => $id]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    library::change($id, (int)$data->generation, $action, $data->reviewnote ?? $data->audience ?? '');
    redirect($back, ui::t('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::t($action));
echo html_writer::tag('p', ui::t('confirmaction'));
$form->display();
echo $OUTPUT->footer();
