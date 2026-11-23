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
 * Request portal and technician queue.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\catalog;
use local_skillupshelpdesk\queries;
use local_skillupshelpdesk\ui;

require_login();
ui::page(access::url(), access::text('pluginname'));
$filter = queries::request_filters();
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
[$counts, $tickets] = queries::queue($filter, $page);
$labels = [];
foreach (['status', 'priority', 'severity'] as $kind) {
    $labels[$kind] = array_map([catalog::class, 'label'], catalog::records($kind, false));
}
$metrics = [];
foreach (['total', 'open', 'closed', 'overdue'] as $key) {
    $field = $key . 'count';
    $metrics[] = ['label' => access::text('metric_' . $key), 'value' => (int)$counts->$field];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::COMPONENT . '/dashboard', ui::nav() + ui::filters($filter) + [
    'staffqueue' => access::can('work') || access::can('supervise'),
    'metrics' => $metrics,
    'filterurl' => access::url()->out(false),
    'tickets' => array_values(array_map(static fn($row): array => ui::row($row, $labels), $tickets)),
    'pager' => $OUTPUT->paging_bar((int)$counts->totalcount, $page, 25, access::url('index', $filter)),
]);
echo $OUTPUT->footer();
