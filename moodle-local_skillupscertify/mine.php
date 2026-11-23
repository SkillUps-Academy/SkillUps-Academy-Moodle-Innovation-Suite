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
 * List the authenticated user's historical certificates across courses.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscertify\access;
use local_skillupscertify\store;
use local_skillupscertify\ui;

require_login(null, false);
if (isguestuser()) {
    access::deny();
}
$url = access::url('mine');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(access::text('mine'));
$PAGE->set_heading(access::text('pluginname'));
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$criteria = ['userid' => $USER->id];
$count = $DB->count_records(store::ISSUE, $criteria);
$data = ['pager' => $OUTPUT->paging_bar($count, $page, 25, $url)];
foreach ($DB->get_records(store::ISSUE, $criteria, 'id DESC', '*', $page * 25, 25) as $row) {
    $data['rows'][] = ui::row($row);
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/mine', $data);
echo $OUTPUT->footer();
