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

namespace local_skillupsaigrading;

use local_skillupsaigrading\licensing\entitlements;

/**
 * Verify annual signed subscriptions and Community fallback.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entitlements_test extends \advanced_testcase {
    /**
     * Accept an installation-bound entitlement with grace and honor local revocation.
     */
    public function test_signed_feature_grace_and_revocation(): void {
        global $CFG;
        $this->resetAfterTest();
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Sodium is unavailable.');
        }
        $pair = sodium_crypto_sign_keypair();
        $private = sodium_crypto_sign_secretkey($pair);
        $CFG->local_skillupsaigrading_licensepublickey = base64_encode(sodium_crypto_sign_publickey($pair));
        $CFG->local_skillupsaigrading_gracedays = 1;
        $now = time();
        $claims = [
            'component' => access::C, 'version' => 1, 'id' => 'signed-test', 'tier' => 'professional',
            'issued' => $now - DAYSECS, 'expires' => $now - 30, 'sites' => [entitlements::site_id(), str_repeat('a', 64)],
            'features' => ['aggregate_export'],
        ];
        $payload = evaluation::json($claims);
        $signature = sodium_crypto_sign_detached($payload, $private);
        $CFG->local_skillupsaigrading_entitlement = base64_encode($payload) . '.' . base64_encode($signature);
        $this->assertSame('grace', entitlements::status()['state']);
        $this->assertSame(['aggregate_export'], entitlements::status()['features']);
        $CFG->local_skillupsaigrading_gracedays = 0;
        $this->assertSame('expired', entitlements::status()['state']);
        $CFG->local_skillupsaigrading_revoked = [$claims['id']];
        $this->assertSame('community', entitlements::status()['state']);
    }
}
