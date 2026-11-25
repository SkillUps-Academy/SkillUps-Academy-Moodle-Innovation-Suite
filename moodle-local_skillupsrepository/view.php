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
 * Display a verified resource version, preview and institutional history.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public access is explicitly checked below.
require_once(__DIR__ . '/../../config.php');

use local_skillupsrepository\access;
use local_skillupsrepository\files;
use local_skillupsrepository\store;
use local_skillupsrepository\ui;

if (!access::public_enabled()) {
    require_login();
}
$id = required_param('id', PARAM_INT);
$asset = store::get(store::ASSET, $id);
$internal = access::internal($asset);
$categoryid = (int)$asset->categoryid;
$moderator = access::can('moderate', $categoryid);
$editable = $moderator || ($internal && access::can('contribute', $categoryid));
$default = $internal ? $asset->currentid : $asset->publishedid;
$version = optional_param('version', (int)$default, PARAM_INT);
$revision = store::get(store::REVISION, $version);
if (!access::view($asset, $revision)) {
    access::fail();
}
ui::page('view', compact('id', 'version'), context_coursecat::instance($categoryid));
echo $OUTPUT->header();
echo html_writer::start_div('skillups-repo');
echo html_writer::link(ui::url(), ui::t('back'));
echo $OUTPUT->heading(s($revision->title ?: ui::t('erased')), 2);
echo html_writer::tag('p', s(ui::t($revision->kind) . ' · ' . ui::t('revisionnumber', $revision->number)));
echo html_writer::start_div('sr-actions');
$download = access::download($asset, $revision);
$button = ['class' => 'btn btn-primary'];
if ($revision->sourceurl && $download) {
    echo html_writer::link(new moodle_url($revision->sourceurl), ui::t('openlink'), $button + ['rel' => 'noreferrer noopener']);
}
if ($revision->filename && $download) {
    echo html_writer::link(ui::fileurl($asset, $revision, true), ui::t('download'), $button);
}
if (access::reuse($asset, $revision)) {
    echo html_writer::link(ui::url('insert', ['id' => $id, 'version' => $version]), ui::t('insert'), $button);
}
echo html_writer::end_div();
echo html_writer::div(s($revision->description ?? ''), 'sr-description mb-3');
if (files::previewable($revision) && $revision->filename) {
    $attributes = ['src' => ui::fileurl($asset, $revision, false)->out(false), 'class' => 'sr-preview mb-3'];
    if ($revision->kind === 'image') {
        echo html_writer::empty_tag('img', $attributes + ['alt' => $revision->title, 'loading' => 'lazy']);
    } else {
        echo html_writer::tag($revision->kind, s(ui::t('previewunavailable')), $attributes + [
            'controls' => 'controls', 'preload' => 'none', 'aria-label' => $revision->title,
        ]);
    }
}
echo html_writer::start_tag('dl');
$display = [
    'categoryid' => (\core_course_category::get($categoryid, MUST_EXIST, true))->name,
    'audience' => ui::t($asset->audience),
    'state' => ui::t($asset->archived ? 'archived' : $revision->state),
    'license' => ui::t('license_' . $revision->license),
    'published' => $revision->published ? userdate($revision->published) : '',
];
foreach (['collection', 'area', 'program', 'level', 'credit', 'tags', 'keywords', 'filename'] as $key) {
    $display[$key] = $revision->$key;
}
if ($internal) {
    $display['reviewed'] = $revision->reviewed ? userdate($revision->reviewed) : '';
    $display['reviewnote'] = $revision->reviewnote ?? '';
}
foreach ($display as $key => $value) {
    if ($value !== '') {
        $formatted = s($value);
        if (in_array($key, ['collection', 'area', 'program', 'level', 'credit'], true)) {
            $formatted = html_writer::link(ui::url('index', [$key => $value]), $formatted);
        }
        echo html_writer::tag('dt', ui::t($key)) . html_writer::tag('dd', $formatted);
    }
}
echo html_writer::end_tag('dl');
if ($internal) {
    $current = store::get(store::REVISION, (int)$asset->currentid);
    echo html_writer::start_div('sr-actions');
    $outlined = ['class' => 'btn btn-outline-secondary'];
    if ($editable && !$asset->archived && $current->state !== 'pending') {
        echo html_writer::link(ui::url('edit', ['id' => $id]), ui::t('newrevision'), $outlined);
    }
    $actions = [];
    if ($editable && !$asset->archived && $current->state === 'draft') {
        $actions[] = 'submit';
    }
    if ($moderator) {
        array_push($actions, $asset->archived ? 'restore' : 'archive', 'audience');
        if (!$asset->archived && $current->state === 'pending') {
            array_push($actions, 'approve', 'reject');
        }
    }
    foreach ($actions as $action) {
        echo html_writer::link(ui::url('action', compact('id', 'action')), ui::t($action), $outlined);
    }
    echo html_writer::end_div();
    $panels = ['history', 'usage', 'audit'];
    $panel = optional_param('panel', 'history', PARAM_ALPHA);
    if (!in_array($panel, $panels, true)) {
        access::fail();
    }
    echo html_writer::start_div('sr-actions');
    foreach ($panels as $option) {
        $attributes = $outlined;
        if ($option === $panel) {
            $attributes['aria-current'] = 'page';
        }
        echo html_writer::link(ui::url('view', compact('id', 'version') + ['panel' => $option]), ui::t($option), $attributes);
    }
    echo html_writer::end_div();
    $page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
    $rows = [];
    if ($panel === 'history') {
        $table = store::REVISION;
        $events = $DB->get_records($table, ['assetid' => $id], 'number DESC', '*', $page * 30, 30);
        foreach ($events as $event) {
            $rows[] = [
                html_writer::link(
                    ui::url('view', ['id' => $id, 'version' => $event->id]),
                    s(ui::t('revisionnumber', $event->number))
                ),
                s($event->title), ui::t($event->state), s(userdate($event->created)),
            ];
        }
        $keys = ['version', 'title', 'state', 'created'];
    } else if ($panel === 'usage') {
        $table = store::USAGE;
        $events = $DB->get_records($table, ['assetid' => $id], 'created DESC, id DESC', '*', $page * 30, 30);
        foreach ($events as $event) {
            $course = $DB->get_record('course', ['id' => $event->courseid]);
            $context = $course ? context_course::instance($course->id) : null;
            if (
                !$context || (!is_enrolled($context, null, '', true) && !has_capability('moodle/course:view', $context)) ||
                (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context))
            ) {
                continue;
            }
            $cm = get_fast_modinfo($course)->get_cms()[$event->cmid] ?? null;
            if ((!$cm || !$cm->uservisible) && !has_capability('moodle/course:manageactivities', $context)) {
                continue;
            }
            $rows[] = [
                s(format_string($course->fullname, true, ['escape' => false, 'context' => $context])),
                $cm && $cm->uservisible ? html_writer::link($cm->url, s($cm->name)) : ui::t('removed'),
                s(userdate($event->created)), (int)$event->revisionid,
            ];
        }
        $keys = ['courseid', 'module', 'created', 'versionid'];
    } else {
        $table = store::AUDIT;
        $events = $DB->get_records($table, ['assetid' => $id], 'created DESC, id DESC', '*', $page * 30, 30);
        foreach ($events as $event) {
            $rows[] = [s(userdate($event->created)), ui::t($event->action), (int)$event->revisionid];
        }
        $keys = ['created', 'operation', 'versionid'];
    }
    echo ui::table(array_map([ui::class, 't'], $keys), $rows);
    $count = $DB->count_records($table, ['assetid' => $id]);
    echo $OUTPUT->paging_bar($count, $page, 30, ui::url('view', compact('id', 'version', 'panel')));
}
echo html_writer::end_div();
echo $OUTPUT->footer();
