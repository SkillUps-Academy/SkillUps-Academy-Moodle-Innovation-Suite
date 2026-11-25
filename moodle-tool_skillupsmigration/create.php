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
 * Collect explicit batch and cross-instance mapping choices.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_skillupsmigration\client;
use tool_skillupsmigration\form\fields;
use tool_skillupsmigration\jobs;
use tool_skillupsmigration\support;
use tool_skillupsmigration\ui;

require_login();
require_capability(support::CAP . 'manage', context_system::instance());
$origins = client::choices();
$categories = ui::categories();
if (!$origins || !$categories) {
    support::fail('unavailable');
}
$origin = optional_param('origin', '', PARAM_ALPHANUMEXT);
$course = optional_param('course', 0, PARAM_INT);
ui::page('create', compact('origin', 'course'));
$form = new fields(ui::url('create', compact('origin', 'course')), ['fields' => [
    'origin' => ['select', $origins, false], 'categoryid' => ['select', $categories, false],
    'courses' => ['textarea', PARAM_RAW_TRIMMED, true], 'sections' => ['text', PARAM_TEXT, false],
    'activities' => ['text', PARAM_TEXT, false], 'files' => ['advcheckbox', null, false],
    'groups' => ['advcheckbox', null, false], 'dates' => ['advcheckbox', null, false],
    'configuration' => ['advcheckbox', null, false], 'staff' => ['textarea', PARAM_RAW_TRIMMED, false],
]]);
$form->set_data(['origin' => $origin, 'courses' => $course ? "$course,migrated-$course" : '', 'files' => 1, 'dates' => 1]);
if ($form->is_cancelled()) {
    redirect(ui::url());
}
if ($data = $form->get_data()) {
    $rows = [];
    foreach (ui::lines($data->courses, 2) as [$source, $short]) {
        if (!ctype_digit($source) || isset($rows[(int)$source])) {
            support::fail('selection');
        }
        $rows[(int)$source] = $short;
    }
    $options = (array)$data;
    foreach (['sections', 'activities'] as $name) {
        $raw = trim($options[$name] ?? '');
        if ($raw && !preg_match('/^\d+(?:\s*,\s*\d+)*$/D', $raw)) {
            support::fail('selection');
        }
        $options[$name] = $raw ? array_map('intval', explode(',', $raw)) : [];
    }
    $options['staff'] = array_map(
        static fn($row) => array_combine(['sourceuser', 'targetuser', 'sourcerole', 'targetrole'], $row),
        ui::lines($data->staff ?? '', 4)
    );
    $id = jobs::create($data->origin, (int)$data->categoryid, $rows, $options, (int)$USER->id);
    redirect(ui::url('job', compact('id')));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::t('newjob'));
echo ui::link(ui::url(), ui::t('back'));
echo html_writer::tag('p', ui::t('planhelp'));
$form->display();
echo $OUTPUT->footer();
