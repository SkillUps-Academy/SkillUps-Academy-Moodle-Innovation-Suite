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
 * List institutionally scoped batches and authorized origins.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_skillupsmigration\client;
use tool_skillupsmigration\support;
use tool_skillupsmigration\ui;

require_login();
require_capability(support::CAP . 'manage', context_system::instance());
$categories = ui::categories();
$origins = client::choices();
$page = max(0, min(10000, optional_param('page', 0, PARAM_INT)));
ui::page('index', compact('page'));
echo $OUTPUT->header();
echo html_writer::start_div('skillups-migration');
echo $OUTPUT->heading(ui::t('pluginname'), 2);
echo html_writer::start_div('sm-actions');
if ($origins && $categories) {
    echo ui::link(ui::url('create'), ui::t('newjob'), 'btn btn-primary');
}
foreach ($origins as $id => $label) {
    echo ui::link(ui::url('origin', ['origin' => $id]), ui::t('browseorigin', $label), 'btn btn-outline-secondary');
}
echo html_writer::end_div();
if (!$origins) {
    echo html_writer::div(ui::t('registryempty'), 'alert alert-info');
}
echo $OUTPUT->heading(ui::t('history'), 3);
$rows = [];
if ($categories) {
    [$where, $params] = $DB->get_in_or_equal(array_keys($categories), SQL_PARAMS_NAMED);
    $condition = "categoryid $where";
    foreach ($DB->get_records_select(support::JOB, $condition, $params, 'updated DESC, id DESC', '*', $page * 25, 25) as $job) {
        $rows[] = [
            ui::link(ui::url('job', ['id' => $job->id]), ui::t('jobnumber', $job->id)),
            s($origins[$job->endpoint] ?? $job->endpoint), s($categories[$job->categoryid]), ui::t($job->state),
            s(userdate($job->updated)),
        ];
    }
    echo ui::table(['job', 'origin', 'categoryid', 'state', 'updated'], $rows);
    echo $OUTPUT->paging_bar($DB->count_records_select(support::JOB, $condition, $params), $page, 25, ui::url());
} else {
    echo html_writer::tag('p', ui::t('empty'));
}
echo html_writer::end_div();
echo $OUTPUT->footer();
