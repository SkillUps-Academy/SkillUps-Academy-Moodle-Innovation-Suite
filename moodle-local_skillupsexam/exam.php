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
 * Convocation detail, participation, incidences and authorized results.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsexam\native;
use local_skillupsexam\report;
use local_skillupsexam\roster;
use local_skillupsexam\scope;
use local_skillupsexam\store;
use local_skillupsexam\ui;

require_login();
$id = required_param('id', PARAM_INT);
$after = optional_param('after', 0, PARAM_INT);
$export = optional_param('download', 0, PARAM_BOOL);
$downloadcases = optional_param('downloadcases', 0, PARAM_BOOL);
$exam = store::get(store::EXAM, $id);
$course = get_course($exam->courseid);
if (!scope::exam($exam, $course)) {
    scope::fail();
}
require_login($course);
$scopeedit = scope::exam($exam, $course, 'edit');
$supervise = scope::exam($exam, $course, 'supervise');
$operational = $supervise || $scopeedit;
$results = native::permitted($exam, $course);
$canexport = scope::exam($exam, $course, 'export') && ($operational || $results);
$running = $exam->status === 'scheduled';
$manual = $exam->regmode === 'manual';
$route = static fn(string $op, int $userid = 0) => scope::url('operation', ['id' => $id, 'op' => $op, 'userid' => $userid]);
$opstyle = 'btn btn-sm btn-outline-secondary';
$ops = static fn(string $op, int $userid = 0) => ui::link($route($op, $userid), scope::text($op), $opstyle);
$keys = ['name'];
if ($operational) {
    $keys[] = 'attendance';
}
if ($results) {
    array_push($keys, 'participation', 'native_status', 'grade');
}
$head = array_map([scope::class, 'text'], $keys);
if ($downloadcases) {
    require_sesskey();
    if (!$canexport || !$operational) {
        scope::fail();
    }
    report::audit($id, 'incidentsexport');
    $stream = static function () use ($DB, $id, $exam): Generator {
        $cases = $DB->get_recordset(store::INCIDENT, ['examid' => $id], 'created DESC, id DESC');
        try {
            foreach ($cases as $case) {
                if (!$case->relatedid || roster::eligible($exam, (int)$case->relatedid, true)) {
                    $user = $case->relatedid ? \core_user::get_user($case->relatedid) : null;
                    yield array_map([ui::class, 'cell'], [
                        userdate($case->created), scope::text($case->severity), $user ? fullname($user) : '', $case->details ?? '',
                    ]);
                }
            }
        } finally {
            $cases->close();
        }
    };
    $columns = array_map([scope::class, 'text'], ['date', 'severity', 'memberid', 'note']);
    \core\dataformat::download_data('exam-incidents-' . $id, 'csv', $columns, $stream());
    exit;
}
if ($export) {
    require_sesskey();
    if (!$canexport) {
        scope::fail();
    }
    report::audit($id, 'rosterexport');
}
$scopeusers = $operational || $results;
$users = [];
if ($scopeusers && !$export) {
    foreach (roster::users($exam, true, true, $after) as $user) {
        $users[$user->id] = $user;
        if (count($users) >= 50) {
            break;
        }
    }
}
$make = static function (
    array $users,
    bool $showactions
) use (
    $DB,
    $id,
    $exam,
    $course,
    $supervise,
    $operational,
    $results,
    $running,
    $scopeedit,
    $manual,
    $ops
): array {
    if (!$users) {
        return [];
    }
    $ids = array_keys($users);
    $grades = $results ? native::results($exam, $course, $ids) : [];
    $marks = [];
    if ($operational) {
        [$set, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        foreach ($DB->get_records_select(store::MARK, "examid = :examid AND userid $set", ['examid' => $id] + $params) as $mark) {
            $marks[$mark->userid] = $mark;
        }
    }
    $rows = [];
    foreach ($users as $uid => $user) {
        $data = [fullname($user)];
        if ($operational) {
            $data[] = scope::text($marks[$uid]->status ?? 'unmarked');
        }
        if ($results) {
            $grade = $grades[$uid] ?? [];
            array_push($data, (string)($grade['count'] ?? ''), $grade['state'] ?? '', $grade['grade'] ?? '');
        }
        if ($showactions) {
            $buttons = '';
            if ($supervise && $running) {
                $buttons = $ops('mark', (int)$uid) . ' ' . $ops('incident', (int)$uid);
            }
            if ($scopeedit && $manual && $exam->status !== 'cancelled') {
                $buttons .= ' ' . $ops('unregister', (int)$uid);
            }
            $data = [...array_map('s', $data), $buttons];
        } else {
            $data = array_map([ui::class, 'cell'], $data);
        }
        $rows[] = $data;
    }
    return $rows;
};
if ($export) {
    $stream = static function () use ($exam, $make): Generator {
        $batch = [];
        foreach (roster::users($exam) as $user) {
            $batch[$user->id] = $user;
            if (count($batch) >= 50) {
                yield from $make($batch, false);
                $batch = [];
            }
        }
        yield from $make($batch, false);
    };
    \core\dataformat::download_data('exam-roster-' . $id, 'csv', $head, $stream());
    exit;
}
$window = native::window($exam, $course);
$period = store::get(store::PERIOD, (int)$exam->periodid);
$room = $exam->roomid ? $DB->get_record(store::ROOM, ['id' => $exam->roomid]) : null;
$formatuser = static function (int $userid): string {
    $user = $userid ? \core_user::get_user($userid) : null;
    return $user && !$user->deleted ? fullname($user) : scope::text('none');
};
if ($results && $users) {
    report::audit($id, 'resultsread');
}
scope::page('exam', context_course::instance($course->id), compact('id', 'after'));
echo $OUTPUT->header();
echo html_writer::start_div('skillups-exam');
echo $OUTPUT->heading(s($exam->name));
echo ui::link(scope::url('index', ['courseid' => $course->id]), scope::text('back'));
echo html_writer::start_div('exam-actions');
if ($scopeedit && $exam->status !== 'cancelled') {
    echo ui::link(scope::url('edit', ['id' => $id]), scope::text('editexam'), 'btn btn-primary');
    echo $ops('cancel');
    if ($manual) {
        echo $ops('register');
    }
}
if ($supervise && $running) {
    echo $ops('incident');
}
if ($canexport) {
    echo ui::link(
        scope::url('exam', ['id' => $id, 'download' => 1, 'sesskey' => sesskey()]),
        scope::text('download'),
        'btn btn-outline-secondary'
    );
}
echo html_writer::end_div();
$metadata = [
    'status' => scope::text(ui::state($exam)), 'mode' => scope::text($exam->mode),
    'starts' => userdate($exam->starts), 'ends' => userdate($exam->ends),
    'periodid' => scope::inside($course, (int)$period->categoryid) ? $period->name : scope::text('none'),
    'roomid' => $room && scope::inside($course, (int)$room->categoryid) ? $room->campus . ' / ' . $room->name : scope::text('none'),
    'teacherid' => $formatuser((int)$exam->teacherid), 'supervisor' => $formatuser((int)$exam->supervisor),
    'groupid' => $exam->groupid ? ((groups_get_group($exam->groupid)->name ?? scope::text('none'))) : scope::text('wholecourse'),
    'regmode' => scope::text($exam->regmode),
];
echo html_writer::start_tag('dl');
foreach ($metadata as $key => $value) {
    echo html_writer::tag('dt', scope::text($key)) . html_writer::tag('dd', s($value));
}
echo html_writer::end_tag('dl');
echo $OUTPUT->heading(scope::text('instruction'), 3);
echo html_writer::tag('p', nl2br(s($exam->instruction ?? '')));
echo $OUTPUT->heading(scope::text('nativeactivity'), 3);
if ($cm = $window['cm']) {
    echo ui::link($cm->url, format_string($cm->name, true, ['escape' => false, 'context' => $cm->context]));
    $dates = [];
    foreach (['open', 'due', 'close'] as $kind) {
        if ($window[$kind]) {
            $dates[] = [scope::text($kind), s(userdate($window[$kind]))];
        }
    }
    echo ui::table([scope::text('name'), scope::text('date')], $dates);
}
foreach ($window['warnings'] as $warning) {
    echo html_writer::div(scope::text($warning), 'alert alert-warning');
}
if ($scopeusers) {
    echo $OUTPUT->heading(scope::text('roster'), 3);
    echo ui::table([...$head, scope::text('actions')], $make($users, true));
    if ($after) {
        echo ui::link(scope::url('exam', ['id' => $id]), scope::text('first'));
        echo ' ';
    }
    if (count($users) === 50) {
        echo ui::link(scope::url('exam', ['id' => $id, 'after' => array_key_last($users)]), scope::text('next'));
    }
}
if ($operational) {
    echo $OUTPUT->heading(scope::text('incidents'), 3);
    if ($canexport) {
        echo ui::link(
            scope::url('exam', ['id' => $id, 'downloadcases' => 1, 'sesskey' => sesskey()]),
            scope::text('downloadcases'),
            'btn btn-outline-secondary mb-3'
        );
    }
    $casepage = optional_param('casepage', 0, PARAM_INT);
    $casepage = max(0, min(10000, $casepage));
    $rows = [];
    $fetched = $DB->get_records(store::INCIDENT, ['examid' => $id], 'created DESC, id DESC', '*', $casepage * 50, 50);
    foreach ($fetched as $case) {
        if (!$case->relatedid || roster::eligible($exam, (int)$case->relatedid, true)) {
            $rows[] = [
                s(userdate($case->created)), scope::text($case->severity),
                s($formatuser((int)$case->relatedid)), s($case->details ?? ''),
            ];
        }
    }
    echo ui::table(array_map([scope::class, 'text'], ['date', 'severity', 'memberid', 'note']), $rows);
    if ($casepage) {
        echo ui::link(scope::url('exam', ['id' => $id, 'casepage' => $casepage - 1]), scope::text('previous')) . ' ';
    }
    if (count($fetched) === 50) {
        echo ui::link(scope::url('exam', ['id' => $id, 'casepage' => $casepage + 1]), scope::text('next'));
    }
    echo $OUTPUT->heading(scope::text('history'), 3);
    $changes = [];
    $auditpage = max(0, min(10000, optional_param('auditpage', 0, PARAM_INT)));
    $entries = $DB->get_records(store::AUDIT, ['examid' => $id], 'created DESC, id DESC', '*', $auditpage * 50, 50);
    foreach ($entries as $entry) {
        if (!$entry->subjectid || roster::eligible($exam, (int)$entry->subjectid, true, false)) {
            $before = json_decode($entry->beforedata ?? '', true) ?: [];
            $afterdata = json_decode($entry->afterdata ?? '', true) ?: [];
            $delta = [];
            foreach ($afterdata as $key => $value) {
                if (($before[$key] ?? null) != $value && in_array($key, ['starts', 'ends', 'status', 'name', 'severity'], true)) {
                    $time = in_array($key, ['starts', 'ends'], true);
                    $enum = in_array($key, ['status', 'severity'], true);
                    $fmt = static fn($v) => $time ? userdate($v) : ($enum ? scope::text($v) : $v);
                    $delta[] = scope::text($key) . ' ' . (isset($before[$key]) ? $fmt($before[$key]) . ' → ' : '') . $fmt($value);
                }
            }
            $changes[] = [
                s(userdate($entry->created)), scope::text($entry->action), s($entry->reason ?? ''),
                s(implode(' · ', $delta)),
            ];
        }
    }
    echo ui::table(array_map([scope::class, 'text'], ['date', 'actions', 'reason', 'changes']), $changes);
    if ($auditpage) {
        echo ui::link(scope::url('exam', ['id' => $id, 'auditpage' => $auditpage - 1]), scope::text('previous')) . ' ';
    }
    if (count($entries) === 50) {
        echo ui::link(scope::url('exam', ['id' => $id, 'auditpage' => $auditpage + 1]), scope::text('next'));
    }
}
echo html_writer::end_div();
echo $OUTPUT->footer();
