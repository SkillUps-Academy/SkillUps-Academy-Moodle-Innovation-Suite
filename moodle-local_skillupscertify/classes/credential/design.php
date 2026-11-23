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

namespace local_skillupscertify\credential;

use local_skillupscertify\access;
use moodle_exception;
use stdClass;

/**
 * Validate safe plain-text layouts and expand explicit certificate fields.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design {
    /** @var array Named brand palettes. */
    public const COLORS = ['navy' => [24, 48, 84], 'green' => [22, 91, 72], 'wine' => [108, 44, 71]];

    /** @var array Safe fields available to the wording. */
    public const FIELDNAMES = ['recipient', 'course', 'issuer', 'issuedate', 'completiondate', 'serial'];

    /**
     * Normalize design fields for publishing a revision.
     *
     * @param stdClass $input Form data
     * @return array
     */
    public static function normalize(stdClass $input): array {
        $data = [];
        foreach (['title' => 90, 'issuer' => 130, 'body' => 500, 'signer' => 100] as $field => $length) {
            $value = trim(clean_param($input->$field ?? '', PARAM_TEXT));
            if (($field !== 'signer' && $value === '') || \core_text::strlen($value) > $length) {
                throw new moodle_exception('invaliddesign', access::C);
            }
            $data[$field] = $value;
        }
        preg_match_all('/\{([^{}]+)\}/', $data['body'], $matches);
        if (array_diff($matches[1], self::FIELDNAMES) || !isset(self::COLORS[$input->color ?? ''])) {
            throw new moodle_exception('invaliddesign', access::C);
        }
        if (!in_array($input->orientation ?? '', ['L', 'P'], true)) {
            throw new moodle_exception('invaliddesign', access::C);
        }
        return $data + ['color' => $input->color, 'orientation' => $input->orientation];
    }

    /**
     * Expand variables without recursive interpolation.
     *
     * @param string $body Template text
     * @param array $fields Plain text claims
     * @return string
     */
    public static function expand(string $body, array $fields): string {
        $replace = [];
        foreach (self::FIELDNAMES as $name) {
            $replace['{' . $name . '}'] = $fields[$name];
        }
        return strtr($body, $replace);
    }
}
