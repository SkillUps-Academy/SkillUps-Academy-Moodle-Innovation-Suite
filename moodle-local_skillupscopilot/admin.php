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
 * SkillUps AI Copilot admin.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupscopilot\ai\registry;
use local_skillupscopilot\event\operation_recorded;
use local_skillupscopilot\form\coursequota;
use local_skillupscopilot\licensing\entitlements;
use local_skillupscopilot\quota;

$component = 'local_skillupscopilot';
require_login();
$context = context_system::instance();
require_capability('local/skillupscopilot:manage', $context);
admin_externalpage_setup($component . '_dashboard');
$url = new moodle_url('/local/skillupscopilot/admin.php');
$license = entitlements::status();
$canexport = in_array('aggregate_export', $license['features'], true);
$courseid = max(0, optional_param('courseid', 0, PARAM_INT));
$userid = max(0, optional_param('userid', 0, PARAM_INT));
$filterurl = new moodle_url($url, ['courseid' => $courseid, 'userid' => $userid]);
$today = quota::day_start(time());
$filtercourse = $courseid ?: null;
$filteruser = $userid ?: null;
$daily = quota::totals($today, $filtercourse, $filteruser);
$monthly = quota::totals($today - 29 * DAYSECS, $filtercourse, $filteruser);

if (optional_param('export', 0, PARAM_BOOL) && data_submitted()) {
    require_sesskey();
    if (!$canexport || $userid) {
        throw new moodle_exception('accessdenied', $component);
    }
    operation_recorded::record($context, 'exported', $courseid);
    $fields = [
        'provider' => get_string('provider', $component), 'requests' => get_string('requests30', $component),
        'inputtokens' => get_string('inputtokens', $component), 'outputtokens' => get_string('outputtokens', $component),
        'failures' => get_string('failures', $component),
    ];
    \core\dataformat::download_data('skillupscopilot', 'csv', $fields, $monthly);
    exit;
}

$form = new coursequota($filterurl);
if ($data = $form->get_data()) {
    $factory = \core\lock\lock_config::get_lock_factory($component);
    $lock = $factory->get_lock('quota', 5);
    if (!$lock) {
        throw new moodle_exception('busy', $component);
    }
    try {
        $scope = ['courseid' => $data->quota_courseid];
        $record = $DB->get_record('local_skillupscopilot_course', $scope);
        if ($data->requestlimit < 0) {
            $DB->delete_records('local_skillupscopilot_course', $scope);
        } else if ($record) {
            $DB->set_field('local_skillupscopilot_course', 'requestlimit', $data->requestlimit, $scope);
        } else {
            $DB->insert_record('local_skillupscopilot_course', (object)($scope + ['requestlimit' => $data->requestlimit]));
        }
        operation_recorded::record($context, 'quotachanged', $data->quota_courseid);
    } finally {
        $lock->release();
    }
    redirect($filterurl, get_string('quotasaved', $component), null, \core\output\notification::NOTIFY_SUCCESS);
}
$rows = [];
foreach (registry::IDS as $id) {
    $config = registry::config($id);
    $last = $DB->get_records_select(
        'local_skillupscopilot_meter',
        'provider = :provider AND status IN (:complete, :failed)',
        ['provider' => $id, 'complete' => 'complete', 'failed' => 'failed'],
        'timemodified DESC, id DESC',
        'id, status, timemodified',
        0,
        1
    );
    $last = reset($last);
    $rows[] = [
        'name' => get_string($id, $component), 'model' => $config['model'] ?? '',
        'availability' => get_string($config ? 'ready' : 'unavailable', $component),
        'last' => $last ? get_string('meter_' . $last->status, $component, userdate($last->timemodified)) : '—',
        'today' => $daily[$id]->requests ?? 0, 'requests' => $monthly[$id]->requests ?? 0,
        'input' => $monthly[$id]->inputtokens ?? 0, 'output' => $monthly[$id]->outputtokens ?? 0,
        'failures' => $monthly[$id]->failures ?? 0,
    ];
}
$overridelimit = $courseid ? $DB->get_field('local_skillupscopilot_course', 'requestlimit', ['courseid' => $courseid]) : false;
echo $OUTPUT->header();
echo $OUTPUT->render_from_template($component . '/admin', [
    'rows' => $rows, 'form' => $form->render(), 'courseid' => $courseid ?: '', 'userid' => $userid ?: '',
    'filterurl' => $url->out(false), 'exporturl' => $canexport && !$userid ? $filterurl->out(false) : null,
    'sesskey' => sesskey(), 'overridelimit' => $overridelimit === false ? '' : (string)$overridelimit,
    'overrideactive' => $overridelimit !== false,
    'edition' => get_string('edition_' . $license['state'], $component),
    'expiry' => $license['expires'] ? userdate($license['expires']) : '',
    'siteidentity' => entitlements::site_id(),
    'settingsurl' => (new moodle_url('/admin/settings.php', ['section' => $component . '_settings']))->out(false),
    'chaturl' => has_capability('local/skillupscopilot:sitechat', $context) ?
        (new moodle_url('/local/skillupscopilot/index.php'))->out(false) : null,
]);
echo $OUTPUT->footer();
