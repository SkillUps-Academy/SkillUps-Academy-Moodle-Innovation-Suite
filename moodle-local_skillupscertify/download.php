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
 * Download the archived PDF for its authorized owner or course operator.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_skillupscertify\access;
use local_skillupscertify\issuer;
use local_skillupscertify\store;

require_login(null, false);
if (isguestuser()) {
    access::deny();
}
$id = required_param('id', PARAM_INT);
$issue = $DB->get_record(store::ISSUE, ['id' => $id]);
if (!$issue) {
    access::deny();
}
$context = context_course::instance($issue->courseid);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(access::url('download', ['id' => $id]));
if ($issue->userid != $USER->id) {
    $course = get_course($issue->courseid);
    if (!has_capability(access::CAP . 'viewregistry', $context) || !access::sees($course, $issue->userid)) {
        access::deny();
    }
    require_login($course, false);
}
send_stored_file(issuer::file($issue), 0, 0, true);
