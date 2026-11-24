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
 * Display the scoped daily, weekly and monthly academic planner.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_skillupsscheduler\catalog;
use local_skillupsscheduler\scope;
use local_skillupsscheduler\planner;
use local_skillupsscheduler\calendar;
use local_skillupsscheduler\store;

require_login();
$zone = core_date::get_user_timezone_object();
$today = new DateTimeImmutable('today', $zone);
$day = optional_param('day', $today->format('Y-m-d'), PARAM_TEXT);
$mode = optional_param('range', 'week', PARAM_ALPHA);
if (!in_array($mode, ['day', 'week', 'month'], true)) {
    scope::fail('invalidinput');
}
$anchor = catalog::local_date($day, $zone->getName());
$start = match ($mode) {
    'day' => $anchor,
    'week' => $anchor->modify('monday this week'),
    'month' => $anchor->modify('first day of this month'),
};
$step = '1 ' . $mode;
$end = $start->modify("+$step");
$filters = [];
foreach (['siteid', 'cycleid', 'courseid', 'groupid', 'teacherid', 'roomid'] as $key) {
    $filters[$key] = optional_param($key, 0, PARAM_INT);
}
$issues = (bool)optional_param('issues', 0, PARAM_BOOL);
$params = ['range' => $mode, 'day' => $day] + array_filter($filters) + ($issues ? ['issues' => 1] : []);
scope::setup_page('index', scope::text('pluginname'), $params);
$PAGE->add_body_class('local-scheduler-planner');
$results = planner::slots($start->getTimestamp(), $end->getTimestamp(), $filters, $issues);
if (optional_param('download', 0, PARAM_BOOL)) {
    send_file(calendar::export(iterator_to_array($results, false)), 'skillups-schedule.ics', 0, 0, true, true, 'text/calendar');
}
$page = max(0, optional_param('page', 0, PARAM_INT));
$limit = 100;
$days = [];
$planning = false;
foreach ($DB->get_records(store::SITE, [], 'name ASC') as $site) {
    if (scope::staff($site, 'plan')) {
        $planning = true;
        break;
    }
}
if ($mode === 'month') {
    $firstday = (int)$start->format('N');
    for ($i = 1; $i < $firstday; $i++) {
        $days['pad' . $i] = ['blank' => true];
    }
}
for ($date = $start; $date < $end; $date = $date->modify('+1 day')) {
    $ymd = $date->format('Y-m-d');
    $days[$ymd] = [
        'label' => userdate($date->getTimestamp(), '%A %d'),
        'url' => scope::url('index', ['range' => 'day', 'day' => $ymd] + $params)->out(false),
        'today' => $ymd === $today->format('Y-m-d'),
        'slots' => [],
    ];
}
$total = 0;
foreach ($results as $slot) {
    if ($total >= $page * $limit && $total < ($page + 1) * $limit) {
        $ymd = (new DateTimeImmutable('@' . max($slot->starts, $start->getTimestamp())))->setTimezone($zone)->format('Y-m-d');
        $days[$ymd]['slots'][] = planner::card($slot);
    }
    $total++;
}
$selects = [];
foreach (planner::options($start->getTimestamp(), $end->getTimestamp()) as $name => $choices) {
    $options = [];
    foreach ([0 => scope::text('all')] + $choices as $id => $label) {
        $options[] = ['id' => $id, 'label' => $label, 'selected' => (int)$id === $filters[$name]];
    }
    $selects[] = ['name' => $name, 'label' => scope::text($name), 'options' => $options];
}
$ranges = [];
foreach (['day', 'week', 'month'] as $range) {
    $ranges[] = [
        'url' => scope::url('index', ['range' => $range] + $params)->out(false),
        'label' => scope::text($range . 'view'), 'selected' => $range === $mode,
    ];
}
$weekdays = [];
for ($weekday = 1; $weekday < 8; $weekday++) {
    $weekdays[] = ['label' => userdate(gmmktime(12, 0, 0, 1, 5 + $weekday, 2025), '%a', 'UTC')];
}
$unstaffed = $issues && $planning ? planner::courses_without_teachers($filters['siteid'], $filters['courseid']) : [];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(scope::C . '/planner', [
    'range' => $mode, 'day' => $day, 'ranges' => $ranges, 'selects' => $selects,
    'weekdays' => $weekdays, 'month' => $mode === 'month', 'week' => $mode === 'week',
    'days' => array_values($days), 'issues' => $issues, 'empty' => !$total,
    'title' => userdate($start->getTimestamp(), '%d %B %Y') . ' – ' . userdate($end->getTimestamp() - 1, '%d %B %Y'),
    'timezone' => $zone->getName(),
    'selectedrange' => scope::text($mode . 'view'),
    'pickday' => scope::url('index')->out(false),
    'previous' => scope::url('index', ['day' => $start->modify("-$step")->format('Y-m-d')] + $params)->out(false),
    'next' => scope::url('index', ['day' => $end->format('Y-m-d')] + $params)->out(false),
    'today' => scope::url('index', ['day' => $today->format('Y-m-d')] + $params)->out(false),
    'download' => scope::url('index', ['download' => 1] + $params)->out(false),
    'add' => $planning ? scope::url('edit', ['day' => $day, 'siteid' => $filters['siteid']])->out(false) : '',
    'catalog' => scope::admin() ? scope::url('catalog')->out(false) : '',
    'canplan' => $planning,
    'unstaffed' => $unstaffed, 'hasunstaffed' => (bool)$unstaffed,
    'ledger' => scope::admin() ? scope::url('catalog', ['queue' => 1])->out(false) : '',
]);
echo $OUTPUT->paging_bar($total, $page, $limit, scope::url('index', $params));
echo $OUTPUT->footer();
