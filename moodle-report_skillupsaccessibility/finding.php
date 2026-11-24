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
 * Show a finding, its correction guidance and authorized review history.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/formslib.php');

use report_skillupsaccessibility\form\decision;
use report_skillupsaccessibility\review;
use report_skillupsaccessibility\rules;
use report_skillupsaccessibility\scope;
use report_skillupsaccessibility\store;

require_login();
$id = required_param('id', PARAM_INT);
$finding = store::get(store::FINDING, $id);
$asset = store::get(store::ASSET, (int)$finding->assetid);
$course = get_course($asset->courseid);
require_login($course);
[$finding, $asset, $course] = review::open($id);
$context = context_course::instance($course->id);
scope::setup('finding', $context, ['id' => $id]);
$back = scope::url('course', ['id' => $course->id]);
$form = null;
$error = '';
if (scope::allowed($course, 'review')) {
    $assignable = [0 => scope::text('unassigned'), $USER->id => fullname($USER)];
    foreach (get_enrolled_users($context, scope::CAP . 'review', 0, 'u.*', '', 0, 0, true) as $user) {
        if (!$user->deleted && !$user->suspended && scope::asset($course, $asset, (int)$user->id)) {
            $assignable[$user->id] = fullname($user);
        }
    }
    $form = new decision(scope::url('finding', ['id' => $id]), [$id, $assignable]);
    $form->set_data(['state' => $finding->state, 'assignee' => isset($assignable[$finding->assignee]) ? $finding->assignee : 0]);
    if ($form->is_cancelled()) {
        redirect($back);
    }
    if ($data = $form->get_data()) {
        require_sesskey();
        try {
            review::save($id, $data);
            redirect(scope::url('finding', ['id' => $id]), scope::text('saved'));
        } catch (moodle_exception $exception) {
            if ($exception->module !== scope::C) {
                throw $exception;
            }
            $error = $exception->getMessage();
        }
    }
}
$items = [];
$page = max(0, optional_param('page', 0, PARAM_INT));
$total = $DB->count_records(store::REVIEW, ['findingid' => $id]);
foreach ($DB->get_records(store::REVIEW, ['findingid' => $id], 'id DESC', '*', $page * 30, 30) as $entry) {
    $files = [];
    foreach (get_file_storage()->get_area_files($context->id, scope::C, review::AREA, $entry->id, 'filename', false) as $file) {
        $files[] = ['name' => $file->get_filename(), 'url' => moodle_url::make_pluginfile_url(
            $context->id,
            scope::C,
            review::AREA,
            $entry->id,
            '/',
            $file->get_filename(),
            true,
        )->out(false)];
    }
    $author = $entry->authorid ? core_user::get_user($entry->authorid) : null;
    $assignee = $entry->assignee ? core_user::get_user($entry->assignee) : null;
    $items[] = [
        'author' => $author && !$author->deleted ? fullname($author) : scope::text('unassigned'),
        'assignee' => $assignee && !$assignee->deleted ? fullname($assignee) : scope::text('unassigned'),
        'when' => userdate($entry->created), 'state' => scope::text($entry->state), 'notes' => $entry->notes, 'files' => $files,
    ];
}
$rule = rules::CATALOG[$finding->ruleid] ?? ['medium', 'review', ''];
$target = new moodle_url('/course/view.php', ['id' => $course->id]);
if ($asset->cmid) {
    $cm = get_fast_modinfo($course)->get_cm($asset->cmid);
    if ($cm->url) {
        $target = $asset->kind === 'chapter' ? new moodle_url($cm->url, ['chapterid' => $asset->refid]) : $cm->url;
    }
}
$owner = $finding->assignee ? core_user::get_user($finding->assignee) : null;
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(scope::C . '/finding', [
    'back' => $back->out(false), 'title' => scope::text('rule_' . $finding->ruleid), 'source' => $asset->label,
    'location' => $finding->location,
    'fact' => $finding->ruleid === 'pdfstructure' ? scope::text($finding->fact) : $finding->fact,
    'target' => $target->out(false),
    'criterion' => $rule[2], 'classification' => scope::text($rule[1]), 'severity' => scope::text($rule[0]),
    'recommendation' => scope::text('fix_' . $finding->ruleid), 'state' => scope::text($finding->state),
    'owner' => $owner && !$owner->deleted ? fullname($owner) : scope::text('unassigned'), 'items' => $items,
]);
if ($error) {
    echo $OUTPUT->notification(s($error), 'error');
}
if ($form) {
    echo $OUTPUT->heading(scope::text('savereview'), 2);
    $form->display();
}
echo $OUTPUT->paging_bar($total, $page, 30, scope::url('finding', ['id' => $id]));
echo $OUTPUT->footer();
