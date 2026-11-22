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
 * Version-specific schema upgrades.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initial schema.
 */
function xmldb_report_skillupsinsight_upgrade($oldversion): bool {
    global $DB;
    if ($oldversion < 2026112101) {
        $manager = $DB->get_manager();
        $table = new xmldb_table('report_skillupsinsight_run');
        $field = new xmldb_field('cursor', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'periodid');
        if ($manager->field_exists($table, $field)) {
            $manager->rename_field($table, $field, 'lastcourseid');
        }
        $manager->add_key($table, new xmldb_key(
            'period',
            XMLDB_KEY_FOREIGN,
            ['periodid'],
            'report_skillupsinsight_period',
            ['id']
        ));
        $manager->add_key(
            new xmldb_table('report_skillupsinsight_snapshot'),
            new xmldb_key('run', XMLDB_KEY_FOREIGN, ['runid'], 'report_skillupsinsight_run', ['id'])
        );
        $watch = new xmldb_table('report_skillupsinsight_watch');
        $manager->drop_index($watch, new xmldb_index('preset', XMLDB_INDEX_NOTUNIQUE, ['presetid']));
        $manager->add_key($watch, new xmldb_key(
            'preset',
            XMLDB_KEY_FOREIGN,
            ['presetid'],
            'report_skillupsinsight_preset',
            ['id']
        ));
        upgrade_plugin_savepoint(true, 2026112101, 'report', 'skillupsinsight');
    }
    return true;
}
