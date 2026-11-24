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

namespace local_skillupsscheduler;

/**
 * Publish Moodle calendar events and write scoped iCalendar downloads.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar {
    /**
     * Synchronize the Moodle calendar event.
     *
     * @param \stdClass $slot Session data
     */
    public static function sync(\stdClass $slot): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $event = false;
        if ($slot->eventid && $DB->record_exists('event', ['id' => $slot->eventid, 'component' => scope::C])) {
            $event = \calendar_event::load($slot->eventid);
        }
        if ($slot->status !== 'published') {
            if ($event) {
                $event->delete();
            }
            if ($slot->eventid) {
                $DB->set_field(store::SLOT, 'eventid', 0, ['id' => $slot->id]);
            }
            return;
        }
        $record = (object)[
            'name' => $slot->title,
            'description' => \html_writer::link(scope::url('session', ['id' => $slot->id]), scope::text('pluginname')),
            'format' => FORMAT_HTML, 'courseid' => $slot->courseid, 'groupid' => $slot->groupid, 'userid' => 0,
            'component' => scope::C, 'eventtype' => $slot->groupid ? 'group' : 'course',
            'type' => CALENDAR_EVENT_TYPE_STANDARD, 'visible' => 1, 'timestart' => $slot->starts,
            'timeduration' => $slot->ends - $slot->starts,
        ];
        if ($event) {
            $event->update($record, false);
        } else {
            $event = \calendar_event::create($record, false);
        }
        $DB->set_field(store::SLOT, 'eventid', $event->id, ['id' => $slot->id]);
    }

    /**
     * Export.
     *
     * @param array $slots Slots
     * @return string
     */
    public static function export(array $slots): string {
        global $CFG;
        $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//SkillUps Academy//SkillUps Scheduler//EN', 'CALSCALE:GREGORIAN'];
        foreach ($slots as $slot) {
            if ($slot->status !== 'published') {
                continue;
            }
            array_push(
                $lines,
                'BEGIN:VEVENT',
                "UID:scheduler-$slot->series-$slot->ordinal@$host",
                'DTSTAMP:' . gmdate('Ymd\THis\Z', $slot->changed),
                'DTSTART:' . gmdate('Ymd\THis\Z', $slot->starts),
                'DTEND:' . gmdate('Ymd\THis\Z', $slot->ends),
                'SEQUENCE:' . $slot->revision,
                'SUMMARY:' . strtr($slot->title, [
                    "\\" => "\\\\", "\r\n" => "\\n", "\n" => "\\n", "\r" => "\\n", ';' => '\\;', ',' => '\\,',
                ]),
                'URL:' . scope::url('session', ['id' => $slot->id])->out(false),
                'END:VEVENT',
            );
        }
        $lines[] = 'END:VCALENDAR';
        $output = '';
        foreach ($lines as $line) {
            while (strlen($line) > 75) {
                $head = mb_strcut($line, 0, 75, 'UTF-8');
                $output .= $head . "\r\n";
                $line = ' ' . substr($line, strlen($head));
            }
            $output .= $line . "\r\n";
        }
        return $output;
    }
}
