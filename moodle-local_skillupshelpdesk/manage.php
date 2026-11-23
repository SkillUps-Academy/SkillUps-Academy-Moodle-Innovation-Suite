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
 * Configure support taxonomy, workflow, knowledge and templates.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\catalog;
use local_skillupshelpdesk\form\catalog_edit;
use local_skillupshelpdesk\storage;
use local_skillupshelpdesk\ui;

$kind = optional_param('kind', 'category', PARAM_ALPHA);
$id = max(0, optional_param('id', 0, PARAM_INT));
$editing = optional_param('edit', false, PARAM_BOOL);
$base = access::url('manage', ['kind' => $kind]);
$url = $editing ? access::url('manage', ['kind' => $kind, 'edit' => 1, 'id' => $id]) : $base;
require_login();
ui::page($url, access::text('manage'));
access::require(access::can('configure'));
$entries = catalog::records($kind, false);
$form = null;
if ($editing) {
    $item = $id ? catalog::entry($kind, $id, false) : null;
    $options = ['kind' => $kind];
    if ($kind === 'status' && $item) {
        $options['terminal'] = (int)$item->terminal;
    }
    $form = new catalog_edit($url, $options);
    if ($id) {
        $record = clone $item;
        $record->name = catalog::label($record);
        $form->set_data($record);
    }
    if ($form->is_cancelled()) {
        redirect($base);
    }
    if ($data = $form->get_data()) {
        catalog::save($kind, $id, $data);
        redirect($base, access::text('catalogsaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
$tabs = [];
foreach (catalog::KINDS as $type) {
    $tabs[] = [
        'label' => access::text('kind_' . $type),
        'url' => access::url('manage', ['kind' => $type])->out(false),
        'current' => $type === $kind,
    ];
}
$records = [];
foreach ($entries as $entry) {
    $info = [];
    if ($entry->initialstate) {
        $info[] = access::text('initialstate');
    }
    if ($entry->terminal) {
        $info[] = access::text('terminal');
    }
    if ($kind === 'priority') {
        $info[] = access::text('goals', (object)['response' => $entry->responsegoal, 'resolution' => $entry->resolutiongoal]);
    }
    $records[] = [
        'name' => catalog::label($entry),
        'active' => access::text($entry->active ? 'active' : 'inactive'),
        'info' => implode(' · ', $info),
        'url' => access::url('manage', ['kind' => $kind, 'id' => $entry->id, 'edit' => 1])->out(false),
    ];
}
$audit = [];
if (!$editing) {
    foreach ($DB->get_records(storage::AUDIT, ['ticketid' => 0], 'id DESC', '*', 0, 15) as $entry) {
        $audit[] = [
            'action' => access::text('action_' . $entry->action),
            'date' => ui::date((int)$entry->timecreated),
            'actor' => $entry->userid ? ui::person((int)$entry->userid) : access::text('systemactor'),
        ];
    }
}
$settings = is_siteadmin() ? new moodle_url('/admin/settings.php', ['section' => access::COMPONENT]) : null;
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::COMPONENT . '/manage', ui::nav() + [
    'records' => $records,
    'tabs' => $tabs,
    'editing' => $editing,
    'addurl' => access::url('manage', ['kind' => $kind, 'edit' => 1])->out(false),
    'settingsurl' => $settings ? $settings->out(false) : '',
    'audit' => $audit,
]);
if ($form) {
    echo $OUTPUT->heading(access::text($id ? 'editentry' : 'newentry'), 3);
    $form->display();
}
echo $OUTPUT->footer();
