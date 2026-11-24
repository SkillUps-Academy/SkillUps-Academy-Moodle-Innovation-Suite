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
 * Manage campus catalogs and inspect the notification queue.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsscheduler\catalog;
use local_skillupsscheduler\scope;
use local_skillupsscheduler\store;

require_login();
require_capability(scope::CAP . 'admin', context_system::instance());
$siteid = optional_param('siteid', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$revoke = optional_param('revoke', 0, PARAM_INT);
$showqueue = optional_param('queue', 0, PARAM_BOOL);
$selected = $siteid ? store::get(store::SITE, $siteid) : null;
$scopeurl = scope::url('catalog', $siteid ? ['siteid' => $siteid] : []);
scope::setup_page('catalog', scope::text('catalog'), array_filter(['siteid' => $siteid, 'type' => $type, 'id' => $id]));
if ($revoke && data_submitted()) {
    require_sesskey();
    $grant = store::get(store::GRANT, $revoke);
    if ($grant->siteid != $siteid) {
        scope::fail();
    }
    catalog::revoke($revoke);
    redirect($scopeurl, scope::text('saved'));
}
if ($type) {
    if (!isset(catalog::TYPES[$type]) || ($type !== 'site' && !$selected)) {
        scope::fail();
    }
    $record = $id ? store::get(catalog::TYPES[$type], $id) : null;
    if ($record && $type !== 'site' && $record->siteid != $siteid) {
        scope::fail();
    }
    $form = new \local_skillupsscheduler\form\catalog(
        scope::url('catalog', ['siteid' => $siteid, 'type' => $type, 'id' => $id]),
        [$type, $siteid, $id],
    );
    if ($record) {
        $defaults = (array)$record;
        if ($type === 'block') {
            $defaults['begin'] = catalog::time_label($record->beginmin);
            $defaults['finish'] = catalog::time_label($record->endmin);
        }
        $form->set_data($defaults);
    }
    if ($form->is_cancelled()) {
        redirect($scopeurl);
    }
    $error = '';
    if ($data = $form->get_data()) {
        require_sesskey();
        if ($data->type !== $type || (int)$data->siteid !== $siteid || (int)$data->id !== $id) {
            scope::fail();
        }
        try {
            $saved = catalog::save($type, $data);
            redirect(scope::url('catalog', ['siteid' => $type === 'site' ? $saved : $siteid]), scope::text('saved'));
        } catch (moodle_exception $failure) {
            if ($failure->module !== scope::C) {
                throw $failure;
            }
            $error = $failure->getMessage();
        }
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(scope::text('catalog_' . $type));
    if ($error) {
        echo $OUTPUT->notification(s($error), 'error');
    }
    $form->display();
    echo $OUTPUT->footer();
    exit;
}
$sections = [];
if ($selected) {
    foreach (array_diff_key(catalog::TYPES, ['site' => true]) as $kind => $table) {
        $entries = [];
        foreach ($DB->get_records($table, ['siteid' => $siteid], 'id DESC') as $row) {
            $account = $kind === 'grant' ? core_user::get_user($row->userid) : false;
            $details = match ($kind) {
                'cycle' => ($row->parentid ? scope::text('cycleid') : scope::text('rootperiod')) . ' · ' .
                    $row->startday . ' – ' . $row->endday,
                'room' => scope::text($row->kind . 'room') . ' · ' . scope::text('capacity') . ' ' . $row->capacity,
                'block' => $row->shiftname . ' · ' . catalog::time_label($row->beginmin) . '–' . catalog::time_label($row->endmin),
                default => $row->userid,
            };
            $entries[] = [
                'name' => $row->name ?? ($account ? fullname($account) : scope::text('unknown')), 'details' => $details,
                'edit' => $kind === 'grant' ? '' :
                    scope::url('catalog', ['type' => $kind, 'siteid' => $siteid, 'id' => $row->id])->out(false),
                'revoke' => $kind === 'grant' ? $row->id : 0,
            ];
        }
        $sections[] = [
            'name' => scope::text('catalog_' . $kind), 'rows' => $entries,
            'add' => scope::url('catalog', ['type' => $kind, 'siteid' => $siteid])->out(false),
        ];
    }
}
$sites = [];
foreach ($DB->get_records(store::SITE, [], 'name ASC') as $site) {
    $sites[] = ['name' => $site->name, 'url' => scope::url('catalog', ['siteid' => $site->id])->out(false)];
}
$jobs = [];
if ($showqueue) {
    foreach ($DB->get_records(store::QUEUE, [], 'attempts DESC, id ASC', '*', 0, 100) as $job) {
        $jobs[] = [
            'id' => $job->id, 'attempts' => $job->attempts, 'date' => userdate($job->nexttry),
            'link' => scope::url('session', ['id' => $job->slotid])->out(false),
            'exhausted' => $job->attempts >= 8,
        ];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(scope::C . '/catalog', [
    'back' => scope::url()->out(false), 'newsite' => scope::url('catalog', ['type' => 'site'])->out(false),
    'sites' => $sites, 'campus' => $selected ? $selected->name : '',
    'editsite' => $selected ? scope::url('catalog', ['siteid' => $siteid, 'type' => 'site', 'id' => $siteid])->out(false) : '',
    'sections' => $sections, 'siteid' => $siteid, 'sesskey' => sesskey(),
    'url' => scope::url('catalog')->out(false), 'showqueue' => $showqueue, 'jobs' => $jobs,
]);
echo $OUTPUT->footer();
