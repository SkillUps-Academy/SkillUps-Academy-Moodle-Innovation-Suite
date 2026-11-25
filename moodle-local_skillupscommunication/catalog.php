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
 * Configure templates and named institutional audience selectors.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupscommunication\access;
use local_skillupscommunication\campaign;
use local_skillupscommunication\catalog;
use local_skillupscommunication\form\editor;
use local_skillupscommunication\store;
use local_skillupscommunication\ui;

require_login();
$scopeid = required_param('scopeid', PARAM_INT);
$type = optional_param('type', 'template', PARAM_ALPHA);
$editing = optional_param('editing', 0, PARAM_BOOL);
$id = optional_param('id', 0, PARAM_INT);
$scope = access::scope($scopeid);
$config = access::can($scope, 'configure');
$compose = access::can($scope, 'compose');
if (
    !in_array($type, ['template', 'segment'], true) || (!$config && (!$compose || $type !== 'template')) ||
    ($editing && !$config)
) {
    access::fail();
}
$table = $type === 'template' ? store::TEMPLATE : store::SEG;
$item = $id && $editing ? store::get($table, $id) : null;
if ($item && $item->scopeid != $scopeid) {
    access::fail();
}
$base = ui::url('catalog', compact('scopeid', 'type'));
ui::page('catalog', compact('scopeid', 'type', 'editing', 'id'), $scope);
$form = null;
if ($editing) {
    $fields = ['name' => ['text', PARAM_TEXT, true]];
    $defaults = $item ? (array)$item : [];
    if ($type === 'template') {
        $fields += [
            'subject' => ['text', PARAM_TEXT, true], 'body' => ['textarea', PARAM_RAW_TRIMMED, true],
            'kind' => ['select', ui::options(campaign::KINDS), false],
        ];
    } else {
        $categories = [0 => ui::t('any')];
        foreach (access::scopes('configure') as $cid => $name) {
            $context = access::scope($cid);
            if ($context->contextlevel === CONTEXT_COURSECAT && access::contains($scope, $context)) {
                $categories[$cid] = $name;
            }
        }
        if (!$item) {
            $fields['segmentkind'] = ['select', ui::options(['program', 'campus', 'set']), false];
        }
        if (!$item || $item->kind !== 'set') {
            $fields['targetcontext'] = ['select', $categories, false];
        }
        if (!$item || $item->kind === 'set') {
            $fields['usernames'] = ['textarea', PARAM_RAW_TRIMMED, false];
        }
        if ($item && $item->kind === 'set') {
            $users = $DB->get_fieldset_sql(
                'SELECT u.username FROM {user} u JOIN {' . store::MEMBER . '} m ON m.userid = u.id
                  WHERE m.segmentid = :id AND u.deleted = 0 ORDER BY u.username',
                ['id' => $id]
            );
            $defaults['usernames'] = implode("\n", $users);
        }
    }
    $form = new editor(ui::url('catalog', compact('scopeid', 'type', 'editing', 'id')), ['fields' => $fields]);
    $form->set_data($defaults);
    if ($form->is_cancelled()) {
        redirect($base);
    }
    if ($data = $form->get_data()) {
        catalog::save($scope, $type, $id, $data);
        redirect($base);
    }
}
echo $OUTPUT->header();
echo html_writer::start_div('skillups-comm');
echo $OUTPUT->heading(ui::t($type === 'template' ? 'templates' : 'segments'));
echo ui::link(ui::url('index', compact('scopeid')), ui::t('back'));
echo html_writer::start_div('sc-actions');
echo ui::link(ui::url('catalog', ['scopeid' => $scopeid, 'type' => 'template']), ui::t('templates'), 'btn btn-outline-secondary');
if ($config) {
    echo ui::link(ui::url('catalog', ['scopeid' => $scopeid, 'type' => 'segment']), ui::t('segments'), 'btn btn-outline-secondary');
    echo ui::link(ui::url('catalog', compact('scopeid', 'type') + ['editing' => 1]), ui::t('newentry'), 'btn btn-primary');
}
echo html_writer::end_div();
if ($form) {
    if ($type === 'segment') {
        echo html_writer::tag('p', ui::t('segmenthelp'));
    }
    $form->display();
} else {
    $rows = [];
    foreach ($DB->get_records($table, ['scopeid' => $scopeid], 'name ASC') as $entry) {
        $buttons = '';
        if ($config) {
            $edit = ui::url('catalog', compact('scopeid', 'type') + ['editing' => 1, 'id' => $entry->id]);
            $buttons .= ui::link($edit, ui::t('edit'));
        }
        if ($type === 'template' && $compose) {
            $buttons .= ' ' . ui::link(ui::url('edit', ['scopeid' => $scopeid, 'templateid' => $entry->id]), ui::t('usetemplate'));
        }
        $rows[] = [s($entry->name), ui::t($entry->kind), $buttons];
    }
    echo ui::table(['name', 'kind', 'operation'], $rows);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
