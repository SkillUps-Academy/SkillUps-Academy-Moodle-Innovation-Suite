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

namespace local_skillupsflow;

/**
 * Moodle event ingress. Engine-generated events are ignored to avoid feedback loops.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Listen to the explicitly registered core events.
     */
    public static function receive(\core\event\base $event): void {
        global $DB;
        if (engine::applying()) {
            return;
        }
        $parts = explode('\\', $event->eventname);
        $type = end($parts);
        if (!in_array($type, definition::EVENTS, true)) {
            return;
        }
        $userid = $type === 'user_created' ? (int)$event->objectid : (int)$event->relateduserid;
        $courseid = $type === 'course_created' ? (int)$event->objectid : (int)$event->courseid;
        $id = (string)($event->objectid ?: $userid . '-' . $courseid);
        $flows = $DB->get_records_select(
            'local_skillupsflow_flow',
            'active > 0 AND triggertype = ?',
            [$type],
            'id',
            'id'
        );
        foreach ($flows as $flow) {
            try {
                store::admit($flow->id, $type, $id, $userid, $courseid);
            } catch (\Throwable $exception) {
                // Keep the originating Moodle operation independent of automation admission.
                store::audit('eventadmissionfailed', $flow->id);
            }
        }
    }
}
