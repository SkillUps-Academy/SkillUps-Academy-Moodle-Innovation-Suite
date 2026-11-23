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
 * Course configuration, rule editing, challenge catalog and reward delivery.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsgamification\access;
use local_skillupsgamification\admin;
use local_skillupsgamification\form\editor;
use local_skillupsgamification\goals;
use local_skillupsgamification\integration\learning_path;
use local_skillupsgamification\licensing\entitlements;
use local_skillupsgamification\store;
use local_skillupsgamification\ui;

$courseid = required_param('courseid', PARAM_INT);
[$course, $context] = access::course($courseid);
require_login($course);
require_capability(access::CAP . 'view', $context);
access::manager($courseid);
$section = optional_param('section', 'settings', PARAM_ALPHA);
$sections = ['settings', 'rules', 'goals', 'delivery', 'exclusions', 'audit'];
$section = in_array($section, $sections, true) ? $section : 'settings';
$base = access::url('manage', $courseid, ['section' => $section]);
$editing = optional_param('edit', false, PARAM_BOOL) && in_array($section, ['rules', 'goals'], true);
$id = max(0, optional_param('id', 0, PARAM_INT));
$url = $editing ? access::url('manage', $courseid, ['section' => $section, 'edit' => 1, 'id' => $id]) : $base;
ui::page($course, $url, access::text('manage'));
$form = null;
if (data_submitted() && optional_param('deliver', false, PARAM_BOOL) && $section === 'delivery') {
    require_sesskey();
    goals::fulfil($courseid, required_param('grantid', PARAM_INT));
    redirect($base, access::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if (in_array($section, ['settings', 'exclusions'], true) || $editing) {
    $form = new editor($url, ['section' => $section, 'courseid' => $courseid]);
    $table = $section === 'rules' ? store::RULE : store::GOAL;
    if ($section === 'settings') {
        $form->set_data(store::configuration($courseid));
    } else if ($editing && $id) {
        $form->set_data($DB->get_record($table, ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST));
    }
    if ($form->is_cancelled()) {
        redirect($base);
    }
    if ($input = $form->get_data()) {
        match ($section) {
            'settings' => admin::course($courseid, $input),
            'exclusions' => admin::exclude($courseid, (int)$input->userid, !empty($input->excluded)),
            'rules' => admin::rule($courseid, $id, $input),
            'goals' => admin::goal($courseid, $id, $input),
        };
        redirect($base, access::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
$data = [
    'tabs' => ui::tabs($courseid), 'courseid' => $courseid, 'sesskey' => sesskey(),
    'url' => $base->out(false), 'sections' => [],
];
foreach ($sections as $entry) {
    $data['sections'][] = [
        'label' => access::text($entry), 'active' => $entry === $section,
        'url' => access::url('manage', $courseid, ['section' => $entry])->out(false),
    ];
}
if (!$editing && in_array($section, ['rules', 'goals'], true)) {
    $rules = $section === 'rules';
    $data['catalog'] = true;
    $data['addurl'] = access::url('manage', $courseid, ['section' => $section, 'edit' => 1])->out(false);
    $data['records'] = [];
    foreach ($DB->get_records($rules ? store::RULE : store::GOAL, ['courseid' => $courseid], 'id ASC') as $entry) {
        $data['records'][] = [
            'title' => $entry->name, 'kind' => access::text(($rules ? 'source_' : 'goal_') . $entry->kind),
            'active' => access::text($entry->enabled ? 'enabled' : 'inactive'), 'window' => ui::window($entry),
            'url' => access::url('manage', $courseid, ['section' => $section, 'edit' => 1, 'id' => $entry->id])->out(false),
        ];
    }
}
if ($section === 'delivery') {
    $data['delivery'] = true;
    $data['redemptions'] = [];
    $page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
    $select = 'courseid = :course AND state IN (:pending, :fulfilled)';
    $params = ['course' => $courseid, 'pending' => 'pending', 'fulfilled' => 'fulfilled'];
    $records = $DB->get_records_select(store::GRANT, $select, $params, 'state DESC, id DESC', '*', $page * 25, 25);
    $catalog = $DB->get_records(store::GOAL, ['courseid' => $courseid, 'kind' => 'reward'], 'id', 'id,name');
    foreach ($records as $record) {
        $user = $record->userid > 0 ? \core_user::get_user($record->userid) : null;
        $data['redemptions'][] = [
            'id' => $record->id, 'title' => $catalog[$record->goalid]->name ?? access::text('archivedgoal'),
            'participant' => $user && !$user->deleted ? fullname($user) : access::text('anonymous'),
            'date' => userdate($record->timecreated), 'pending' => $record->state === 'pending',
            'state' => access::text('state_' . $record->state), 'cost' => $record->cost,
        ];
    }
    $data['pager'] = $OUTPUT->paging_bar($DB->count_records_select(store::GRANT, $select, $params), $page, 25, $base);
    if (in_array('aggregate_export', entitlements::status()['features'], true)) {
        $data['exporturl'] = access::url('export', $courseid, ['sesskey' => sesskey()])->out(false);
    }
} else if ($section === 'settings') {
    $data['pathnote'] = !(bool)learning_path::provider();
    if (has_capability('moodle/course:managegroups', $context)) {
        $data['groupsurl'] = (new moodle_url('/group/index.php', ['id' => $courseid]))->out(false);
    }
} else if ($section === 'exclusions') {
    $data['excludedusers'] = array_values(array_map(static fn($row): array => [
        'userid' => $row->userid, 'label' => access::text('usernumber', $row->userid),
    ], $DB->get_records(store::PLAYER, ['courseid' => $courseid, 'excluded' => 1], 'id DESC', '*', 0, 100)));
} else if ($section === 'audit') {
    $data['audit'] = true;
    $data['events'] = array_values(array_map(static fn($row): array => [
        'time' => userdate($row->timecreated), 'action' => access::text('audit_' . $row->action),
        'target' => $row->targetid, 'actor' => $row->userid ? access::text('usernumber', $row->userid) : access::text('anonymous'),
    ], $DB->get_records(store::AUDIT, ['courseid' => $courseid], 'id DESC', '*', 0, 50)));
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/manage', $data);
if ($form) {
    $form->display();
}
echo $OUTPUT->footer();
