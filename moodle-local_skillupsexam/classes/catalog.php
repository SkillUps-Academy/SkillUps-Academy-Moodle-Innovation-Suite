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

use stdClass;

/**
 * Maintain category-scoped academic periods and rooms.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /**
     * Save a period or a room without breaking current reservations.
     *
     * @param string $type Catalog type
     * @param int $categoryid Authorizing Moodle category
     * @param stdClass $input Moodle form
     * @return int Record ID
     */
    public static function save(string $type, int $categoryid, stdClass $input): int {
        global $DB;
        require_capability(scope::CAP . 'catalog', \context_coursecat::instance($categoryid));
        $table = match ($type) {
            'period' => store::PERIOD,
            'room' => store::ROOM,
            default => scope::fail(),
        };
        return store::write(static function () use ($DB, $table, $type, $categoryid, $input): int {
            $record = (object)['categoryid' => $categoryid, 'name' => trim($input->name)];
            $existing = !empty($input->id) ? store::get($table, (int)$input->id) : null;
            if ($existing && $existing->categoryid != $categoryid) {
                scope::fail();
            }
            if ($record->name === '' || \core_text::strlen($record->name) > ($type === 'room' ? 100 : 120)) {
                scope::fail('invalidinput');
            }
            $unique = ['categoryid' => $categoryid, 'name' => $record->name];
            if ($type === 'room') {
                $record->campus = trim($input->campus);
                $record->capacity = (int)$input->capacity;
                if (
                    $record->campus === '' || \core_text::strlen($record->campus) > 100 ||
                    $record->capacity < 1 || $record->capacity > 10000
                ) {
                    scope::fail('invalidinput');
                }
                $unique['campus'] = $record->campus;
                if ($existing && $record->capacity < $existing->capacity) {
                    $exams = $DB->get_recordset_select(
                        store::EXAM,
                        'roomid = :room AND status = :state AND ends > :now',
                        ['room' => $existing->id, 'state' => 'scheduled', 'now' => time()]
                    );
                    try {
                        foreach ($exams as $exam) {
                            if (roster::count($exam, $record->capacity) > $record->capacity) {
                                scope::fail('capacityexceeded');
                            }
                        }
                    } finally {
                        $exams->close();
                    }
                }
            } else {
                $record->starts = (int)$input->starts;
                $record->ends = (int)$input->ends;
                if ($record->starts <= 0 || $record->ends <= $record->starts) {
                    scope::fail('invalidtime');
                }
                if (
                    $existing && $DB->record_exists_select(
                        store::EXAM,
                        'periodid = :period AND status = :state AND (starts < :start OR ends > :end)',
                        ['period' => $existing->id, 'state' => 'scheduled', 'start' => $record->starts, 'end' => $record->ends]
                    )
                ) {
                    scope::fail('outsideperiod');
                }
            }
            $collision = $DB->get_field($table, 'id', $unique);
            if ($collision && (!$existing || $collision != $existing->id)) {
                scope::fail('duplicate');
            }
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record($table, $record);
                return (int)$record->id;
            }
            return (int)$DB->insert_record($table, $record);
        });
    }

    /**
     * Return catalog entries whose categories contain the currently authorized course.
     *
     * @param string $table Known catalog table
     * @param stdClass $course Moodle course
     * @return array
     */
    public static function for_course(string $table, stdClass $course): array {
        global $DB;
        return array_filter($DB->get_records($table, [], 'name ASC'), static fn($item) =>
            scope::inside($course, (int)$item->categoryid));
    }
}
