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

use context_system;
use moodle_url;

/**
 * Central authorization and navigation for sensitive reports.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Plugin component. */
    public const C = 'report_skillupssecurity';

    /** @var string Capability prefix. */
    public const CAP = 'report/skillupssecurity:';

    /**
     * Check the requested capabilities in the site context.
     *
     * @param string ...$actions Capability suffixes
     */
    public static function require(string ...$actions): void {
        $context = context_system::instance();
        foreach (['view', ...$actions] as $action) {
            require_capability(self::CAP . $action, $context);
        }
    }

    /**
     * Return whether the current user has the indicated capability.
     *
     * @param string $action Capability suffix
     * @return bool
     */
    public static function can(string $action): bool {
        return has_capability(self::CAP . $action, context_system::instance());
    }

    /**
     * Generate a report endpoint URL.
     *
     * @param string $page Endpoint
     * @param array $params Query parameters
     * @return moodle_url
     */
    public static function url(string $page = 'index', array $params = []): moodle_url {
        return new moodle_url('/report/skillupssecurity/' . $page . '.php', $params);
    }

    /**
     * Resolve local strings.
     *
     * @param string $key String name
     * @param mixed $data Optional interpolation
     * @return string
     */
    public static function text(string $key, mixed $data = null): string {
        return get_string($key, self::C, $data);
    }
}
