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
 * Verify webhook authenticity and replay bounds before staging any data.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ingress {
    /** @var int Maximum exact body bytes for REST and webhook. */
    public const LIMIT = 262144;

    /**
     * Verify rotating HMAC credentials and deliver a profile-versioned simulation.
     *
     * @param int $sourceid Integration scope
     * @param string $timestamp Request timestamp
     * @param string $signature HMAC header
     * @param string $body Exact request bytes
     * @return array
     */
    public static function webhook(int $sourceid, string $timestamp, string $signature, string $body): array {
        if (strlen($body) > self::LIMIT) {
            throw new problem('limit');
        }
        if (
            $sourceid <= 0 || !preg_match('/^\d{10}$/D', $timestamp) || abs(time() - (int)$timestamp) > 300 ||
            !preg_match('/^sha256=([a-f0-9]{64})$/D', $signature, $parts)
        ) {
            throw new problem('signature');
        }
        try {
            $source = profile::get($sourceid);
            if (!$source->enabled || !$source->webhook) {
                throw new problem('signature');
            }
            $secrets = vault::read($source->secretref);
            $current = vault::field($secrets, 'webhook');
            $signed = $timestamp . "\n" . $sourceid . "\n" . $body;
            $ok = strlen($current) >= 32 && hash_equals(hash_hmac('sha256', $signed, $current), $parts[1]);
            $previous = $secrets['previouswebhook'] ?? '';
            $until = $secrets['previousuntil'] ?? 0;
            if (!$ok && is_string($previous) && strlen($previous) >= 32 && is_int($until) && $until >= time()) {
                $ok = hash_equals(hash_hmac('sha256', $signed, $previous), $parts[1]);
            }
            if (!$ok) {
                throw new problem('signature');
            }
        } catch (problem $failure) {
            throw new problem('signature');
        }
        return runs::ingest($sourceid, 'webhook', 0, $body, (int)$source->revision);
    }
}
