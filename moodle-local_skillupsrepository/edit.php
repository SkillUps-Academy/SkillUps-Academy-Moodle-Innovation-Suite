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
 * Upload a resource or create its next immutable revision.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/repository/lib.php');

use local_skillupsrepository\access;
use local_skillupsrepository\catalog;
use local_skillupsrepository\files;
use local_skillupsrepository\form\fields;
use local_skillupsrepository\library;
use local_skillupsrepository\store;
use local_skillupsrepository\ui;

require_login();
$id = optional_param('id', 0, PARAM_INT);
$asset = $id ? store::get(store::ASSET, $id) : null;
$categoryid = $asset ? (int)$asset->categoryid : optional_param('categoryid', 0, PARAM_INT);
$categories = catalog::categories('contribute') + catalog::categories('moderate');
if (!$categories) {
    access::fail();
}
if (!$categoryid) {
    ui::page('edit');
    echo $OUTPUT->header();
    echo $OUTPUT->heading(ui::t('categoryid'));
    echo html_writer::start_tag('ul');
    foreach ($categories as $cid => $label) {
        echo html_writer::tag('li', html_writer::link(ui::url('edit', ['categoryid' => $cid]), s($label)));
    }
    echo html_writer::end_tag('ul');
    echo $OUTPUT->footer();
    exit;
}
if (!isset($categories[$categoryid]) || ($asset && (!access::internal($asset) || $asset->archived))) {
    access::fail();
}
$revision = $asset ? store::get(store::REVISION, (int)$asset->currentid) : null;
if ($revision && $revision->state === 'pending') {
    access::fail('stale');
}
ui::page('edit', compact('id', 'categoryid'), context_coursecat::instance($categoryid));
$formfields = [
    'generation' => ['hidden', PARAM_INT, false],
    'title' => ['text', PARAM_TEXT, true],
    'description' => ['textarea', PARAM_RAW_TRIMMED, false],
    'collection' => ['text', PARAM_TEXT, false],
    'area' => ['text', PARAM_TEXT, false],
    'program' => ['text', PARAM_TEXT, false],
    'level' => ['text', PARAM_TEXT, false],
    'credit' => ['text', PARAM_TEXT, false],
    'tags' => ['text', PARAM_TEXT, false],
    'keywords' => ['text', PARAM_TEXT, false],
    'license' => ['select', ui::options(array_keys(library::LICENSES), 'license_'), false],
    'sourceurl' => ['text', PARAM_RAW_TRIMMED, false],
    'payload' => ['filemanager', [
        'maxbytes' => files::limit(), 'maxfiles' => 1, 'subdirs' => 0, 'return_types' => FILE_INTERNAL,
        'accepted_types' => array_map(static fn($ext) => '.' . $ext, array_keys(files::TYPES)),
    ], false],
    'allowdownload' => ['advcheckbox', null, false],
    'allowcopy' => ['advcheckbox', null, false],
];
if (!$asset) {
    $audiences = access::public_enabled() ? library::AUDIENCES : ['private', 'institution'];
    $formfields['audience'] = ['select', ui::options($audiences), false];
}
$form = new fields(ui::url('edit', compact('id', 'categoryid')), ['fields' => $formfields]);
$defaults = $revision ? (array)$revision : ['audience' => 'private', 'allowdownload' => 1];
$defaults['generation'] = $asset->generation ?? 0;
$form->set_data($defaults);
$back = $id ? ui::url('view', ['id' => $id]) : ui::url();
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    $assetid = library::save($categoryid, $id, $data);
    redirect(ui::url('view', ['id' => $assetid]), ui::t('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::t($id ? 'newrevision' : 'newresource'));
echo html_writer::link($back, ui::t('back'));
echo html_writer::tag('p', ui::t($revision ? 'uploadrevisionhelp' : 'uploadhelp'));
$form->display();
echo $OUTPUT->footer();
