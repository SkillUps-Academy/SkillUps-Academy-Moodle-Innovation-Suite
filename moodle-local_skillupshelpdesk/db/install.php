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
 * Initial configurable records.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seed the local catalog. Display labels of seeds come from language strings.
 */
function xmldb_local_skillupshelpdesk_install(): void {
    global $DB;
    $seeds = [
        'category' => ['general', 'technical'],
        'campus' => ['defaultcampus'],
        'priority' => ['normal', 'urgent'],
        'severity' => ['minor', 'major', 'critical'],
        'status' => ['open', 'progress', 'waiting', 'resolved', 'closed'],
    ];
    foreach ($seeds as $kind => $codes) {
        foreach ($codes as $code) {
            $DB->insert_record('local_skillupshelpdesk_catalog', [
                'kind' => $kind,
                'code' => $code,
                'name' => '',
                'body' => '',
                'initialstate' => (int)($code === 'open'),
                'terminal' => (int)in_array($code, ['resolved', 'closed'], true),
                'responsegoal' => $kind === 'priority' ? ($code === 'urgent' ? 60 : 480) : 0,
                'resolutiongoal' => $kind === 'priority' ? ($code === 'urgent' ? 480 : 2400) : 0,
                'timemodified' => time(),
            ]);
        }
    }
}
