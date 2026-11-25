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

namespace local_skillupsrepository;

/**
 * Maintain library records as native Moodle modules and categories are removed.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Keep historical provenance when the course copy is removed.
     *
     * @param \core\event\course_module_deleted $event Deleted module event
     */
    public static function module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $DB->set_field(store::USAGE, 'removed', time(), ['cmid' => $event->objectid, 'removed' => 0]);
    }

    /**
     * Remove the deleted category's library after Moodle removes its context.
     *
     * @param \core\event\course_category_deleted $event Deleted category event
     */
    public static function category_deleted(\core\event\course_category_deleted $event): void {
        self::purge((int)$event->objectid);
    }

    /**
     * Remove all institutionally owned library records in a category.
     *
     * @param int $categoryid Category ID
     */
    public static function purge(int $categoryid): void {
        global $DB;
        store::write($categoryid, static function () use ($DB, $categoryid): void {
            $context = \context_coursecat::instance($categoryid, IGNORE_MISSING);
            if ($context) {
                get_file_storage()->delete_area_files($context->id, access::COMPONENT, files::AREA);
            }
            $conditions = 'assetid IN (SELECT id FROM {' . store::ASSET . '} WHERE categoryid = :category)';
            foreach ([store::USAGE, store::AUDIT, store::REVISION] as $table) {
                $DB->delete_records_select($table, $conditions, ['category' => $categoryid]);
            }
            $DB->delete_records(store::ASSET, ['categoryid' => $categoryid]);
        });
    }
}
