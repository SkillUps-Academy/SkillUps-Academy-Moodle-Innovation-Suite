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

namespace local_skillupsprivacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_skillupsprivacy\licensing\entitlements;
use local_skillupsprivacy\privacy\provider;

/**
 * Test delegated authority, native data exports and optional entitlements.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(entitlements::class)]
final class authorization_test extends \advanced_testcase {
    /**
     * A delegated officer can work within granted privileges and loses access when the native designation is removed.
     */
    public function test_native_officer_also_needs_explicit_grants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $operator = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        $system = \context_system::instance();
        assign_capability('tool/dataprivacy:managedatarequests', CAP_ALLOW, $role, $system->id);
        assign_capability(access::CAP . 'view', CAP_ALLOW, $role, $system->id);
        assign_capability(access::CAP . 'manage', CAP_ALLOW, $role, $system->id);
        role_assign($role, $operator->id, $system->id);
        $this->assertFalse(access::allowed($operator->id));
        set_config('dporoles', (string)$role, 'tool_dataprivacy');
        $this->assertTrue(access::allowed($operator->id, 'manage'));
        $this->assertFalse(access::allowed($operator->id, 'export'));
        $this->assertFalse(access::allowed($operator->id, 'decide'));
        set_config('dporoles', '', 'tool_dataprivacy');
        $this->assertFalse(access::allowed($operator->id, 'manage'));
    }

    /**
     * Native export includes approved case data and omits other cases and officer identifiers.
     */
    public function test_native_export_is_minimized(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $case = (object)[
            'userid' => $subject->id, 'kind' => 'access', 'ownerid' => get_admin()->id,
            'nativeid' => 0, 'contextid' => 0, 'due' => time() + DAYSECS,
        ];
        $id = workflow::create($case);
        $case->userid = $other->id;
        $otherid = workflow::create($case);
        $system = \context_system::instance();
        $personal = \context_user::instance($subject->id);
        $writer = writer::with_context($system);
        provider::export_user_data(new approved_contextlist($subject, access::C, [$personal->id]));
        $this->assertFalse($writer->has_any_data());
        provider::export_user_data(new approved_contextlist($subject, access::C, [$system->id]));
        $path = [access::text('pluginname'), access::text('view_cases')];
        $data = $writer->get_data([...$path, (string)$id]);
        $this->assertEquals($subject->id, $data->userid);
        $this->assertObjectNotHasProperty('ownerid', $data);
        $this->assertObjectNotHasProperty('createdby', $data);
        $this->assertSame([], $writer->get_data([...$path, (string)$otherid]));
    }

    /**
     * Accept correctly signed site-bound annual claims and honor grace and revocation.
     */
    public function test_signed_entitlements_fail_closed(): void {
        global $CFG;
        $this->resetAfterTest();
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('PHP sodium is unavailable');
        }
        $keys = sodium_crypto_sign_keypair();
        $private = sodium_crypto_sign_secretkey($keys);
        $CFG->local_skillupsprivacy_licensepublickey = base64_encode(sodium_crypto_sign_publickey($keys));
        $claims = [
            'version' => 1, 'component' => access::C, 'id' => 'synthetic-annual',
            'tier' => 'enterprise', 'issued' => time() - DAYSECS, 'expires' => time() + DAYSECS,
            'sites' => [entitlements::site_id()], 'features' => ['extended_evidence'],
        ];
        $sign = static function (array $claims) use ($private): string {
            $payload = json_encode($claims, JSON_THROW_ON_ERROR);
            return base64_encode($payload) . '.' . base64_encode(sodium_crypto_sign_detached($payload, $private));
        };
        $CFG->local_skillupsprivacy_entitlement = $sign($claims);
        $this->assertSame(['extended_evidence'], entitlements::status()['features']);
        $CFG->local_skillupsprivacy_revoked = [$claims['id']];
        $this->assertSame('community', entitlements::status()['state']);
        $CFG->local_skillupsprivacy_revoked = [];
        $CFG->local_skillupsprivacy_entitlement = $sign(array_replace($claims, ['sites' => ['other']]));
        $this->assertSame('community', entitlements::status()['state']);
        $CFG->local_skillupsprivacy_entitlement = $sign(array_replace($claims, ['expires' => time() - HOURSECS]));
        $this->assertSame('expired', entitlements::status()['state']);
        $CFG->local_skillupsprivacy_gracedays = 1;
        $this->assertSame('grace', entitlements::status()['state']);
    }
}
