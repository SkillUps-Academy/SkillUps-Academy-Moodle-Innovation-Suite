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
 * Display authorized operational diagnostics.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsmonitor\diagnostics;
use report_skillupsmonitor\store;
use report_skillupsmonitor\ui;
use report_skillupsmonitor\util;

require_login();
$context = ui::access();
util::audit('diagnostics');
ui::header('diagnostics', util::str('diagnostics'));
$rows = [];
foreach (diagnostics::configuration() as $key => $flag) {
    $rows[] = [s(util::str('configuration_' . $key)), s(get_string($flag ? 'yes' : 'no'))];
}
echo ui::table('configuration', ['check', 'value'], $rows);
$sample = store::latest();
$data = $sample ? store::payload($sample) : [];
if (!empty($data['components'])) {
    $rows = [];
    foreach ($data['components'] as $component => $facts) {
        $rows[] = [s($component), s(util::str('component_' . $facts['status']))];
    }
    echo ui::table('components', ['component', 'state'], $rows);
    echo html_writer::tag('p', s(util::str('componentnotice')));
}
if (has_capability('moodle/site:config', $context)) {
    echo $OUTPUT->heading(util::str('tasksdetail'), 3);
    $rows = [];
    foreach (diagnostics::schedule_rows() as $task) {
        $rows[] = [s($task['classname']), s(util::str($task['label'])), s(userdate($task['when']))];
    }
    if ($rows) {
        echo ui::table('schedulecaption', ['taskclass', 'state', 'observed'], $rows);
    } else {
        echo html_writer::tag('p', s(util::str('noscheduledissues')));
    }
    $records = diagnostics::task_errors();
    if ($records === null) {
        echo html_writer::tag('p', s(util::str('tasklogunavailable')));
    } else if ($records) {
        $rows = [];
        foreach ($records as $record) {
            $length = max(0, $record->timeend - $record->timestart);
            $rows[] = [
                s(userdate((int)$record->timestart)), s($record->classname),
                s(util::str($record->type ? 'adhoc' : 'scheduled')),
                s(util::str('seconds', format_float($length, 2))),
            ];
        }
        echo ui::table('taskerrorcaption', ['observed', 'taskclass', 'type', 'duration'], $rows);
        echo html_writer::link(new moodle_url('/' . $CFG->admin . '/tasklogs.php'), s(util::str('nativetasklogs')));
    } else {
        echo html_writer::tag('p', s(util::str('notaskerrors')));
    }
}
if (has_capability('report/log:view', $context)) {
    echo $OUTPUT->heading(util::str('events'), 3);
    $events = diagnostics::events();
    if ($events) {
        $rows = array_map(static fn($event) => [s(userdate($event['when'])), s(util::str('error_' . $event['type']))], $events);
        echo ui::table('eventcaption', ['observed', 'type'], $rows);
    } else {
        echo html_writer::tag('p', s(util::str($events === null ? 'eventunavailable' : 'noevents')));
    }
}
echo html_writer::tag('p', s(util::str('errorlimits')));
echo $OUTPUT->footer();
