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
 * XMLDB upgrade entry point.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply future schema changes.
 *
 * @param int $oldversion Previously installed version
 * @return bool
 */
function xmldb_local_skillupsscheduler_upgrade(int $oldversion): bool {
    global $DB;
    if ($oldversion < 2026112401) {
        $manager = $DB->get_manager();
        $table = new xmldb_table('local_skillupsscheduler_cycle');
        $parent = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'siteid');
        if (!$manager->field_exists($table, $parent)) {
            $manager->add_field($table, $parent);
        }
        upgrade_plugin_savepoint(true, 2026112401, 'local', 'skillupsscheduler');
    }
    return true;
}
