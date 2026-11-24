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
 * Display course-level teacher and learner competency dashboards.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscompetency\access;
use local_skillupscompetency\assessment;
use local_skillupscompetency\catalog;
use local_skillupscompetency\program;
use local_skillupscompetency\reports;
use local_skillupscompetency\store;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$context = context_course::instance($id);
$caps = [access::CAP . 'view', access::CAP . 'review', access::CAP . 'assess', access::CAP . 'reports'];
if (!has_any_capability($caps, $context)) {
    access::fail();
}
$staff = has_any_capability([access::CAP . 'review', access::CAP . 'assess'], $context);
$canchoose = $staff || has_capability(access::CAP . 'reports', $context);
$user = optional_param('userid', 0, PARAM_INT);
if (!$user && access::can_see_subject($course, (int)$USER->id)) {
    $user = (int)$USER->id;
}
if ($user && !access::can_see_subject($course, $user)) {
    access::fail();
}
$page = max(0, optional_param('page', 0, PARAM_INT));
access::setup('course', $context, ['id' => $id, 'userid' => $user]);
$learners = [];
if ($canchoose) {
    foreach (reports::learners($course) as $candidate) {
        $learners[] = ['id' => $candidate->id, 'name' => fullname($candidate), 'selected' => $user == $candidate->id];
    }
}
$matches = [];
foreach (program::goals_for_course($id) as $match) {
    $matches[$match->competencyid][] = $match;
}
$entries = [];
$actions = $user && has_capability(access::CAP . ($user == $USER->id ? 'self' : 'assess'), $context);
foreach (access::links($course) as $item) {
    if (!$item->get_framework()->get('visible') || !access::within($context, $item->get_context())) {
        continue;
    }
    $cid = (int)$item->get('id');
    $value = $user ? assessment::current($id, $user, $cid) : 0;
    $levels = catalog::levels($item);
    $targets = [];
    foreach ($matches[$cid] ?? [] as $goal) {
        $numbers = reports::target($goal, $levels, (int)$item->get_scale()->id, $value);
        $targets[] = $numbers + ['program' => $goal->programname, 'term' => $goal->termname, 'hasgap' => $numbers['gap'] !== null];
    }
    $entries[] = [
        'name' => format_string($item->get('shortname')), 'code' => $item->get('idnumber'),
        'current' => $levels[$value] ?? access::text('unrated'), 'targets' => $targets,
        'action' => $actions ? access::url('evaluate', [
            'courseid' => $id, 'userid' => $user, 'competencyid' => $cid,
        ])->out(false) : '',
    ];
}
$csv = core_plugin_manager::instance()->get_plugin_info('dataformat_csv');
$canexport = $user && $csv && $csv->is_enabled();
if (optional_param('download', 0, PARAM_BOOL)) {
    if (!$canexport) {
        access::fail();
    }
    $keys = ['competency', 'code', 'current', 'programs', 'terms', 'expected', 'gap'];
    $headings = array_map(static fn($key) => access::text($key), $keys);
    $iterator = (static function () use ($entries): Generator {
        foreach ($entries as $entry) {
            foreach ($entry['targets'] ?: [[]] as $target) {
                yield array_map(static fn($value) => reports::cell((string)$value), [
                    $entry['name'], $entry['code'], $entry['current'], $target['program'] ?? '',
                    $target['term'] ?? '', $target['expected'] ?? '', $target['gap'] ?? '',
                ]);
            }
        }
    })();
    \core\dataformat::download_data('skillups-competency-progress', 'csv', $headings, $iterator);
    exit;
}
$history = [];
$historytotal = 0;
if ($user || $staff) {
    $conditions = ['courseid' => $id] + ($user ? ['userid' => $user] : ['status' => 'pending']);
    $records = $DB->get_recordset(store::ASSESS, $conditions, 'created DESC, id DESC');
    try {
        foreach ($records as $row) {
            if (!access::read_assessment($row)) {
                continue;
            }
            $historytotal++;
            if ($historytotal <= 50 * $page || $historytotal > 50 * ($page + 1)) {
                continue;
            }
            $item = access::competency((int)$row->competencyid);
            $levels = $row->scaleid == $item->get_scale()->id ? catalog::levels($item) : [];
            $history[] = [
                'name' => format_string($item->get('shortname')), 'status' => access::text($row->status),
                'level' => $levels[$row->levelvalue] ?? access::text('scalechanged'),
                'student' => fullname(core_user::get_user($row->userid, '*', MUST_EXIST)),
                'date' => userdate($row->created), 'kind' => access::text($row->kind),
                'link' => access::url('evaluate', ['id' => $row->id])->out(false),
            ];
        }
    } finally {
        $records->close();
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/course', [
    'course' => format_string($course->fullname), 'panel' => access::text($staff ? 'teacherpanel' : 'studentpanel'),
    'selected' => $user ? fullname(core_user::get_user($user, '*', MUST_EXIST)) : '',
    'find' => access::url('course')->out(false), 'courseid' => $id, 'learners' => $learners, 'canchoose' => $canchoose,
    'entries' => $entries, 'history' => $history, 'back' => access::url()->out(false),
    'download' => $canexport ? access::url('course', ['id' => $id, 'userid' => $user, 'download' => 1])->out(false) : '',
    'links' => has_capability('moodle/competency:coursecompetencymanage', $context) ?
        access::url('catalog', ['courseid' => $id])->out(false) : '',
]);
echo $OUTPUT->paging_bar($historytotal, $page, 50, access::url('course', ['id' => $id, 'userid' => $user]));
echo $OUTPUT->footer();
