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
 * Learner pathway selection, map, progress, next steps and timeline.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupspathways\access;
use local_skillupspathways\progress;
use local_skillupspathways\store;
use local_skillupspathways\ui;

$courseid = required_param('courseid', PARAM_INT);
$pathid = optional_param('pathid', 0, PARAM_INT);
[$course, $context] = access::course($courseid);
require_login($course);
require_capability(access::CAP . 'view', $context);
$url = access::url('index', $courseid, $pathid ? ['pathid' => $pathid] : []);
ui::page($course, $url, access::text('pluginname'));
$userid = (int)$USER->id;
$participant = access::enrolled($course, $userid, true);
$manager = has_capability(access::CAP . 'manage', $context);
$data = ui::nav($courseid, $pathid) + ['url' => $url->out(false), 'sesskey' => sesskey()];
if (!$pathid) {
    $data['listing'] = true;
    $data['paths'] = [];
    $table = store::PATH;
    $versions = store::VERSION;
    $records = $DB->get_records_sql(
        "SELECT p.id, p.name, p.enabled, v.id AS versionid, v.name AS publishedname, v.description, v.versionnum
           FROM {{$table}} p LEFT JOIN {{$versions}} v ON v.id = p.activeversion AND v.pathid = p.id
          WHERE p.courseid = :entry
       ORDER BY p.timemodified DESC, p.id DESC",
        ['entry' => $courseid],
        0,
        100
    );
    foreach ($records as $record) {
        if ((!$record->enabled || !$record->versionid || !$participant) && !$manager) {
            continue;
        }
        $data['paths'][] = [
            'name' => $record->publishedname ?? $record->name, 'description' => $record->description ?? '',
            'version' => $record->versionnum ? access::text('versionvalue', $record->versionnum) : access::text('draftonly'),
            'url' => access::url('index', $courseid, ['pathid' => $record->id])->out(false),
        ];
    }
} else {
    $path = store::path($courseid, $pathid);
    if ((!$path->enabled || !$path->activeversion || !$participant) && !$manager) {
        access::deny();
    }
    if (data_submitted()) {
        require_sesskey();
        progress::sync($path, $course);
        redirect($url, access::text('progresssaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $data['detail'] = true;
    $data['name'] = $path->name;
    $data['draftonly'] = !$path->activeversion;
    $data['disabled'] = !$path->enabled;
    if ($path->activeversion) {
        $registration = $participant ? progress::get($pathid, $userid) : null;
        $version = store::version($pathid, (int)($registration->versionid ?? $path->activeversion));
        $data['name'] = $version->name;
        $data['description'] = $version->description;
        $data['version'] = access::text('versionvalue', $version->versionnum);
        $data['differentversion'] = $version->id != $path->activeversion;
        $data['canrefresh'] = $participant && $path->enabled;
        $data['syncbutton'] = access::text($registration ? 'refresh' : 'start');
        $visibletitles = [];
        if ($registration && $path->enabled) {
            $result = progress::evaluate($version, $userid);
            $rows = ui::steps($result);
            foreach ($rows as $row) {
                if ($row['state'] !== 'unavailable') {
                    $visibletitles[$row['key']] = $row['title'];
                }
            }
            $data['play'] = true;
            $data['complete'] = $result['required'] && $result['passed'] === $result['required'];
            $data['score'] = access::text('score', (object)['done' => $result['passed'], 'total' => $result['required']]);
            $data['checked'] = access::text('lastchecked', userdate($registration->timechecked));
            $data['steps'] = $rows;
            $data['next'] = array_values(array_filter($rows, static fn(array $row): bool => $row['recommended']));
            $data['diagram'] = json_encode(ui::diagram($rows), JSON_THROW_ON_ERROR);
            $data['diagramlabels'] = json_encode(ui::diagram_labels(), JSON_THROW_ON_ERROR);
            $PAGE->requires->js_call_amd(access::C . '/designer', 'initMap', ['skillups-pathways-map']);
        }
        if ($registration) {
            $page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
            $criteria = ['pathid' => $pathid, 'userid' => $userid];
            $history = $DB->get_records(store::HISTORY, $criteria, 'id DESC', '*', $page * 20, 20);
            $versions = $DB->get_records(store::VERSION, ['pathid' => $pathid], 'versionnum ASC', 'id,versionnum');
            $data['hashistory'] = (bool)$history;
            foreach ($history as $event) {
                $node = match ($event->nodekey) {
                    '_version' => access::text('migrationhistory'),
                    '_path' => access::text('pathhistory'),
                    default => $event->versionid == $version->id ?
                        ($visibletitles[$event->nodekey] ?? access::text('stephistory')) : access::text('stephistory'),
                };
                $data['history'][] = [
                    'version' => access::text('versionvalue', $versions[$event->versionid]->versionnum),
                    'node' => $node,
                    'before' => access::text('state_' . $event->oldstate),
                    'after' => access::text('state_' . $event->newstate), 'date' => userdate($event->timecreated),
                ];
            }
            $data['pager'] = $OUTPUT->paging_bar($DB->count_records(store::HISTORY, $criteria), $page, 20, $url);
        }
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/portal', $data);
echo $OUTPUT->footer();
