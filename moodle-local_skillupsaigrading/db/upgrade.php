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
 * Plugin database migrations.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initial release upgrade entry point.
 */
function xmldb_local_skillupsaigrading_upgrade($oldversion): bool {
    global $DB;
    if ($oldversion < 2026112101) {
        $manager = $DB->get_manager();
        $table = new xmldb_table('local_skillupsaigrading_job');
        $field = new xmldb_field('evaluatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'human');
        if (!$manager->field_exists($table, $field)) {
            $manager->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026112101, 'local', 'skillupsaigrading');
    }
    return true;
}
