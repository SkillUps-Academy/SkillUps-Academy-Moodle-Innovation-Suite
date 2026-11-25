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

namespace local_skillupscommunication;

/**
 * Authorize campaigns in the system or a single Moodle category subtree.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Plugin component. */
    public const C = 'local_skillupscommunication';
    /** @var string Capability prefix. */
    public const CAP = 'local/skillupscommunication:';

    /**
     * Return a valid scope or reject a foreign or deleted context.
     *
     * @param int $id Moodle context ID
     * @return \context
     */
    public static function scope(int $id): \context {
        $context = \context::instance_by_id($id, IGNORE_MISSING);
        if (!$context || !in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT], true)) {
            self::fail();
        }
        return $context;
    }

    /**
     * Check a capability and underlying scope visibility.
     *
     * @param \context $context Institutional scope
     * @param string $action Capability suffix
     * @param int $userid Account, or current account
     * @return bool
     */
    public static function can(\context $context, string $action, int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int)$USER->id;
        if (
            !$userid || isguestuser($userid) || !has_capability(self::CAP . 'view', $context, $userid) ||
            !has_capability(self::CAP . $action, $context, $userid)
        ) {
            return false;
        }
        return $context->contextlevel !== CONTEXT_COURSECAT ||
            (bool)\core_course_category::get($context->instanceid, IGNORE_MISSING, false, $userid);
    }

    /**
     * Whether an inner context lies within the allowed scope.
     *
     * @param \context $outer Institutional scope
     * @param \context $inner Target context
     * @return bool
     */
    public static function contains(\context $outer, \context $inner): bool {
        return str_starts_with($inner->path . '/', $outer->path . '/');
    }

    /**
     * List authorizable system or category contexts.
     *
     * @param string $action Capability suffix
     * @return array Context IDs and Moodle labels
     */
    public static function scopes(string $action = 'view'): array {
        $system = \context_system::instance();
        $result = self::can($system, $action) ? [$system->id => get_string('site', self::C)] : [];
        foreach (\core_course_category::make_categories_list(self::CAP . $action) as $id => $name) {
            $context = \context_coursecat::instance($id);
            if (self::can($context, $action)) {
                $result[$context->id] = $name;
            }
        }
        return $result;
    }

    /**
     * Abort with a localizable access or validation error.
     *
     * @param string $key Language string
     */
    public static function fail(string $key = 'unavailable'): never {
        throw new \moodle_exception($key, self::C);
    }
}
