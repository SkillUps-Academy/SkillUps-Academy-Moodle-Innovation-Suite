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

namespace local_skillupsgradebookguardian;

use context_course;
use moodle_exception;
use moodle_url;

/** Authorization and URLs shared by the course entry points.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** @var string Frankenstyle component. */
    public const COMPONENT = 'local_skillupsgradebookguardian';

    /** @var string Capability prefix. */
    public const CAP = 'local/skillupsgradebookguardian:';

    /**
     * Check access to course-wide gradebook and hidden activity configuration.
     *
     * @param context_course $context Course context
     * @return bool
     */
    public static function visible(context_course $context): bool {
        return $context->instanceid != SITEID && has_all_capabilities([
            self::CAP . 'view', 'moodle/grade:viewall', 'moodle/grade:viewhidden', 'moodle/course:viewhiddenactivities',
        ], $context);
    }

    /**
     * Enforce course scope and optionally a component capability.
     *
     * @param context_course $context Course context
     * @param string $operation Additional capability
     */
    public static function require(context_course $context, string $operation = 'view'): void {
        if (!self::visible($context)) {
            throw new moodle_exception('accessdenied', 'admin');
        }
        require_capability(self::CAP . $operation, $context);
    }

    /**
     * Check whether the actor can approve or recover a repair.
     *
     * @param context_course $context Course context
     * @return bool
     */
    public static function canrepair(context_course $context): bool {
        return self::repairsenabled() && has_all_capabilities([
            self::CAP . 'approve', 'moodle/grade:manage',
        ], $context);
    }

    /**
     * Check the site switch for repair workflows.
     *
     * @return bool
     */
    public static function repairsenabled(): bool {
        return (bool)(get_config(self::COMPONENT)->repairenabled ?? 1);
    }

    /**
     * Resolve a localized label.
     *
     * @param string $key Language key
     * @param mixed $value Interpolation
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::COMPONENT, $value);
    }

    /**
     * Link to a course tool endpoint.
     *
     * @param string $page Endpoint basename
     * @param array $params Moodle URL parameters
     * @return moodle_url
     */
    public static function url(string $page, array $params): moodle_url {
        return new moodle_url('/local/skillupsgradebookguardian/' . $page . '.php', $params);
    }
}
