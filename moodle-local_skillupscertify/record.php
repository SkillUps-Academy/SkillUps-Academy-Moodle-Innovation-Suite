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
 * Show scoped issuance history and explicit revocation actions.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscertify\access;
use local_skillupscertify\issuer;
use local_skillupscertify\store;
use local_skillupscertify\ui;

$courseid = required_param('courseid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$course = access::course($courseid);
require_login($course, false);
$context = context_course::instance($courseid);
require_capability(access::CAP . 'viewregistry', $context);
$row = store::scoped(store::ISSUE, $courseid, $id);
if (!access::sees($course, $row->userid)) {
    access::deny();
}
$url = access::url('record', ['courseid' => $courseid, 'id' => $id]);
ui::page($course, $url, access::text('record'));
$data = ui::nav($course) + ui::row($row) + [
    'url' => $url->out(false), 'courseid' => $courseid, 'sesskey' => sesskey(),
    'fingerprint' => $row->pdfhash, 'reason' => $row->reason,
    'revokedate' => $row->timerevoked ? userdate($row->timerevoked) : '',
];
$criteria = ['courseid' => $courseid, 'templateid' => $row->templateid, 'userid' => $row->userid];
$latestrecords = $DB->get_records(store::ISSUE, $criteria, 'id DESC', 'id', 0, 1);
$latest = reset($latestrecords);
$data['canrevoke'] = has_capability(access::CAP . 'revoke', $context);
$data['canreissue'] = $data['canrevoke'] && has_capability(access::CAP . 'issue', $context) && $latest->id == $id;
if (data_submitted()) {
    require_sesskey();
    try {
        if (!required_param('confirm', PARAM_BOOL)) {
            access::deny();
        }
        $action = required_param('operation', PARAM_ALPHA);
        if ($action === 'revoke') {
            issuer::revoke($course, $id, required_param('reason', PARAM_TEXT));
            redirect($url, access::text('revokedmessage'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        if ($action !== 'reissue') {
            access::deny();
        }
        $result = issuer::issue($course, $row->templateid, $row->userid, 0, 0, $id);
        if (!$result) {
            throw new moodle_exception('ineligible', access::C);
        }
        redirect(
            access::url('record', ['courseid' => $courseid, 'id' => $result[0]->id]),
            access::text('reissued'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $failure) {
        $data['error'] = $failure->getMessage();
    }
}
$data['history'] = [];
foreach ($DB->get_records(store::ISSUE, $criteria, 'id DESC', '*', 0, 50) as $past) {
    $data['history'][] = ui::row($past) + [
        'version' => access::text('edition', store::revision((object)['id' => $past->templateid], $past->revisionid)->revision),
        'recordurl' => access::url('record', ['courseid' => $courseid, 'id' => $past->id])->out(false),
    ];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/record', $data);
echo $OUTPUT->footer();
