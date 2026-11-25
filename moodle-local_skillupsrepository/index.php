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
 * Browse and moderate the institutional resource library.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public access is explicitly checked below.
require_once(__DIR__ . '/../../config.php');

use local_skillupsrepository\access;
use local_skillupsrepository\catalog;
use local_skillupsrepository\files;
use local_skillupsrepository\store;
use local_skillupsrepository\ui;

if (!access::public_enabled()) {
    require_login();
}
$moderation = optional_param('moderation', 0, PARAM_BOOL);
$categories = catalog::categories('view') + catalog::categories('moderate') + catalog::categories('contribute');
if ($moderation && !catalog::categories('moderate') && !catalog::categories('contribute')) {
    access::fail();
}
if (access::public_enabled()) {
    $table = store::ASSET;
    $categories += $DB->get_records_sql_menu(
        "SELECT DISTINCT c.id, c.name FROM {course_categories} c JOIN {{$table}} a ON a.categoryid = c.id
          WHERE c.visible = 1 AND a.archived = 0 AND a.publishedid > 0 AND a.audience = :public ORDER BY c.name",
        ['public' => 'public']
    );
}
$filter = ['moderation' => $moderation, 'categoryid' => optional_param('categoryid', 0, PARAM_INT)];
foreach (['q', 'title', 'description', 'tags', 'credit', 'collection', 'area', 'program', 'level', 'keywords'] as $field) {
    $filter[$field] = core_text::substr(trim(optional_param($field, '', PARAM_TEXT)), 0, 255);
}
foreach (['kind', 'extension', 'state', 'sort'] as $field) {
    $filter[$field] = optional_param($field, '', PARAM_ALPHANUM);
}
$layout = optional_param('layout', 'grid', PARAM_ALPHA) === 'list' ? 'list' : 'grid';
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
[$items, $count] = catalog::search($filter, $page);

$params = array_filter($filter) + ['layout' => $layout];
ui::page('index', $params + ['page' => $page]);
echo $OUTPUT->header();
echo html_writer::start_div('skillups-repo');
echo $OUTPUT->heading(ui::t($moderation ? 'moderation' : 'pluginname'), 2);
echo html_writer::start_div('sr-actions');
echo html_writer::link(ui::url(), ui::t('browse'), ['class' => 'btn btn-outline-secondary']);
if (catalog::categories('contribute') || catalog::categories('moderate')) {
    echo html_writer::link(ui::url('edit'), ui::t('newresource'), ['class' => 'btn btn-primary']);
    echo html_writer::link(ui::url('index', ['moderation' => 1]), ui::t('moderation'), ['class' => 'btn btn-outline-secondary']);
}
echo html_writer::end_div();

echo html_writer::start_div('sr-layout');
echo html_writer::start_tag('aside', ['aria-label' => ui::t('filters')]);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => ui::url()->out_omit_querystring(), 'class' => 'sr-filters']);
foreach (['moderation' => $moderation, 'layout' => $layout] as $key => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
}
$textfield = static function (string $key) use ($filter): string {
    $id = 'sr-filter-' . $key;
    return html_writer::label(ui::t($key), $id, false) . html_writer::empty_tag('input', [
        'id' => $id, 'name' => $key, 'type' => $key === 'q' ? 'search' : 'text',
        'class' => 'form-control', 'value' => $filter[$key],
    ]);
};
$select = static function (string $key, array $options) use ($filter): string {
    $id = 'sr-filter-' . $key;
    return html_writer::label(ui::t($key), $id, false)
        . html_writer::select($options, $key, $filter[$key], false, ['id' => $id, 'class' => 'form-select']);
};
echo $textfield('q');
echo $select('categoryid', [0 => ui::t('all')] + $categories);
echo $select('kind', ['' => ui::t('all')] + ui::options(['document', 'image', 'audio', 'video', 'package', 'link']));
echo $select('extension', ['' => ui::t('all')] + array_combine(array_keys(files::TYPES), array_keys(files::TYPES)));
echo $select('sort', ui::options(['recent', 'popular', 'relevance']));
if ($moderation) {
    echo $select('state', ['' => ui::t('all')] + ui::options(['draft', 'pending', 'approved', 'rejected']));
}
$advanced = ['title', 'description', 'tags', 'credit', 'collection', 'area', 'program', 'level', 'keywords'];
$expanded = array_filter(array_intersect_key($filter, array_flip($advanced)));
echo html_writer::start_tag('details', $expanded ? ['open' => 'open'] : []);
echo html_writer::tag('summary', ui::t('advanced'));
foreach ($advanced as $key) {
    echo $textfield($key);
}
echo html_writer::end_tag('details');
echo html_writer::tag('button', ui::t('filter'), ['type' => 'submit', 'class' => 'btn btn-primary w-100 mt-3']);
echo html_writer::end_tag('form');
echo html_writer::end_tag('aside');

echo html_writer::start_tag('section', ['aria-label' => ui::t('results')]);
echo html_writer::start_div('sr-results-bar');
echo html_writer::tag('strong', ui::t('resultcount', $count));
foreach (['grid', 'list'] as $variant) {
    $attributes = ['class' => 'btn btn-sm btn-outline-secondary'];
    if ($variant === $layout) {
        $attributes['aria-current'] = 'page';
    }
    echo html_writer::link(ui::url('index', array_replace($params, ['layout' => $variant])), ui::t($variant), $attributes);
}
echo html_writer::end_div();
$cards = [];
foreach ($items as $item) {
    $cards[] = [
        'url' => ui::url('view', ['id' => $item->id, 'version' => $item->revisionid])->out(false),
        'kind' => ui::t($item->kind), 'category' => $item->categoryname,
        'title' => $item->title ?: ui::t('erased'), 'description' => shorten_text($item->description ?? '', 155),
        'collection' => $item->collection, 'credit' => $item->credit, 'tags' => $item->tags,
        'status' => $item->archived ? ui::t('archived') : ($item->state === 'approved' ? '' : ui::t($item->state)),
        'license' => ui::t('license_' . $item->license),
    ];
}
if ($cards) {
    echo $OUTPUT->render_from_template('local_skillupsrepository/catalogue', [
        'list' => $layout === 'list', 'items' => $cards,
    ]);
} else {
    echo html_writer::div(ui::t('empty'), 'alert alert-info');
}
echo $OUTPUT->paging_bar($count, $page, 24, ui::url('index', $params));
echo html_writer::end_tag('section');
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
