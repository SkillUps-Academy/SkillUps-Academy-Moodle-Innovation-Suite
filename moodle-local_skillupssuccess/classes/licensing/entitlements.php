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

namespace local_skillupssuccess\licensing;

/**
 * Offline verification for optional commercial features.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entitlements {
    /**
     * Build the installation identity from the canonical Moodle URL.
     */
    public static function site_id(): string {
        global $CFG;
        return hash('sha256', rtrim($CFG->wwwroot, '/'));
    }

    /**
     * Return effective entitlement status, caching signature verification within this request.
     */
    public static function status(): array {
        global $CFG;
        $signed = $CFG->local_skillupssuccess_entitlement ?? '';
        $publickey = $CFG->local_skillupssuccess_licensepublickey ?? '';
        $revoked = $CFG->local_skillupssuccess_revoked ?? [];
        $grace = max(0, min(30, (int)($CFG->local_skillupssuccess_gracedays ?? 0))) * DAYSECS;
        $result = ['state' => 'community', 'features' => [], 'expires' => 0];
        if (!function_exists('sodium_crypto_sign_verify_detached') || $signed === '' || strlen($signed) > 32768) {
            return $result;
        }
        [$payload64, $signature64] = array_pad(explode('.', $signed, 2), 2, '');
        $payload = base64_decode($payload64, true);
        $signature = base64_decode($signature64, true);
        $key = base64_decode($publickey, true);
        if (
            $payload === false || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES ||
            $key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        ) {
            return $result;
        }
        static $verified = [];
        $cachekey = hash('sha256', $publickey . '.' . $signed);
        $verified[$cachekey] ??= sodium_crypto_sign_verify_detached($signature, $payload, $key) ?
            (json_decode($payload, true) ?: []) : [];
        $claims = $verified[$cachekey];
        $now = time();
        if (
            !is_array($claims) || ($claims['version'] ?? null) !== 1 ||
            ($claims['component'] ?? null) !== 'local_skillupssuccess' ||
            !is_string($claims['id'] ?? null) || in_array($claims['id'], $revoked, true) ||
            !in_array($claims['tier'] ?? '', ['professional', 'enterprise'], true) ||
            !is_int($claims['issued'] ?? null) || !is_int($claims['expires'] ?? null) ||
            $claims['issued'] > $now || $claims['expires'] <= $claims['issued'] ||
            $claims['expires'] - $claims['issued'] > 366 * DAYSECS ||
            !is_array($claims['sites'] ?? null) || !in_array(self::site_id(), $claims['sites'], true) ||
            !is_array($claims['features'] ?? null)
        ) {
            return $result;
        }
        $expires = $claims['expires'];
        if ($now >= $expires + $grace) {
            return ['state' => 'expired', 'features' => [], 'expires' => $expires];
        }
        return [
            'state' => $now >= $expires ? 'grace' : $claims['tier'],
            'features' => array_values(array_intersect(['aggregate_export'], $claims['features'])),
            'expires' => $expires,
        ];
    }
}
