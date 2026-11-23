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
 * Submit individual and group certificate issuance requests.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscertify\access;
use local_skillupscertify\batches;
use local_skillupscertify\issuer;
use local_skillupscertify\store;
use local_skillupscertify\ui;

$courseid = required_param('courseid', PARAM_INT);
$course = access::course($courseid);
require_login($course, false);
$context = context_course::instance($courseid);
require_capability(access::CAP . 'issue', $context);
$url = access::url('issue', ['courseid' => $courseid]);
ui::page($course, $url, access::text('issueheading'));
$data = ui::nav($course) + ['url' => $url->out(false), 'courseid' => $courseid, 'sesskey' => sesskey()];
if (data_submitted()) {
    require_sesskey();
    $templateid = required_param('templateid', PARAM_INT);
    $op = required_param('operation', PARAM_ALPHA);
    try {
        if (!required_param('confirm', PARAM_BOOL)) {
            access::deny();
        }
        if ($op === 'batch') {
            $id = batches::queue($course, $templateid, required_param('groupid', PARAM_INT));
            redirect(
                access::url('batch', ['courseid' => $courseid, 'id' => $id]),
                access::text('queued'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        if ($op !== 'single') {
            access::deny();
        }
        $username = \core_text::strtolower(required_param('username', PARAM_RAW_TRIMMED));
        $userid = \core_text::strlen($username) <= 100
            ? $DB->get_field('user', 'id', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])
            : false;
        if (!$userid || !access::participant($course, $userid) || !access::sees($course, $userid)) {
            access::deny();
        }
        $result = issuer::issue($course, $templateid, $userid);
        if (!$result) {
            throw new moodle_exception('ineligible', access::C);
        }
        $target = access::url('record', ['courseid' => $courseid, 'id' => $result[0]->id]);
        if (!has_capability(access::CAP . 'viewregistry', $context)) {
            $target = $url;
        }
        redirect($target, access::text($result[1] ? 'issued' : 'alreadyissued'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $failure) {
        $data['error'] = $failure->getMessage();
    }
}
$templates = $DB->get_records(store::TEMPLATE, ['courseid' => $courseid, 'enabled' => 1], 'label ASC', 'id,label');
$data['choices'] = array_values($templates);
$data['canbatch'] = !access::limited($course) || (bool)access::groups($course);
$data['groups'] = access::limited($course) ? [] : [['id' => 0, 'name' => access::text('allgroups')]];
foreach (access::groups($course) as $group) {
    $data['groups'][] = ['id' => $group->id, 'name' => $group->name];
}
$data['haschoices'] = (bool)$data['choices'];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/issue', $data);
echo $OUTPUT->footer();
