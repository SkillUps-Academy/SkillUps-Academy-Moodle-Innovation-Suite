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
 * Read provisioned credential bundles from the host environment.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class vault {
    /**
     * Check an environment reference against the integration namespace.
     *
     * @param string $reference Credential reference
     * @return bool
     */
    public static function valid_reference(string $reference): bool {
        return preg_match('/^SKILLUPS_HUB_[A-Z0-9_]{1,50}$/D', $reference) === 1;
    }

    /**
     * Load current credentials without caching them between requests or emitting their values.
     *
     * @param string $reference Environment reference
     * @return array
     */
    public static function read(string $reference): array {
        if (!self::valid_reference($reference)) {
            throw new problem('credential');
        }
        $value = getenv($reference, true);
        $value = $value === false ? getenv($reference) : $value;
        $data = is_string($value) && strlen($value) <= 16384 ? json_decode($value, true, 8) : null;
        if (!is_array($data)) {
            throw new problem('credential');
        }
        return $data;
    }

    /**
     * Read a required OAuth or HTTP value with header-safe boundaries.
     *
     * @param array $data Credential bundle
     * @param string $key Field
     * @return string
     */
    public static function field(array $data, string $key): string {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > 8192 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new problem('credential');
        }
        return $value;
    }
}
