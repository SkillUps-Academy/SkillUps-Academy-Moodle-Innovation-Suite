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
 * Simulate one selected enrollment operation.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_skillupsenrollment\form\enrollment_form;
use tool_skillupsenrollment\jobs;
use tool_skillupsenrollment\support;
use tool_skillupsenrollment\ui;

require_login();
ui::setup('create', 'single');
$form = new enrollment_form(ui::url('create'), ['import' => false]);
if ($form->is_cancelled()) {
    redirect(ui::url('index'));
}
if ($data = $form->get_data()) {
    $number = jobs::draft([1 => (array)$data], 100, (int)$data->runat);
    redirect(ui::url('job', ['id' => $number]));
}
ui::header();
echo html_writer::tag('p', s(support::text('singlehelp')));
$form->display();
echo $OUTPUT->footer();
