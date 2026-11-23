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
 * Upload bounded tables and discard original data after validating.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_skillupsenrollment\form\enrollment_form;
use tool_skillupsenrollment\importer;
use tool_skillupsenrollment\jobs;
use tool_skillupsenrollment\support;
use tool_skillupsenrollment\ui;

require_login();
ui::setup('import', 'import');
$form = new enrollment_form(ui::url('import'), ['import' => true]);
if ($form->is_cancelled()) {
    redirect(ui::url('index'));
}
if ($data = $form->get_data()) {
    $name = $form->get_new_filename('sheet');
    $path = make_request_directory() . '/workbook';
    try {
        if (!$name || !$form->save_file('sheet', $path, true)) {
            throw new moodle_exception('invalidfile', support::C);
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        // OpenSpout checks the file name when opening an archive.
        $typed = $path . ($extension === 'xlsx' ? '.xlsx' : '.csv');
        if (!rename($path, $typed)) {
            throw new moodle_exception('invalidfile', support::C);
        }
        $rows = importer::read($typed, $extension);
        $job = jobs::draft($rows, (int)$data->batchsize, (int)$data->runat);
    } finally {
        foreach ([$path, $typed ?? $path] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (!empty($data->sheet)) {
            get_file_storage()->delete_area_files(context_user::instance($USER->id)->id, 'user', 'draft', (int)$data->sheet);
        }
    }
    redirect(ui::url('job', ['id' => $job]));
}
ui::header();
echo html_writer::tag('p', s(support::text('sheethelp')));
echo html_writer::tag('p', implode(', ', array_map('s', importer::COLUMNS)));
$form->display();
echo $OUTPUT->footer();
