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
 * Edit institutional matrices and report authorized competency gaps.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');


use core_competency\api;
use local_skillupscompetency\access;
use local_skillupscompetency\catalog;
use local_skillupscompetency\form\editor;
use local_skillupscompetency\program;
use local_skillupscompetency\reports;
use local_skillupscompetency\store;

require_login();
$id = optional_param('id', 0, PARAM_INT);
$pid = optional_param('programid', 0, PARAM_INT);
$tid = optional_param('termid', 0, PARAM_INT);
$cid = optional_param('competencyid', 0, PARAM_INT);
$op = optional_param('op', '', PARAM_ALPHA);
$record = $pid ? store::get(store::PROG, $pid) : null;
$context = $record ? program::context($record) : context_system::instance();
if ($record && !has_any_capability([access::CAP . 'manage', access::CAP . 'reports'], $context)) {
    access::fail();
}
$term = $tid ? store::get(store::TERM, $tid) : null;
if ($term && $term->programid != $pid) {
    access::fail();
}
$base = array_filter(['programid' => $pid, 'termid' => $tid]);
$back = $record ? access::url('matrix', $base) : access::url();
$manage = $record && has_capability(access::CAP . 'manage', $context);
access::setup('matrix', $context, $base + ['op' => $op]);

if ($op && in_array($op, ['program', 'term', 'course', 'goal'], true)) {
    $defaults = [];
    switch ($op) {
        case 'program':
            $categories = core_course_category::make_categories_list(access::CAP . 'manage');
            if ((!$record && !$categories) || ($record && !$manage)) {
                access::fail();
            }
            $fields = [
                'categoryid' => ['select', $record ? [$record->categoryid => $context->get_context_name()] : $categories],
                'code' => ['text', ['maxlength' => 40]], 'name' => ['text', ['maxlength' => 120]],
                'summary' => ['textarea', []],
            ];
            $id = $pid;
            $defaults = $record ? (array)$record : [];
            break;
        case 'term':
            require_capability(access::CAP . 'manage', $context);
            $previous = $id ? store::get(store::TERM, $id) : null;
            if (!$record || ($previous && $previous->programid != $pid)) {
                access::fail();
            }
            $defaults = $previous ? (array)$previous : [];
            $fields = [
                'name' => ['text', ['maxlength' => 80]],
                'firstday' => ['text', ['placeholder' => 'YYYY-MM-DD']], 'lastday' => ['text', ['placeholder' => 'YYYY-MM-DD']],
            ];
            break;
        case 'course':
            if (!$manage) {
                access::fail();
            }
            $options = [];
            $category = core_course_category::get($record->categoryid, MUST_EXIST, true);
            foreach ($category->get_courses(['recursive' => true]) as $course) {
                $native = get_course($course->id);
                if (access::course_visible($native)) {
                    $options[$course->id] = format_string($course->fullname);
                }
            }
            $fields = ['courseid' => ['autocomplete', $options], 'unlink' => ['advcheckbox', []]];
            break;
        case 'goal':
            if (!$manage || !$term || !$cid) {
                access::fail();
            }
            $item = access::competency($cid, $context);
            $fields = [
                'expected' => ['select', [0 => access::text('removegoal')] + catalog::levels($item)],
                'crosscut' => ['advcheckbox', []],
            ];
            $goal = $DB->get_record(store::GOAL, ['termid' => $tid, 'competencyid' => $cid]);
            $defaults = $goal && $goal->scaleid == $item->get_scale()->id ? (array)$goal : [];
            break;
    }
    $routes = $base + ['op' => $op, 'id' => $id, 'competencyid' => $cid];
    $form = new editor(access::url('matrix', $routes), [$op, $routes, $fields, 'save']);
    $form->set_data($defaults);
    if ($form->is_cancelled()) {
        redirect($back);
    }
    if ($data = $form->get_data()) {
        require_sesskey();
        foreach ($routes as $key => $value) {
            $data->{$key} = $value;
        }
        $saved = program::save($op, $data);
        redirect($op === 'program' ? access::url('matrix', ['programid' => $saved]) : $back, access::text('saved'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(access::text('edit_' . $op));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}
if (!$record) {
    access::fail();
}
$courses = program::courses($record);
$reportmode = $op === 'report';
$canreport = has_capability(access::CAP . 'reports', $context);
$page = max(0, optional_param('page', 0, PARAM_INT));
$rows = [];
if ($reportmode) {
    require_capability(access::CAP . 'reports', $context);
    if (!$term) {
        access::fail();
    }
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $groupid = optional_param('groupid', 0, PARAM_INT);
    $base += ['op' => $op, 'courseid' => $courseid, 'groupid' => $groupid];
    $rowsource = reports::matrix($record, $term, $courseid, $groupid);
    $columns = ['course', 'student', 'competency', 'code', 'expected', 'current', 'gap', 'state'];
    $format = \core_plugin_manager::instance()->get_plugin_info('dataformat_csv');
    $canexport = $format && $format->is_enabled();
    if (optional_param('download', 0, PARAM_BOOL)) {
        if (!$canexport) {
            access::fail();
        }
        $headings = array_map(static fn($key) => access::text($key), $columns);
        $formatrow = static function ($row) use ($columns) {
            $row['state'] = access::text($row['state'] === 'gap' ? 'hasgap' : $row['state']);
            return array_map(static fn($key) => reports::cell((string)$row[$key]), $columns);
        };
        \core\dataformat::download_data('skillups-competency-gaps', 'csv', $headings, $rowsource, $formatrow);
        exit;
    }
    $num = 0;
    $summary = ['met' => 0, 'gap' => 0, 'unrated' => 0, 'scalechanged' => 0];
    foreach ($rowsource as $row) {
        $summary[$row['state']]++;
        if ($num >= 50 * $page && $num < 50 * ($page + 1)) {
            $row['state'] = access::text($row['state'] === 'gap' ? 'hasgap' : $row['state']);
            $rows[] = $row;
        }
        $num++;
    }
    $filters = [];
    foreach ([0 => access::text('allcourses')] + array_map(static fn($course) => $course->fullname, $courses) as $key => $label) {
        $filters[] = ['label' => $label, 'id' => $key, 'selected' => $key == $courseid];
    }
    $options = [];
    if (isset($courses[$courseid])) {
        $operator = has_capability('moodle/site:accessallgroups', context_course::instance($courseid)) ||
            (int)groups_get_course_groupmode($courses[$courseid]) !== SEPARATEGROUPS ? 0 : $USER->id;
        foreach (groups_get_all_groups($courseid, $operator) as $group) {
            $options[] = ['label' => $group->name, 'id' => $group->id, 'selected' => $group->id == $groupid];
        }
    }
    $stats = [];
    foreach ($summary as $status => $value) {
        $stats[] = ['label' => access::text($status === 'gap' ? 'hasgap' : $status), 'count' => $value];
    }
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template(access::C . '/report', [
        'title' => $record->name . ' · ' . $term->name,
        'back' => access::url('matrix', ['programid' => $pid, 'termid' => $tid])->out(false),
        'export' => $canexport ? access::url('matrix', ['download' => 1] + $base)->out(false) : '',
        'form' => access::url('matrix')->out(false),
        'pid' => $pid, 'tid' => $tid, 'filters' => $filters, 'groups' => $options, 'rows' => $rows, 'stats' => $stats,
        'heads' => array_map(static fn($key) => ['name' => access::text($key)], $columns),
    ]);
    echo $OUTPUT->paging_bar($num, $page, 50, access::url('matrix', $base));
    echo $OUTPUT->footer();
    exit;
}

$terms = [];
foreach ($DB->get_records(store::TERM, ['programid' => $pid], 'firstday DESC') as $entry) {
    $terms[] = ['label' => $entry->name, 'range' => $entry->firstday . ' – ' . $entry->lastday,
        'href' => access::url('matrix', ['programid' => $pid, 'termid' => $entry->id])->out(false)];
}
$coursecards = [];
foreach ($courses as $course) {
    $coursecards[] = ['label' => format_string($course->fullname),
        'href' => access::url('course', ['id' => $course->id])->out(false)];
}
$targets = [];
$available = [];
if ($term) {
    $mapped = [];
    foreach ($courses as $course) {
        if (
            !has_any_capability(
                ['moodle/competency:coursecompetencyview', 'moodle/competency:coursecompetencymanage'],
                context_course::instance($course->id)
            )
        ) {
            continue;
        }
        foreach (access::links($course) as $item) {
            $key = $item->get('id');
            $mapped[$key] = ($mapped[$key] ?? 0) + 1;
        }
    }
    foreach ($DB->get_records(store::GOAL, ['termid' => $term->id], 'id') as $goal) {
        try {
            $item = access::competency((int)$goal->competencyid, $context);
        } catch (moodle_exception $e) {
            continue;
        }
        $levels = catalog::levels($item);
        $targets[] = [
            'name' => format_string($item->get('shortname')), 'code' => $item->get('idnumber'),
            'level' => $goal->scaleid == $item->get_scale()->id ? ($levels[$goal->expected] ?? '') : access::text('scalechanged'),
            'crosscut' => (bool)$goal->crosscut,
            'count' => $mapped[$goal->competencyid] ?? 0,
            'edit' => $manage ?
                access::url('matrix', ['op' => 'goal', 'competencyid' => $goal->competencyid] + $base)->out(false) : '',
        ];
    }
    if ($manage) {
        $frameworkid = optional_param('frameworkid', 0, PARAM_INT);
        $frames = api::list_frameworks('shortname', 'ASC', 0, 0, $context, 'parents', true);
        $frameoptions = [];
        foreach ($frames as $frame) {
            $frameid = (int)$frame->get('id');
            $frameoptions[] = [
                'label' => format_string($frame->get('shortname')), 'id' => $frameid, 'selected' => $frameid === $frameworkid,
            ];
            if ($frameworkid === $frameid) {
                foreach (api::list_competencies(['competencyframeworkid' => $frameid], 'shortname', 'ASC') as $item) {
                    $available[] = ['label' => $item->get('idnumber') . ' · ' . format_string($item->get('shortname')),
                        'id' => $item->get('id')];
                }
            }
        }
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/matrix', [
    'name' => $record->name, 'code' => $record->code, 'summary' => $record->summary, 'terms' => $terms,
    'courses' => $coursecards, 'targets' => $targets, 'term' => $term ? $term->name : '', 'manage' => $manage,
    'edit' => $manage ? access::url('matrix', ['op' => 'program'] + $base)->out(false) : '',
    'newterm' => $manage ? access::url('matrix', ['op' => 'term'] + $base)->out(false) : '',
    'editterm' => $manage && $term ? access::url('matrix', ['op' => 'term', 'id' => $tid] + $base)->out(false) : '',
    'addcourse' => $manage ? access::url('matrix', ['op' => 'course'] + $base)->out(false) : '',
    'report' => $canreport && $term ? access::url('matrix', ['op' => 'report'] + $base)->out(false) : '',
    'back' => access::url()->out(false), 'matrixurl' => access::url('matrix')->out(false),
    'frames' => $frameoptions ?? [], 'available' => $available,
    'hasitems' => (bool)$available, 'frameid' => $frameworkid ?? 0, 'pid' => $pid, 'tid' => $tid,
]);
echo $OUTPUT->footer();
