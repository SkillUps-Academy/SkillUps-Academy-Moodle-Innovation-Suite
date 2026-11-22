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
 * Academic support dashboard.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupssuccess\scope;
use local_skillupssuccess\store;
use local_skillupssuccess\ui;
use local_skillupssuccess\util;

$courseid = required_param('course', PARAM_INT);
require_login($courseid);
$course = scope::course($courseid);
$context = context_course::instance($courseid);
$policy = store::policy($courseid);
$page = max(0, min(4000, optional_param('page', 0, PARAM_INT)));
$state = optional_param('state', '', PARAM_ALPHA);
$risk = optional_param('risk', '', PARAM_ALPHA);
$states = ['' => util::str('allstates')];
foreach (['open', 'monitor', 'resolved'] as $key) {
    $states[$key] = util::str($key);
}
$risks = ['' => util::str('allrisks')];
foreach (['high', 'watch', 'normal', 'unknown'] as $key) {
    $risks[$key] = util::str($key);
}
if (!isset($states[$state], $risks[$risk])) {
    util::fail('invalidrequest');
}
$url = util::url('index', ['course' => $courseid, 'state' => $state, 'risk' => $risk]);
$manager = has_capability(util::CAP . ':manage', $context);
[$population, $params] = scope::population($course);
$cases = store::CASES;
$observations = store::OBS;
$where = "FROM {{$cases}} c JOIN ($population) enrolled ON enrolled.id = c.userid
    JOIN {{$observations}} o ON o.id = c.observationid WHERE c.courseid = :course";
$params += ['course' => $courseid];
if (!$manager) {
    $where .= ' AND c.tutorid = :tutor';
    $params['tutor'] = $USER->id;
}
if ($state !== '') {
    $where .= ' AND c.state = :casestate';
    $params['casestate'] = $state;
}
if ($risk !== '') {
    $where .= ' AND c.risk = :risk';
    $params['risk'] = $risk;
}
$total = $DB->count_records_sql("SELECT COUNT(1) $where", $params);
$page = min($page, max(0, (int)ceil($total / 25) - 1));
$records = $DB->get_records_sql("SELECT c.*, o.captured, o.modelalert $where ORDER BY c.id DESC", $params, $page * 25, 25);
util::audit($context, 'dashboard');
ui::header($context, $url, util::str('pluginname'));
$links = [];
foreach (['configure' => 'policy', 'assess' => 'assess', 'aggregate' => 'reports'] as $capability => $endpoint) {
    if (has_capability(util::CAP . ':' . $capability, $context)) {
        $links[] = ui::link($endpoint, ['course' => $courseid], util::str($endpoint));
    }
}
if ($links) {
    echo html_writer::div(implode(' ', $links), 'mb-3');
}
echo $OUTPUT->notification(util::str($policy->enabled ? 'reviewnotice' : 'scandisabled'), 'info');
ui::form('index', ['course' => $courseid], false);
ui::select('state', $states, $state);
ui::select('risk', $risks, $risk);
ui::submit('filter');
echo html_writer::end_tag('form');
$rows = [];
foreach ($records as $record) {
    $student = core_user::get_user($record->userid);
    $staff = $record->tutorid ? core_user::get_user($record->tutorid) : null;
    $rows[] = [
        ui::link('case', ['course' => $courseid, 'id' => $record->id], fullname($student)),
        s(util::str($record->risk)), s(util::str((int)$record->modelalert === 1 ? 'predictedalert' : 'nopath')),
        s(util::str($record->state)), $staff ? s(fullname($staff)) : s(util::str('unassigned')), s(userdate($record->captured)),
    ];
}
if ($rows) {
    echo ui::table('identifiedcases', ['learner', 'ruleclass', 'statistical', 'state', 'tutor', 'observed'], $rows);
    echo $OUTPUT->paging_bar($total, $page, 25, $url);
} else {
    echo $OUTPUT->notification(util::str('nocases'), 'info');
}
echo $OUTPUT->footer();
