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
 * Validate an individual course or clone request.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_skillupscoursefactory\form\factory_form;
use tool_skillupscoursefactory\jobs;
use tool_skillupscoursefactory\ui;

require_login();
ui::setup('create', 'single');
$form = new factory_form(ui::url('create'), ['import' => false]);
if ($form->is_cancelled()) {
    redirect(ui::url('index'));
}
if ($data = $form->get_data()) {
    $record = (array)$data;
    $record['source'] = $data->sourceoverride ?: $data->source;
    foreach (['source', 'template'] as $key) {
        $record[$key] = $record[$key] ?: '';
    }
    $id = jobs::draft([1 => $record], 10, (int)$data->runat, ui::timezone());
    redirect(ui::url('job', ['id' => $id]));
}
ui::header();
$form->display();
echo $OUTPUT->footer();
