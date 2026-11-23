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

namespace local_skillupshelpdesk;

use context_system;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Policy for support roles and ticket visibility.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Component name. */
    public const COMPONENT = 'local_skillupshelpdesk';

    /** @var string Capability prefix. */
    public const CAP = 'local/skillupshelpdesk:';

    /**
     * Check a system-scoped capability for a real, active user.
     *
     * @param string $operation Capability suffix
     * @param stdClass|null $user Recipient or current user
     * @return bool
     */
    public static function can(string $operation, ?stdClass $user = null): bool {
        global $USER;
        $user = $user ?? $USER;
        return !empty($user->id) && empty($user->deleted) && empty($user->suspended) && !isguestuser($user) &&
            has_capability(self::CAP . $operation, context_system::instance(), $user);
    }

    /**
     * Determine whether the current user can access the portal.
     *
     * @return bool
     */
    public static function portal(): bool {
        return self::can('request') || self::can('work') || self::can('supervise') || self::can('configure');
    }

    /**
     * Check access to an existing ticket.
     *
     * @param stdClass $ticket Ticket record
     * @param stdClass|null $user Actor or current user
     * @return bool
     */
    public static function ticket(stdClass $ticket, ?stdClass $user = null): bool {
        global $USER;
        $user = $user ?? $USER;
        return self::can('supervise', $user) ||
            ($ticket->requesterid == $user->id && self::can('request', $user)) ||
            ($ticket->assigneeid == $user->id && self::can('work', $user));
    }

    /**
     * Check whether the current actor can perform staff operations on a ticket.
     *
     * @param stdClass $ticket Ticket record
     * @return bool
     */
    public static function staff(stdClass $ticket): bool {
        global $USER;
        return self::can('supervise') || ($ticket->assigneeid == $USER->id && self::can('work'));
    }

    /**
     * Require an allowed action without exposing ticket existence.
     *
     * @param bool $allowed Result of the policy
     */
    public static function require(bool $allowed): void {
        if (!$allowed) {
            throw new moodle_exception('accessdenied', self::COMPONENT);
        }
    }

    /**
     * Build a parameterized visibility filter for alias t.
     *
     * @return array SQL predicate and parameters
     */
    public static function filter(): array {
        global $USER;
        if (self::can('supervise')) {
            return ['1 = 1', []];
        }
        $branches = [];
        $params = [];
        if (self::can('request')) {
            $branches[] = 't.requesterid = :scopeowner';
            $params['scopeowner'] = $USER->id;
        }
        if (self::can('work')) {
            $branches[] = 't.assigneeid = :scopetech';
            $params['scopetech'] = $USER->id;
        }
        return [$branches ? '(' . implode(' OR ', $branches) . ')' : '1 = 0', $params];
    }

    /**
     * Resolve the current role for a candidate technician.
     *
     * @param int $userid User ID
     * @return stdClass|null
     */
    public static function technician(int $userid): ?stdClass {
        $user = \core_user::get_user($userid);
        return $user && self::can('work', $user) ? $user : null;
    }

    /**
     * Resolve a localized label.
     *
     * @param string $key Identifier
     * @param mixed $value Interpolation
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::COMPONENT, $value);
    }

    /**
     * Build a Helpdesk page URL.
     *
     * @param string $page Filename without extension
     * @param array $params Query parameters
     * @return moodle_url
     */
    public static function url(string $page = 'index', array $params = []): moodle_url {
        return new moodle_url('/local/skillupshelpdesk/' . $page . '.php', $params);
    }
}
