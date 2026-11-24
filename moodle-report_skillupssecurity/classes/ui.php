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

use core\context;
use report_skillupssecurity\control\privileges;

/**
 * Render scoped audit views using localized labels and escaped cells.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Format an account as a display name and numeric reference.
     *
     * @param int $id Account
     * @return string
     */
    public static function person(int $id): string {
        if (!$id) {
            return access::text('systemactor');
        }
        $user = \core_user::get_user($id);
        return $user && !$user->deleted ? fullname($user) . ' (#' . $id . ')' : '#' . $id;
    }

    /**
     * Resolve the historical context label when it still exists.
     *
     * @param int $id Context ID
     * @return string
     */
    public static function scope(int $id): string {
        if (!$id) {
            return access::text('systemactor');
        }
        $scope = context::instance_by_id($id, IGNORE_MISSING);
        return $scope ? $scope->get_context_name(true, false, false) : '#' . $id;
    }

    /**
     * Format stored time or a missing value.
     *
     * @param int $time Time
     * @return string
     */
    public static function date(int $time): string {
        return $time ? userdate($time) : access::text('notrecorded');
    }

    /**
     * Convert approved finding fields to a portable evidence list.
     *
     * @param \stdClass $finding Stored evidence
     * @return array
     */
    public static function evidence(\stdClass $finding): array {
        $raw = json_decode($finding->evidence, true, 12, JSON_THROW_ON_ERROR);
        $fields = [];
        foreach ($raw as $key => $value) {
            $value = match ($key) {
                'lastaccess', 'timecreated' => self::date($value),
                'capabilities' => implode(', ', $value),
                default => (string)$value,
            };
            $fields[] = ['label' => access::text($key), 'value' => $value];
        }
        return $fields;
    }

    /**
     * Build a table cell.
     *
     * @param mixed $text Text to escape in Mustache
     * @param string $url Optional local URL
     * @return array
     */
    public static function cell(mixed $text, string $url = ''): array {
        return ['text' => (string)$text, 'url' => $url];
    }

    /**
     * List auditable roles with selected allowed capability definitions.
     *
     * @param int $offset Page offset
     * @return array
     */
    public static function roles(int $offset): array {
        $scope = \context_system::instance();
        $roles = get_all_roles($scope);
        $visible = array_slice($roles, $offset, 25);
        $rows = [];
        foreach ($visible as $role) {
            $caps = privileges::allows($role->id, $scope);
            $rows[] = [
                self::cell(role_get_name($role, $scope)), self::cell($role->shortname),
                self::cell($role->archetype ?: access::text('notrecorded')),
                self::cell($caps ? implode(', ', $caps) : access::text('none')),
            ];
        }
        return [$rows, count($roles)];
    }
}
