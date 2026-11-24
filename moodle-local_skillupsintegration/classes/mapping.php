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
 * Map source fields using a small allowlisted transformation contract.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mapping {
    /** @var array Canonical managed fields. */
    public const FIELDS = [
        'user' => ['id', 'username', 'firstname', 'lastname', 'email'],
        'course' => ['id', 'shortname', 'fullname'],
    ];

    /**
     * Validate the editable mapping JSON.
     *
     * @param string $json Mapping specification
     * @return array
     */
    public static function configuration(string $json): array {
        if (trim($json) === '') {
            return [];
        }
        $value = strlen($json) <= 8000 ? json_decode($json, true, 12) : null;
        if (!is_array($value)) {
            throw new problem('mapping');
        }
        foreach ($value as $entity => $fields) {
            if (!isset(self::FIELDS[$entity]) || !is_array($fields)) {
                throw new problem('mapping');
            }
            foreach ($fields as $key => $rule) {
                if (
                    !in_array($key, self::FIELDS[$entity], true) || !is_array($rule) || array_diff_key($rule, [
                    'path' => true, 'transforms' => true,
                    ])
                ) {
                    throw new problem('mapping');
                }
                $path = $rule['path'] ?? null;
                $ops = $rule['transforms'] ?? [];
                if (
                    !is_string($path) || !preg_match('/^[a-zA-Z0-9_]{1,64}(?:\.[a-zA-Z0-9_]{1,64}){0,5}$/D', $path) ||
                    !is_array($ops) || !array_is_list($ops) || count($ops) > 4
                ) {
                    throw new problem('mapping');
                }
                foreach ($ops as $op) {
                    if (!in_array($op, ['trim', 'lower', 'upper', 'collapse'], true)) {
                        throw new problem('mapping');
                    }
                }
            }
        }
        return $value;
    }

    /**
     * Convert one provider record to a safe canonical Moodle object.
     *
     * @param string $entity Type to normalize
     * @param array $raw Remote fields
     * @param array $paths Provider defaults per canonical field
     * @param array $configured Administrator transformations
     * @param int $source Connector ID for generated immutable keys
     * @return array
     */
    public static function normalize(string $entity, array $raw, array $paths, array $configured, int $source): array {
        $values = [];
        foreach (self::FIELDS[$entity] as $field) {
            $rule = $configured[$field] ?? ['path' => $paths[$field] ?? $field];
            $value = $raw;
            foreach (explode('.', $rule['path']) as $part) {
                $value = is_array($value) ? $value[$part] ?? null : null;
            }
            $value = is_int($value) || is_string($value) ? (string)$value : '';
            foreach ($rule['transforms'] ?? [] as $op) {
                $value = match ($op) {
                    'trim' => trim($value),
                    'lower' => \core_text::strtolower($value),
                    'upper' => \core_text::strtoupper($value),
                    'collapse' => preg_replace('/\s+/u', ' ', $value) ?? '',
                };
            }
            $values[$field] = trim($value);
        }
        $id = $values['id'];
        if ($id === '' || strlen($id) > 256 || preg_match('/[\x00-\x1f\x7f]/', $id)) {
            throw new problem('record');
        }
        unset($values['id']);
        $identity = hash('sha256', $id);
        $suffix = 'su_' . $source . '_' . substr($identity, 0, 32);
        if ($entity === 'user') {
            $values['email'] = \core_text::strtolower($values['email']);
            $values['username'] = \core_text::strtolower($values['username'] ?: $suffix);
            if (
                !validate_email($values['email']) || strlen($values['email']) > 100 ||
                clean_param($values['username'], PARAM_USERNAME) !== $values['username'] || strlen($values['username']) > 100
            ) {
                throw new problem('record');
            }
            $fields = ['firstname' => 100, 'lastname' => 100];
        } else {
            $values['shortname'] = $values['shortname'] ?: strtoupper($suffix);
            $fields = ['shortname' => 100, 'fullname' => 254];
        }
        foreach ($fields as $field => $limit) {
            $value = $values[$field];
            if (
                $value === '' || clean_param($value, PARAM_TEXT) !== $value || \core_text::strlen($value) > $limit ||
                preg_match('/[\x00-\x1f\x7f]/', $value)
            ) {
                throw new problem('record');
            }
        }
        return [$identity, $values];
    }
}
