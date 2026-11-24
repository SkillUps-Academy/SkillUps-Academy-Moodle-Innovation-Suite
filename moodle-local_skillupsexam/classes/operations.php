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

namespace local_skillupsexam;

/**
 * Manage registrations, attendance and incidents.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class operations {
    /** @var array Permitted attendance values. */
    public const MARKS = ['present', 'absent', 'late', 'excused'];
    /** @var array Permitted incident severities. */
    public const LEVELS = ['low', 'medium', 'high'];

    /**
     * Apply a scoped operation atomically and append its audit record.
     *
     * @param int $id Convocation ID
     * @param string $op Operation
     * @param int $userid Affected participant, or 0 for general incidents
     * @param string $value State or severity
     * @param string $note Plain text observations
     */
    public static function apply(int $id, string $op, int $userid, string $value, string $note): void {
        global $DB, $USER;
        store::write(static function () use ($DB, $USER, $id, $op, $userid, $value, $note): void {
            $exam = store::get(store::EXAM, $id);
            $seat = in_array($op, ['register', 'unregister'], true);
            if (
                !scope::exam($exam, get_course($exam->courseid), $seat ? 'edit' : 'supervise') ||
                ($userid && !roster::eligible($exam, $userid, true, !$seat))
            ) {
                scope::fail();
            }
            if ($seat) {
                if (!$userid || $exam->status === 'cancelled' || $exam->regmode !== 'manual') {
                    scope::fail('invalidinput');
                }
                $key = ['examid' => $id, 'userid' => $userid];
                $exists = $DB->record_exists(store::SEAT, $key);
                if ($op === 'register' && !$exists) {
                    if ($exam->roomid) {
                        $room = store::get(store::ROOM, (int)$exam->roomid);
                        if (roster::count($exam, $room->capacity) >= $room->capacity) {
                            scope::fail('capacityexceeded');
                        }
                    }
                    $DB->insert_record(store::SEAT, (object)$key);
                } else if ($op === 'unregister' && $exists) {
                    if (
                        $DB->record_exists(store::MARK, $key) ||
                        $DB->record_exists(store::INCIDENT, ['examid' => $id, 'relatedid' => $userid])
                    ) {
                        scope::fail('participantslocked');
                    }
                    $DB->delete_records(store::SEAT, $key);
                } else {
                    return;
                }
                store::audit($id, $op, '', $userid);
                return;
            }
            $note = trim($note);
            if ($exam->status !== 'scheduled' || \core_text::strlen($note) > 4000) {
                scope::fail('invalidinput');
            }
            if ($op === 'mark' && $userid && in_array($value, self::MARKS, true)) {
                $key = ['examid' => $id, 'userid' => $userid];
                $old = $DB->get_record(store::MARK, $key);
                if ($old && $old->status === $value && ($old->note ?? '') === $note) {
                    return;
                }
                $record = (object)($key + [
                    'actorid' => $USER->id, 'status' => $value, 'note' => $note, 'marked' => time(),
                ]);
                if ($old) {
                    $record->id = $old->id;
                    $DB->update_record(store::MARK, $record);
                } else {
                    $DB->insert_record(store::MARK, $record);
                }
                store::audit($id, 'mark', '', $userid, $old ? ['status' => $old->status] : null, ['status' => $value]);
            } else if ($op === 'incident' && $note !== '' && in_array($value, self::LEVELS, true)) {
                $DB->insert_record(store::INCIDENT, (object)[
                    'examid' => $id, 'authorid' => $USER->id, 'relatedid' => $userid,
                    'severity' => $value, 'details' => $note, 'created' => time(),
                ]);
                store::audit($id, 'incident', '', $userid, null, ['severity' => $value]);
            } else {
                scope::fail('invalidinput');
            }
        });
    }
}
