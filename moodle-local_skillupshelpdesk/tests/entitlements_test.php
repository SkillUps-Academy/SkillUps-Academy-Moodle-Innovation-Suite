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

namespace local_skillupshelpdesk;

use local_skillupshelpdesk\licensing\entitlements;

/**
 * Verify locally signed feature authorization.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupshelpdesk\licensing\entitlements::class)]
final class entitlements_test extends \advanced_testcase {
    /**
     * A signed annual license unlocks JSON only for an authorized site.
     */
    public function test_signed_entitlement_accepts_authorized_site_and_revocation(): void {
        global $CFG;
        $this->resetAfterTest();
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Optional Sodium extension is not enabled.');
        }
        $keypair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($keypair);
        $CFG->local_skillupshelpdesk_licensepublickey = base64_encode(sodium_crypto_sign_publickey($keypair));
        $payload = json_encode([
            'version' => 1,
            'component' => access::COMPONENT,
            'id' => 'synthetic-subscription',
            'tier' => 'professional',
            'issued' => time() - DAYSECS,
            'expires' => time() + 364 * DAYSECS,
            'sites' => [entitlements::site_id(), hash('sha256', 'https://example.invalid')],
            'features' => ['json_export', 'unsupported'],
        ], JSON_THROW_ON_ERROR);
        $signature = sodium_crypto_sign_detached($payload, $secret);
        $CFG->local_skillupshelpdesk_entitlement = base64_encode($payload) . '.' . base64_encode($signature);
        $this->assertSame('professional', entitlements::status()['state']);
        $this->assertSame(['json_export'], entitlements::status()['features']);
        $CFG->local_skillupshelpdesk_revoked = ['synthetic-subscription'];
        $this->assertSame([], entitlements::status()['features']);
        $CFG->local_skillupshelpdesk_revoked = [];
        $wrongpayload = str_replace('json_export', 'changed', $payload);
        $CFG->local_skillupshelpdesk_entitlement = base64_encode($wrongpayload) . '.' . base64_encode($signature);
        $this->assertSame('community', entitlements::status()['state']);
    }
}
