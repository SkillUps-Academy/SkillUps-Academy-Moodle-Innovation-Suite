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

namespace report_skillupssecurity;

use core\event\base;
use report_skillupssecurity\control\configuration;

/**
 * Collect sanitized metadata from selected native Moodle events.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /** @var array Native event names and storage labels. */
    public const EVENTTYPES = [
        'role_assigned' => 'roleassigned', 'role_unassigned' => 'roleunassigned',
        'role_created' => 'rolecreated', 'role_deleted' => 'roledeleted', 'role_updated' => 'roleupdated',
        'role_capabilities_updated' => 'capabilities', 'role_allow_assign_updated' => 'rolepolicy',
        'role_allow_override_updated' => 'rolepolicy', 'role_allow_switch_updated' => 'rolepolicy',
        'role_allow_view_updated' => 'rolepolicy', 'config_log_created' => 'configuration',
        'user_loggedinas' => 'impersonation',
    ];

    /**
     * Extract only allowlisted metadata.
     *
     * @param base $event Native event
     */
    public static function capture(base $event): void {
        global $DB;
        $parts = explode('\\', get_class($event));
        $type = self::EVENTTYPES[end($parts)] ?? null;
        if ($type === null) {
            return;
        }
        $label = null;
        if ($type === 'configuration') {
            $name = $event->other['name'] ?? '';
            $component = $event->other['plugin'] ?? '';
            if (!in_array($component, ['', 'core'], true) || !in_array($name, configuration::SETTINGS, true)) {
                return;
            }
            $label = $name;
        }
        $DB->insert_record(store::ACTIVITY, [
            'actorid' => (int)$event->userid, 'subjectid' => (int)$event->relateduserid,
            'contextid' => (int)$event->contextid, 'objectid' => (int)$event->objectid,
            'action' => $type, 'detail' => $label, 'timecreated' => (int)$event->timecreated,
        ]);
    }
}
