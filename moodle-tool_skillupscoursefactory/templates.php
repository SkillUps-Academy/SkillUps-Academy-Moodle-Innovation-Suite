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
 * Maintain named institutional template references.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_skillupscoursefactory\form\template_form;
use tool_skillupscoursefactory\jobs;
use tool_skillupscoursefactory\support;
use tool_skillupscoursefactory\ui;

require_login();
ui::setup('templates', 'templates');
require_capability('tool/skillupscoursefactory:manage', context_system::instance());
$form = new template_form(ui::url('templates'));
if ($data = $form->get_data()) {
    $name = trim($data->name);
    if (!$name || core_text::strlen($name) > 160 || !support::source((int)$data->source, (int)$USER->id)) {
        throw new moodle_exception('invalidtemplate', support::C);
    }
    $lock = support::lock('templates', 5);
    if (!$lock) {
        throw new moodle_exception('busy', support::C);
    }
    try {
        if ($DB->get_field(jobs::TPL, 'id', ['name' => $name])) {
            throw new moodle_exception('duplicate', support::C);
        }
        $DB->insert_record(jobs::TPL, ['name' => $name, 'sourceid' => $data->source]);
        jobs::audit(0, 'templatecreated');
    } finally {
        $lock->release();
    }
    redirect(ui::url('templates'), support::text('templatesaved'));
}
$remove = optional_param('remove', 0, PARAM_INT);
if ($remove) {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidaction', support::C);
    }
    $DB->delete_records(jobs::TPL, ['id' => $remove]);
    jobs::audit(0, 'templateremoved');
    redirect(ui::url('templates'));
}
ui::header();
echo html_writer::tag('p', s(support::text('templatehelp')));
$form->display();
$rows = [];
$count = $DB->count_records(jobs::TPL);
$page = max(0, min(optional_param('page', 0, PARAM_INT), (int)ceil($count / 25) - 1));
foreach ($DB->get_records(jobs::TPL, [], 'id DESC', '*', 25 * $page, 25) as $template) {
    if (support::source((int)$template->sourceid, (int)$USER->id)) {
        $rows[] = [
            (int)$template->id, s($template->name), (int)$template->sourceid,
            $OUTPUT->single_button(ui::url('templates', ['remove' => $template->id]), support::text('removetemplate'), 'post'),
        ];
    }
}
if ($rows) {
    ui::table('templates', ['templateid', 'templatename', 'source', 'action'], $rows);
    echo $OUTPUT->paging_bar($count, $page, 25, ui::url('templates'));
}
echo $OUTPUT->footer();
