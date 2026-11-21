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

namespace local_skillupscopilot;

use local_skillupscopilot\ai\loopback_security;
use local_skillupscopilot\licensing\entitlements;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Pure helpers and signed entitlement validation.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(sources::class)]
#[CoversClass(quota::class)]
#[CoversClass(entitlements::class)]
#[CoversClass(loopback_security::class)]
final class contracts_test extends \basic_testcase {
    /**
     * Unicode search terms are unique and normalized.
     */
    public function test_lexical_terms_are_unicode_and_bounded(): void {
        $this->assertSame(['álgebra', 'cálculo'], sources::terms('ÁLGEBRA, álgebra, y Cálculo', 2));
        $this->assertSame([], sources::terms('de la y'));
        $this->assertSame(86400, quota::day_start(90000));
    }

    /**
     * A local transport exception is confined to the exact declared endpoint.
     */
    public function test_loopback_exception_is_bound_to_one_url(): void {
        $endpoint = 'http://127.0.0.1:11434/api/chat';
        $security = new loopback_security($endpoint);
        $this->assertFalse($security->url_is_blocked($endpoint));
        $this->assertTrue($security->url_is_blocked('http://127.0.0.1:11434/api/tags'));
        $this->assertTrue($security->url_is_blocked('http://127.0.0.1:80/api/chat'));
        $this->assertTrue($security->url_is_blocked('https://example.invalid/api/chat'));
        $untrusted = new loopback_security('https://example.invalid/api/chat');
        $this->assertTrue($untrusted->url_is_blocked('https://example.invalid/api/chat'));
    }

    /**
     * Signed claims are installation-bound and can be revoked locally.
     */
    public function test_entitlement_is_signed_and_revocable(): void {
        global $CFG;
        $previous = clone $CFG;
        try {
            $pair = sodium_crypto_sign_keypair();
            $CFG->local_skillupscopilot_licensepublickey = base64_encode(sodium_crypto_sign_publickey($pair));
            $now = time();
            $payload = json_encode([
                'version' => 1, 'id' => 'test-entitlement', 'tier' => 'professional',
                'issued' => $now - 10, 'expires' => $now + DAYSECS, 'sites' => [entitlements::site_id()],
                'features' => ['aggregate_export'],
            ]);
            $signature = sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair));
            $CFG->local_skillupscopilot_entitlement = base64_encode($payload) . '.' . base64_encode($signature);
            $this->assertSame(['aggregate_export'], entitlements::status()['features']);
            $CFG->local_skillupscopilot_revoked = ['test-entitlement'];
            $this->assertSame([], entitlements::status()['features']);
            $CFG->local_skillupscopilot_revoked = [];
            $CFG->local_skillupscopilot_entitlement = base64_encode($payload . ' ') . '.' . base64_encode($signature);
            $this->assertSame('community', entitlements::status()['state']);
        } finally {
            foreach (['licensepublickey', 'entitlement', 'revoked'] as $property) {
                $property = 'local_skillupscopilot_' . $property;
                unset($CFG->$property);
                if (isset($previous->$property)) {
                    $CFG->$property = $previous->$property;
                }
            }
        }
    }
}
