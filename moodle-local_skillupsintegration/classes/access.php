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

namespace local_skillupsintegration;

/**
 * Authorize users independently of the active cron or web session.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Component. */
    public const C = 'local_skillupsintegration';
    /** @var string Capability prefix. */
    public const CAP = 'local/skillupsintegration:';

    /**
     * Check system privileges for the provided active account.
     *
     * @param int $userid Moodle user
     * @param string $cap Capability
     * @return bool
     */
    public static function allowed(int $userid, string $cap): bool {
        $user = $userid > 0 ? \core_user::get_user($userid) : null;
        return $user && !$user->deleted && !$user->suspended && $user->confirmed && !isguestuser($user) &&
            has_capability(self::CAP . $cap, \context_system::instance(), $user);
    }

    /**
     * Require a capability for the current session.
     *
     * @param string $cap Capability
     */
    public static function require(string $cap): void {
        require_capability(self::CAP . $cap, \context_system::instance());
    }

    /**
     * Create a plugin route.
     *
     * @param string $page Page name
     * @param array $params Query parameters
     * @return \moodle_url
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupsintegration/' . $page . '.php', $params);
    }

    /**
     * Localise a plugin label.
     *
     * @param string $key String identifier
     * @param mixed $value Optional interpolation
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }
}
