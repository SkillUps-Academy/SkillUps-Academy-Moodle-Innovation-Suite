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

namespace report_skillupsmonitor;

/**
 * Stateless machine authentication before loading Moodle.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gateway {
    /** Environment name contains a SHA-256 digest, not the bearer itself. */
    public const ENV = 'SKILLUPS_MONITOR_TOKEN_SHA256';

    /**
     * Validate machine credential configuration without revealing it.
     */
    public static function configured(string $hash): bool {
        return (bool)preg_match('/\A[0-9a-f]{64}\z/D', $hash);
    }

    /**
     * Accept an authorization header only and compare its digest in constant time.
     */
    public static function valid(string $header, string $hash): bool {
        if (!self::configured($hash) || !preg_match('/\ABearer ([A-Za-z0-9._~-]{32,256})\z/Di', $header, $parts)) {
            return false;
        }
        return hash_equals($hash, hash('sha256', $parts[1]));
    }

    /**
     * Reject unauthenticated traffic before Moodle bootstraps the database or session.
     */
    public static function authorize(): void {
        $hash = (string)getenv(self::ENV);
        if (!self::secure_transport((string)($_SERVER['HTTPS'] ?? '')) || !self::configured($hash)) {
            self::finish(404);
        }
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
            header('Allow: GET, HEAD');
            self::finish(405);
        }
        if (!self::valid($_SERVER['HTTP_AUTHORIZATION'] ?? '', $hash)) {
            header('WWW-Authenticate: Bearer realm="SkillUps Monitor"');
            self::finish(401);
        }
    }

    /**
     * Trust the web server's HTTPS flag, never a client-supplied forwarding header.
     */
    public static function secure_transport(string $https): bool {
        return in_array(strtolower($https), ['on', '1'], true);
    }

    /**
     * Enforce administrative opt-in and canonical HTTPS once Moodle is configured.
     */
    public static function require_enabled(): void {
        if (!is_https() || !get_config('report_skillupsmonitor', 'scrapeenabled')) {
            self::finish(404);
        }
    }

    /**
     * Write a minimal, non-cacheable, non-session HTTP response.
     */
    public static function finish(int $status, string $body = '', string $type = 'text/plain; charset=utf-8'): never {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: ' . $type);
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo $body;
        }
        exit;
    }
}
