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

namespace report_skillupsaccessibility;

/**
 * Delete plugin-owned records when Moodle removes a course.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Clean obsolete snapshots, decisions and links.
     *
     * @param \core\event\course_deleted $event Native course deletion
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $id = (int)$event->objectid;
        store::lock($id, static function () use ($DB, $id): void {
            store::transaction(static function () use ($DB, $id): void {
                $DB->delete_records(store::REVIEW, ['courseid' => $id]);
                $ids = $DB->get_fieldset_select(store::ASSET, 'id', 'courseid = ?', [$id]);
                foreach (array_chunk($ids, 300) as $chunk) {
                    foreach ([store::HIT, store::FINDING, store::REV] as $table) {
                        $DB->delete_records_list($table, 'assetid', $chunk);
                    }
                }
                $DB->delete_records(store::ASSET, ['courseid' => $id]);
                $DB->delete_records(store::JOB, ['courseid' => $id]);
            });
        });
    }
}
