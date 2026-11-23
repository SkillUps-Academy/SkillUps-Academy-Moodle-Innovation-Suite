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
 * Show scoped batch progress and individual issuance outcomes.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscertify\access;
use local_skillupscertify\batches;
use local_skillupscertify\store;
use local_skillupscertify\ui;

$courseid = required_param('courseid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$course = access::course($courseid);
require_login($course, false);
$context = context_course::instance($courseid);
$batch = store::scoped(store::BATCH, $courseid, $id);
$registry = has_capability(access::CAP . 'viewregistry', $context);
$own = $USER->id == $batch->userid && has_capability(access::CAP . 'issue', $context);
if ((!$registry && !$own) || !batches::visible($course, $batch)) {
    access::deny();
}
$url = access::url('batch', ['courseid' => $courseid, 'id' => $id]);
ui::page($course, $url, access::text('batchheading'));
$data = ui::nav($course) + [
    'identifier' => access::text('batchnumber', $id), 'state' => access::text('batch_' . $batch->state),
    'created' => userdate($batch->timecreated), 'url' => $url->out(false), 'pending' => $batch->state === 'queued',
];
$counts = ['queued' => 0, 'issued' => 0, 'existing' => 0, 'skipped' => 0];
foreach ($DB->get_records(store::ITEM, ['batchid' => $id], 'id ASC') as $item) {
    if (!access::sees($course, $item->userid, (int)$batch->groupid)) {
        continue;
    }
    $counts[$item->state]++;
    $person = \core_user::get_user($item->userid);
    if (!$person || $person->deleted) {
        continue;
    }
    $data['rows'][] = [
        'recipient' => fullname($person), 'status' => access::text('item_' . $item->state),
        'url' => $registry && $item->issueid
            ? access::url('record', ['courseid' => $courseid, 'id' => $item->issueid])->out(false) : '',
    ];
}
foreach ($counts as $state => $count) {
    $data['metrics'][] = ['label' => access::text('item_' . $state), 'count' => $count];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/batch', $data);
echo $OUTPUT->footer();
