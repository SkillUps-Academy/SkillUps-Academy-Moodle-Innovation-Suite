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
 * Build reports with Moodle authorization applied before rendering or export.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {
    /**
     * Resolve authorized courses with bounded database iteration.
     *
     * @param string $action Capability suffix
     * @return array
     */
    public static function courses(string $action = 'view'): array {
        $courses = [];
        $records = get_courses('all');
        foreach ($records as $course) {
            if (scope::course($course, $action)) {
                $courses[$course->id] = $course;
            }
        }
        return $courses;
    }

    /**
     * Read scheduled records in a time window without exposing inaccessible course or group metadata.
     *
     * @param array $courses Course records
     * @param int $start Window start
     * @param int $end Window end
     * @param bool $export Apply export capability too
     * @return \Generator
     */
    public static function exams(array $courses, int $start, int $end, bool $export = false): \Generator {
        global $DB;
        if (!$courses) {
            return;
        }
        $records = $DB->get_recordset_select(
            store::EXAM,
            'starts < :end AND ends > :start',
            ['end' => $end, 'start' => $start],
            'starts ASC, id ASC',
        );
        try {
            foreach ($records as $row) {
                $course = $courses[$row->courseid] ?? null;
                if ($course && scope::exam($row, $course, $export ? 'export' : 'view')) {
                    yield $row;
                }
            }
        } finally {
            $records->close();
        }
    }

    /**
     * Write a report audit entry without storing the exported records.
     *
     * @param int $id Convocation
     * @param string $action Export or sensitive read
     */
    public static function audit(int $id, string $action): void {
        store::audit($id, $action, '');
    }
}
