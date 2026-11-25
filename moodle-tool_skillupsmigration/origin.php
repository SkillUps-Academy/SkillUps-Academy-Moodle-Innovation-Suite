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
 * Probe and inventory a registry-only Moodle origin.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_skillupsmigration\client;
use tool_skillupsmigration\inventory;
use tool_skillupsmigration\support;
use tool_skillupsmigration\ui;

require_login();
require_capability(support::CAP . 'manage', context_system::instance());
$origin = required_param('origin', PARAM_ALPHANUMEXT);
$after = optional_param('after', 0, PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);
$course = optional_param('course', 0, PARAM_INT);
$origins = client::choices();
if (!isset($origins[$origin])) {
    support::fail('endpoint');
}
$info = client::probe($origin);
$inventory = $course ? client::call($origin, 'inventory', ['course' => $course, 'options' => inventory::options([])]) : null;
$catalogue = $course ? null : client::call($origin, 'browse', ['after' => $after, 'category' => $category]);
ui::page('origin', compact('origin', 'after', 'course', 'category'));
echo $OUTPUT->header();
echo html_writer::start_div('skillups-migration');
echo $OUTPUT->heading(s($origins[$origin]));
echo ui::link(ui::url(), ui::t('back'));
echo html_writer::tag('p', ui::t('originready', s($info['release'])));
echo ui::link(ui::url('create', compact('origin', 'course')), ui::t('newjob'), 'btn btn-primary mb-3');
if ($inventory) {
    echo $OUTPUT->heading(s($inventory['name']), 3);
    echo html_writer::tag('p', s($inventory['category'] . ' · ' . $inventory['format']));
    echo $OUTPUT->heading(ui::t('sectionlist'), 4);
    $rows = [];
    foreach ($inventory['sections'] as $section) {
        $rows[] = [(int)$section['id'], s($section['name'])];
    }
    echo ui::table(['identifier', 'name'], $rows);
    echo $OUTPUT->heading(ui::t('activitylist'), 4);
    $rows = [];
    foreach ($inventory['modules'] as $activity) {
        $state = ui::t($activity['backup'] ? 'supported' : 'unsupported');
        $rows[] = [(int)$activity['id'], s($activity['name']), s($activity['type']), $state];
    }
    echo ui::table(['identifier', 'name', 'type', 'state'], $rows);
} else {
    $rows = [];
    foreach ($catalogue['rows'] as $row) {
        $rows[] = [$row['id'], ui::link(ui::url('origin', ['origin' => $origin, 'course' => $row['id']]), $row['name']),
            ui::link(ui::url('origin', ['origin' => $origin, 'category' => $row['categoryid']]), $row['category'])];
    }
    echo ui::table(['identifier', 'name', 'sourcecat'], $rows);
    if ($catalogue['next']) {
        $next = ui::url('origin', ['origin' => $origin, 'after' => $catalogue['next'], 'category' => $category]);
        echo ui::link($next, ui::t('next'));
    }
}
echo html_writer::end_div();
echo $OUTPUT->footer();
