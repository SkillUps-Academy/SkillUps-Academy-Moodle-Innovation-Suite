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
 * List authorized enrollment operations.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_skillupsenrollment\jobs;
use tool_skillupsenrollment\support;
use tool_skillupsenrollment\ui;

require_login();
ui::setup('index', 'pluginname');
$criteria = has_capability('tool/skillupsenrollment:manage', context_system::instance()) ? [] : ['userid' => $USER->id];
$total = $DB->count_records(jobs::JOB, $criteria);
$page = max(0, min(optional_param('page', 0, PARAM_INT), (int)ceil($total / 25) - 1));
ui::header();
echo $OUTPUT->render_from_template('tool_skillupsenrollment/start', [
    'single' => ui::url('create')->out(false), 'import' => ui::url('import')->out(false),
]);
$rows = [];
foreach ($DB->get_records(jobs::JOB, $criteria, 'id DESC', '*', 25 * $page, 25) as $job) {
    $counts = jobs::counts((int)$job->id);
    $rows[] = [
        html_writer::link(ui::url('job', ['id' => $job->id]), s(support::text('jobref', $job->id))),
        ui::state($job->state),
        $job->runat ? s(userdate($job->runat)) : s(support::text('immediate')),
        (int)($counts['applied'] ?? 0), (int)($counts['failed'] ?? 0) + (int)($counts['invalid'] ?? 0),
    ];
}
if ($rows) {
    ui::table('history', ['job', 'state', 'runat', 'state_applied', 'errors'], $rows);
    echo $OUTPUT->paging_bar($total, $page, 25, ui::url('index'));
} else {
    echo $OUTPUT->notification(support::text('nohistory'), 'info');
}
echo $OUTPUT->footer();
