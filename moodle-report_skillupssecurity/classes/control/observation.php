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

namespace report_skillupssecurity\control;

/**
 * Construct stable and bounded evidence identifiers.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observation {
    /**
     * Build normalized storage fields for an observed policy state.
     *
     * @param string $rule Control identifier
     * @param string $kind Evidence category
     * @param string $severity Review priority
     * @param int $userid Affected user, if relevant
     * @param int $contextid Scope, if relevant
     * @param array $evidence Explicitly allowed independent fields
     * @param string $key Additional stable identity, such as role identifier
     * @return array
     */
    public static function make(
        string $rule,
        string $kind,
        string $severity,
        int $userid = 0,
        int $contextid = 0,
        array $evidence = [],
        string $key = '',
    ): array {
        return [
            'fingerprint' => hash('sha256', "$rule:$userid:$contextid:$key"),
            'ruleid' => $rule,
            'kind' => $kind,
            'severity' => $severity,
            'userid' => $userid,
            'contextid' => $contextid,
            'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR),
        ];
    }
}
