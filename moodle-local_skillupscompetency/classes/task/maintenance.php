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

namespace local_skillupscompetency\task;

use local_skillupscompetency\access;
use local_skillupscompetency\store;

/**
 * Reconcile references to deleted courses and competencies.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class maintenance extends \core\task\scheduled_task {
    /**
     * Return the localized Moodle name.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('taskname');
    }

    /**
     * Remove bounded sets of records whose native parent has been deleted.
     */
    public function execute(): void {
        global $DB;
        store::write(static function () use ($DB): void {
            foreach (
                [
                store::MAP => ['course', 'courseid'], store::GOAL => ['competency', 'competencyid'],
                store::IND => ['competency', 'competencyid'], store::ASSESS => ['course', 'courseid'],
                ] as $table => [$parent, $field]
            ) {
                $ids = $DB->get_fieldset_sql(
                    "SELECT p.id FROM {{$table}} p WHERE NOT EXISTS (SELECT 1 FROM {{$parent}} n WHERE n.id = p.$field)
                     ORDER BY p.id",
                    [],
                    0,
                    500,
                );
                if ($ids) {
                    $DB->delete_records_list($table, 'id', $ids);
                }
            }
        });
    }
}
