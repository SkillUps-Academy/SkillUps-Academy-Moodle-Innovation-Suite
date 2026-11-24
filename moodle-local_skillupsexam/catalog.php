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
 * Manage institutional periods and rooms.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupsexam\catalog;
use local_skillupsexam\form\editor;
use local_skillupsexam\scope;
use local_skillupsexam\store;
use local_skillupsexam\ui;

require_login();
$categories = \core_course_category::make_categories_list(scope::CAP . 'catalog');
if (!$categories) {
    scope::fail();
}
$categoryid = optional_param('categoryid', (int)array_key_first($categories), PARAM_INT);
$context = context_coursecat::instance($categoryid);
require_capability(scope::CAP . 'catalog', $context);
$type = optional_param('type', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$route = scope::url('catalog', compact('categoryid', 'type', 'id'));
$parent = scope::url('catalog', compact('categoryid'));
scope::page('catalog', $context, compact('categoryid', 'type', 'id'));
$form = null;
if (in_array($type, ['period', 'room'], true)) {
    $table = $type === 'period' ? store::PERIOD : store::ROOM;
    $item = $id ? $DB->get_record($table, ['id' => $id, 'categoryid' => $categoryid], '*', MUST_EXIST) : (object)[];
    $fields = ['name' => ['text', PARAM_TEXT, true], 'id' => ['hidden', PARAM_INT, false]];
    $fields += $type === 'period' ? [
        'starts' => ['date_time_selector', null, false], 'ends' => ['date_time_selector', null, false],
    ] : ['campus' => ['text', PARAM_TEXT, true], 'capacity' => ['text', PARAM_INT, true]];
    $form = new editor($route, ['fields' => $fields]);
    $form->set_data($item);
    if ($form->is_cancelled()) {
        redirect($parent);
    }
    if ($data = $form->get_data()) {
        $data->id = $id;
        catalog::save($type, $categoryid, $data);
        redirect($parent);
    }
}
echo $OUTPUT->header();
echo html_writer::start_div('skillups-exam');
echo $OUTPUT->heading(scope::text('catalog'));
echo ui::link(scope::url(), scope::text('back'));
echo ui::filters(scope::url('catalog'), ['categoryid' => [$categoryid, $categories]]);
if ($form) {
    echo $OUTPUT->heading(scope::text($type), 3);
    $form->display();
} else {
    foreach (['period' => store::PERIOD, 'room' => store::ROOM] as $type => $table) {
        echo $OUTPUT->heading(scope::text($type), 3);
        echo ui::link(scope::url('catalog', compact('categoryid', 'type')), scope::text('new'), 'btn btn-primary mb-3');
        $rows = [];
        foreach ($DB->get_records($table, ['categoryid' => $categoryid], 'name ASC') as $row) {
            $rows[] = [
                ui::link(scope::url('catalog', ['id' => $row->id] + compact('categoryid', 'type')), $row->name),
                s($type === 'period' ? userdate($row->starts) : $row->campus),
                $type === 'period' ? s(userdate($row->ends)) : (int)$row->capacity,
            ];
        }
        $keys = $type === 'period' ? ['name', 'starts', 'ends'] : ['name', 'campus', 'capacity'];
        echo ui::table(array_map([scope::class, 'text'], $keys), $rows);
    }
}
echo html_writer::end_div();
echo $OUTPUT->footer();
