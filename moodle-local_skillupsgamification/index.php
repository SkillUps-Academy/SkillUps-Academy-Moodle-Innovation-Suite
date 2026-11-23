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
 * Learner dashboard, awards, history, consent and rankings.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsgamification\access;
use local_skillupsgamification\engine;
use local_skillupsgamification\goals;
use local_skillupsgamification\integration\learning_path;
use local_skillupsgamification\ranking;
use local_skillupsgamification\store;
use local_skillupsgamification\team;
use local_skillupsgamification\ui;

$courseid = required_param('courseid', PARAM_INT);
[$course, $context] = access::course($courseid);
require_login($course);
require_capability(access::CAP . 'view', $context);
$tab = optional_param('tab', 'progress', PARAM_ALPHA);
$tab = in_array($tab, ['progress', 'history', 'rankings'], true) ? $tab : 'progress';
$url = access::url('index', $courseid, ['tab' => $tab]);
ui::page($course, $url, access::text('pluginname'));

$userid = (int)$USER->id;
$config = store::configuration($courseid);
$player = store::player($courseid, $userid);
$participant = access::participant($context, $userid);
$eligible = $participant && $config->enabled && !$player->excluded;
if (data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $message = match ($action) {
        'join', 'leave' => (static function () use ($action, $courseid): string {
            ranking::consent($courseid, $action === 'join');
            return access::text('preferencesaved');
        })(),
        'claim' => (static function () use ($courseid): string {
            goals::claim($courseid, required_param('goalid', PARAM_INT));
            return access::text('claimrecorded');
        })(),
        'sync' => access::text('synced', engine::sync_path($courseid, $userid)),
        default => throw new moodle_exception('notavailable', access::C),
    };
    redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}
$data = [
    'tabs' => ui::tabs($courseid), 'courseid' => $courseid, 'sesskey' => sesskey(), 'url' => $url->out(false),
    'disabled' => !$config->enabled, 'excluded' => (bool)$player->excluded,
];
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
if ($config->enabled && $tab === 'progress') {
    $totals = goals::totals($courseid, $userid);
    $levelsize = max(1, (int)$config->xplevel);
    $data += $totals + [
        'overview' => true, 'level' => intdiv($totals['xp'], $levelsize) + 1, 'levelvalue' => $totals['xp'] % $levelsize,
        'levelsize' => $levelsize, 'cansync' => $eligible && $config->pathenabled && (bool)learning_path::provider(),
    ];
    $criteria = ['courseid' => $courseid, 'enabled' => 1];
    $records = $DB->get_records(store::GOAL, $criteria, 'id DESC', '*', $page * 12, 12);
    $own = [];
    if ($records) {
        [$in, $params] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED, 'goal');
        $params += ['course' => $courseid, 'person' => $userid];
        $grants = $DB->get_records_select(store::GRANT, "courseid = :course AND userid = :person AND goalid $in", $params);
        foreach ($grants as $grant) {
            $own[$grant->goalid] = $grant;
        }
    }
    $group = $eligible ? team::membership($config, $userid) : 0;
    if ($group) {
        $visible = groups_get_all_groups($courseid, $userid, $config->groupingid);
        $data['teamname'] = isset($visible[$group]) ? access::text('teamname', $visible[$group]->name) : '';
    }
    $data['goals'] = array_values(array_map(static fn($goal): array => ui::goal(
        $goal,
        $own[$goal->id] ?? null,
        $userid,
        $group,
        $totals,
        $eligible
    ), $records));
    $data['pager'] = $OUTPUT->paging_bar($DB->count_records(store::GOAL, $criteria), $page, 12, $url);
} else if ($tab === 'history') {
    $criteria = ['courseid' => $courseid, 'userid' => $userid];
    $data['history'] = true;
    $ledger = $DB->get_records(store::LEDGER, $criteria, 'timeearned DESC, id DESC', '*', $page * 20, 20);
    $rules = $DB->get_records(store::RULE, ['courseid' => $courseid], 'id ASC', 'id,name');
    $data['movements'] = array_values(array_map(static fn($row): array => [
        'date' => userdate($row->timeearned), 'points' => $row->points, 'xp' => $row->xp,
        'source' => access::text('source_' . $row->kind),
        'label' => $rules[$row->ruleid]->name ?? access::text('archivedrule'),
    ], $ledger));
    $entries = $DB->get_records(store::GRANT, $criteria, 'id DESC', '*', 0, 200);
    $catalog = $DB->get_records(store::GOAL, ['courseid' => $courseid], 'id ASC', 'id,name,kind');
    $data['claims'] = array_values(array_map(static fn($entry): array => [
        'date' => userdate($entry->timecreated), 'state' => access::text('state_' . $entry->state),
        'label' => $catalog[$entry->goalid]->name ?? access::text('archivedgoal'), 'cost' => $entry->cost,
    ], $entries));
    $data['pager'] = $OUTPUT->paging_bar($DB->count_records(store::LEDGER, $criteria), $page, 20, $url);
} else if ($tab === 'rankings') {
    $data['rankings'] = true;
    $data['mayjoin'] = $eligible && $config->rankingenabled && !$player->consent;
    $data['mayleave'] = $participant && $player->consent;
    $data['rankingvisible'] = $eligible && $config->rankingenabled && $player->consent;
    [$individual, $teams] = ranking::read($course);
    $data['boards'] = [];
    foreach (['individualranking' => $individual, 'teamranking' => $teams] as $label => $rows) {
        foreach ($rows as $position => &$row) {
            $row['position'] = $position + 1;
        }
        unset($row);
        $data['boards'][] = ['heading' => access::text($label), 'rows' => $rows];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/dashboard', $data);
echo $OUTPUT->footer();
