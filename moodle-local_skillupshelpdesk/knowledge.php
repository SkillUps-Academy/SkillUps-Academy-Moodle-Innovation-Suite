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
 * Search and read published help articles.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\catalog;
use local_skillupshelpdesk\ui;

$id = max(0, optional_param('id', 0, PARAM_INT));
$q = core_text::substr(trim(optional_param('q', '', PARAM_TEXT)), 0, 80);
$url = $id ? access::url('knowledge', ['id' => $id]) : access::url('knowledge');
require_login();
ui::page($url, access::text('knowledge'));
$data = ui::nav();
if ($id) {
    $record = catalog::entry('article', $id);
    $data['article'] = ['title' => catalog::label($record), 'body' => $record->body];
} else {
    $articles = [];
    $search = core_text::strtolower($q);
    foreach (catalog::records('article') as $record) {
        $title = catalog::label($record);
        if ($search === '' || str_contains(core_text::strtolower($title . ' ' . $record->body), $search)) {
            $articles[] = [
                'title' => $title,
                'summary' => shorten_text($record->body, 160),
                'url' => access::url('knowledge', ['id' => $record->id])->out(false),
            ];
        }
    }
    $page = max(0, min(500, optional_param('page', 0, PARAM_INT)));
    $data['listing'] = true;
    $data['q'] = $q;
    $data['articles'] = array_slice($articles, $page * 15, 15);
    $data['pager'] = $OUTPUT->paging_bar(count($articles), $page, 15, access::url('knowledge', ['q' => $q]));
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::COMPONENT . '/knowledge', $data);
echo $OUTPUT->footer();
