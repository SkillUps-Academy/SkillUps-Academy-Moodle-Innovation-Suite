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

namespace local_skillupsprivacy;

use context_system;
use moodle_url;
use tool_dataprivacy\api;

/**
 * Require a native privacy officer and plugin-specific capabilities.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Component. */
    public const C = 'local_skillupsprivacy';
    /** @var string Capability prefix. */
    public const CAP = 'local/skillupsprivacy:';

    /**
     * Check an arbitrary Moodle user independently of the current session.
     *
     * @param int $userid Operator
     * @param string $action Capability suffix
     * @return bool
     */
    public static function allowed(int $userid, string $action = 'view'): bool {
        if (!$userid || !(is_siteadmin($userid) || isset(api::get_site_dpos()[$userid]))) {
            return false;
        }
        $user = \core_user::get_user($userid);
        $system = context_system::instance();
        return $user && !$user->deleted && !$user->suspended && !isguestuser($user) &&
            has_capability(self::CAP . 'view', $system, $user) &&
            has_capability(self::CAP . $action, $system, $user);
    }

    /**
     * Validate the current operator.
     *
     * @param string $action Capability suffix
     */
    public static function require(string $action = 'view'): void {
        global $USER;
        if (!self::allowed((int)$USER->id, $action)) {
            throw new \moodle_exception('restricted', self::C);
        }
    }

    /**
     * Create a local endpoint URL.
     *
     * @param string $page Endpoint name
     * @param array $params Query parameters
     * @return moodle_url
     */
    public static function url(string $page = 'index', array $params = []): moodle_url {
        return new moodle_url('/local/skillupsprivacy/' . $page . '.php', $params);
    }

    /**
     * Localize a label.
     *
     * @param string $key String ID
     * @param mixed $value Optional value
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }
}
