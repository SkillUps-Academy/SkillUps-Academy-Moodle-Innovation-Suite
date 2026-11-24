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
 * Browse native competency frameworks and manage supplementary indicators.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');

use core_competency\api;
use local_skillupscompetency\access;
use local_skillupscompetency\catalog;
use local_skillupscompetency\form\editor;
use local_skillupscompetency\store;

require_login();
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$contextid = optional_param('contextid', context_system::instance()->id, PARAM_INT);
$competencyid = optional_param('competencyid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$level = optional_param('grade', 0, PARAM_INT);
$op = optional_param('op', '', PARAM_ALPHA);
$page = max(0, optional_param('page', 0, PARAM_INT));
$course = $courseid ? get_course($courseid) : null;
$context = $course ? context_coursecat::instance($course->category) : context::instance_by_id($contextid);
if (!in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT], true)) {
    access::fail();
}
if ($course) {
    require_login($course);
}
$params = array_filter([
    'frameworkid' => $frameworkid, 'contextid' => $context->id, 'competencyid' => $competencyid, 'courseid' => $courseid,
]);
access::setup('catalog', $context, $params);
$framework = $frameworkid ? api::read_framework($frameworkid) : null;
$corecontext = $framework ? $framework->get_context() : $context;
$maymanage = has_capability('moodle/competency:competencymanage', $corecontext);
$mayannotate = $maymanage && has_capability(access::CAP . 'catalog', $corecontext);
if ($framework && !$framework->get('visible') && !$maymanage) {
    access::fail();
}
$item = $competencyid ? access::competency($competencyid, $course ? context_course::instance($courseid) : null) : null;
if ($item && $item->get('competencyframeworkid') != $frameworkid) {
    access::fail();
}
$reset = access::url('catalog', $params);
if (in_array($op, ['importindicators', 'exportindicators'], true)) {
    if (!$framework || !$mayannotate) {
        access::fail();
    }
    if ($op === 'exportindicators') {
        $payload = json_encode(catalog::interchange($framework), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        send_file($payload, 'skillups-indicators.json', 0, 0, true, true, 'application/json');
    }
    $route = $params + ['op' => $op];
    $fields = ['document' => ['filepicker', ['accepted_types' => ['.json'], 'maxbytes' => 262144]]];
    $form = new editor(access::url('catalog', $route), [$op, $route, $fields, 'importindicators']);
    if ($form->is_cancelled()) {
        redirect($reset);
    }
    if ($form->get_data()) {
        require_sesskey();
        $contents = $form->get_file_content('document');
        if (!$contents) {
            access::fail('invaliddocument');
        }
        catalog::interchange($framework, $contents);
        redirect($reset, access::text('saved'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(access::text('importindicators'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}
if ($op) {
    if (!$item) {
        access::fail();
    }
    if ($op === 'indicator' && $mayannotate) {
        if (!isset(catalog::levels($item)[$level])) {
            access::fail('invalidlevel');
        }
        $routes = $params + ['op' => $op, 'grade' => $level];
        $form = new editor(access::url('catalog', $routes), [$op, $routes, ['indicator' => ['textarea', []]], 'save']);
        $form->set_data(['indicator' => $DB->get_field(store::IND, 'indicator', [
            'competencyid' => $competencyid, 'scaleid' => $item->get_scale()->id, 'grade' => $level,
        ]) ?: '']);
    } else if ($op === 'link' && $course) {
        require_capability('moodle/competency:coursecompetencymanage', context_course::instance($courseid));
        $modules = [0 => access::text('wholecourse')];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->uservisible && has_capability('moodle/competency:coursecompetencymanage', context_module::instance($cm->id))) {
                $modules[$cm->id] = format_string($cm->name);
            }
        }
        $routes = $params + ['op' => $op];
        $form = new editor(access::url('catalog', $routes), [$op, $routes, ['cmid' => ['select', $modules]], 'link']);
    } else {
        access::fail();
    }
    if ($form->is_cancelled()) {
        redirect($reset);
    }
    if ($data = $form->get_data()) {
        require_sesskey();
        if ($op === 'indicator') {
            catalog::indicator($competencyid, $level, $data->indicator);
        } else {
            catalog::link($course, $competencyid, (int)$data->cmid);
        }
        redirect($reset, access::text('saved'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(access::text($op));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}
$list = [];
$levels = [];
$links = ['catalog' => (new moodle_url('/admin/tool/lp/competencyframeworks.php', ['pagecontextid' => $context->id]))->out(false)];
if (has_capability('moodle/course:managescales', context_system::instance())) {
    $links['scales'] = (new moodle_url('/grade/edit/scale/index.php', ['id' => SITEID]))->out(false);
}
if (
    has_capability('moodle/competency:competencymanage', context_system::instance()) &&
    core_component::get_plugin_directory('tool', 'lpimportcsv')
) {
    $links['import'] = (new moodle_url('/admin/tool/lpimportcsv/index.php'))->out(false);
    $links['export'] = (new moodle_url('/admin/tool/lpimportcsv/export.php'))->out(false);
}
$total = 0;
if ($framework) {
    if ($mayannotate) {
        $links['importindicators'] = access::url('catalog', ['op' => 'importindicators'] + $params)->out(false);
        $links['exportindicators'] = access::url('catalog', ['op' => 'exportindicators'] + $params)->out(false);
    }
    $links['nativeframework'] = (new moodle_url('/admin/tool/lp/competencies.php', [
        'competencyframeworkid' => $frameworkid, 'pagecontextid' => $corecontext->id,
    ]))->out(false);
    $filters = ['competencyframeworkid' => $frameworkid];
    $total = api::count_competencies($filters);
    foreach (api::list_competencies($filters, 'path', 'ASC', $page * 50, 50) as $row) {
        $name = $row->get('shortname');
        $list[] = [
            'label' => format_string($name),
            'code' => $row->get('idnumber'),
            'parent' => $row->get_parent() ? format_string($row->get_parent()->get('shortname')) : '',
            'link' => access::url('catalog', ['competencyid' => $row->get('id')] + $params)->out(false),
        ];
    }
} else {
    $includes = $course ? 'parents' : 'children';
    $total = api::count_frameworks($context, $includes);
    foreach (api::list_frameworks('shortname', 'ASC', $page * 50, 50, $context, $includes, !$maymanage) as $frame) {
        $list[] = [
            'label' => format_string($frame->get('shortname')),
            'code' => $frame->get('idnumber'),
            'link' => access::url('catalog', ['frameworkid' => $frame->get('id')] + $params)->out(false),
        ];
    }
}
if ($item) {
    $values = $DB->get_records(store::IND, ['competencyid' => $competencyid, 'scaleid' => $item->get_scale()->id], 'grade');
    $bygrade = array_column($values, 'indicator', 'grade');
    foreach (catalog::levels($item) as $grade => $name) {
        $levels[] = [
            'label' => $name, 'indicator' => $bygrade[$grade] ?? '',
            'edit' => $mayannotate ? access::url('catalog', ['op' => 'indicator', 'grade' => $grade] + $params)->out(false) : '',
        ];
    }
    if ($course && has_capability('moodle/competency:coursecompetencymanage', context_course::instance($courseid))) {
        $links['link'] = access::url('catalog', ['op' => 'link'] + $params)->out(false);
    }
}
$buttons = [];
foreach ($links as $name => $href) {
    $buttons[] = ['label' => access::text($name), 'href' => $href];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/catalog', [
    'back' => access::url($course ? 'course' : 'index', $course ? ['id' => $courseid] : [])->out(false),
    'all' => access::url('catalog', ['contextid' => $context->id, 'courseid' => $courseid])->out(false),
    'title' => $framework ? format_string($framework->get('shortname')) : access::text('frameworks'),
    'item' => $item ? format_string($item->get('shortname')) : '',
    'description' => $item ?
        format_text($item->get('description'), $item->get('descriptionformat'), ['context' => $corecontext]) : '',
    'list' => $list, 'buttons' => $buttons, 'levels' => $levels,
]);
echo $OUTPUT->paging_bar($total, $page, 50, $reset);
echo $OUTPUT->footer();
