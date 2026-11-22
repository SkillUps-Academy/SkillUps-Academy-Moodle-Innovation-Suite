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

use core_privacy\local\request\approved_contextlist;
use PHPUnit\Framework\Attributes\CoversClass;
use report_skillupsmonitor\privacy\provider;
use report_skillupsmonitor\task\notify;

/**
 * Operational observations, incident transitions, permissions, and retention.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(availability::class)]
#[CoversClass(collector::class)]
#[CoversClass(diagnostics::class)]
#[CoversClass(gateway::class)]
#[CoversClass(metrics::class)]
#[CoversClass(store::class)]
#[CoversClass(ui::class)]
#[CoversClass(provider::class)]
#[CoversClass(notify::class)]
#[CoversClass(task\purge::class)]
final class monitor_test extends \advanced_testcase {
    /**
     * Write one synthetic database observation with a deterministic check outcome.
     */
    private function observation(int $now, string $state, int $latency = -1): void {
        $check = ['up' => 'ok', 'down' => 'error', 'maintenance' => 'maintenance', 'unknown' => 'unknown'][$state];
        store::record($now, [
            'readiness' => $state, 'latencyus' => $latency, 'checks' => ['readiness' => $check],
        ], util::policy(), store::latest());
    }

    /**
     * Unknown and missing slots cannot open or close a readiness incident.
     */
    public function test_incident_debounce_and_recovery(): void {
        global $DB;
        $this->resetAfterTest();
        $start = store::slot(time()) - 3600;
        $this->observation($start, 'down');
        $this->observation($start + 300, 'unknown');
        $this->observation($start + 600, 'down');
        $this->assertFalse($DB->record_exists(store::INCIDENT, ['checkkey' => 'readiness']));
        $this->observation($start + 900, 'down');
        $incident = $DB->get_record(store::INCIDENT, ['checkkey' => 'readiness'], '*', MUST_EXIST);
        $this->assertEquals($start + 600, $incident->opened);
        $this->assertEquals($start + 900, $incident->detected);

        $this->observation($start + 1200, 'maintenance');
        $this->observation($start + 1500, 'up', 200);
        $this->observation($start + 2400, 'up', 992);
        $this->assertEquals(0, $DB->get_field(store::INCIDENT, 'closed', ['id' => $incident->id]));
        $coverage = $DB->get_record(store::INCIDENT, ['checkkey' => 'coverage'], '*', MUST_EXIST);
        $this->assertEquals($start + 1800, $coverage->opened);
        $this->assertEquals($start + 2400, $coverage->closed);
        $this->observation($start + 2700, 'up', 35);
        $this->assertEquals($start + 2700, $DB->get_field(store::INCIDENT, 'closed', ['id' => $incident->id]));
        $this->assertEquals(1, $DB->count_records(store::INCIDENT, ['checkkey' => 'readiness']));
    }

    /**
     * Availability, coverage, boundary exclusion and nearest-rank latency reconcile with stored observations.
     */
    public function test_report_reconciles_slots(): void {
        $this->resetAfterTest();
        $end = store::slot(time());
        $this->observation($end - 1800, 'up', 120);
        $this->observation($end - 1500, 'up', 720);
        $this->observation($end - 1200, 'down', 5000);
        $this->observation($end - 900, 'maintenance');
        $this->observation($end - 600, 'unknown');
        $this->observation($end, 'up', 90000);
        $report = availability::report($end + 30, 1);
        $this->assertEquals(12, $report['expected']);
        $this->assertEquals(2, $report['up']);
        $this->assertEquals(1, $report['down']);
        $this->assertEquals(1, $report['maintenance']);
        $this->assertEquals(8, $report['unknown']);
        $this->assertEqualsWithDelta(200 / 3, $report['available'], 0.001);
        $this->assertEqualsWithDelta(400 / 12, $report['coverage'], 0.001);
        $this->assertEquals(720, $report['p95us']);
        $this->assertNull(availability::report($end - 3600, 1)['available']);
    }

    /**
     * Cluster lock and unique slot guard keep repeat execution to one observation.
     */
    public function test_real_collection_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('lastcronstart', time(), 'tool_task');
        $this->assertEquals('ok', collector::cron(time(), util::policy())['state']);
        store::sample();
        store::sample();
        $this->assertEquals(1, $DB->count_records(store::SAMPLE));
        $this->assertEquals('up', store::latest()->readiness);
        $this->assertGreaterThanOrEqual(0, store::latest()->latencyus);
        set_config('cron_enabled', 0);
        $this->assertEquals('error', collector::cron(time(), util::policy())['state']);
    }

    /**
     * An ordinary account cannot view site operational information.
     */
    public function test_report_access_requires_system_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        ui::access();
    }

    /**
     * Viewing the monitor does not grant Core logs or acknowledgement.
     */
    public function test_monitor_access_does_not_grant_core_error_access(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        $context = \context_system::instance();
        assign_capability(util::CAP . ':view', CAP_ALLOW, $role, $context);
        role_assign($role, $user->id, $context->id);
        $this->setUser($user);
        $this->assertEquals($context->id, ui::access()->id);
        $this->assertFalse(has_capability(util::CAP . ':manage', $context));
        $this->expectException(\required_capability_exception::class);
        diagnostics::task_errors();
    }

    /**
     * Reading the report does not open the Moodle event log API.
     */
    public function test_error_events_require_native_log_permission(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        diagnostics::events();
    }

    /**
     * Pending notifications must pass authorization again after a role is removed.
     */
    public function test_delivery_revalidates_permissions_and_avoids_duplicates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $recipient = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        $context = \context_system::instance();
        assign_capability(util::CAP . ':view', CAP_ALLOW, $role, $context);
        role_assign($role, $recipient->id, $context->id);
        set_config('recipients', get_admin()->id . ',' . $recipient->id, util::C);
        set_config('notifyenabled', 1, util::C);
        set_config('alertchecks', 'readiness', util::C);
        $start = store::slot(time()) - 900;
        $this->observation($start, 'down');
        $this->observation($start + 300, 'down');

        role_unassign($role, $recipient->id, $context->id);
        $sink = $this->redirectMessages();
        (new notify())->execute();
        (new notify())->execute();
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
        $this->assertEquals('sent', $DB->get_field(store::NOTICE, 'state', ['userid' => get_admin()->id]));
        $this->assertEquals('skipped', $DB->get_field(store::NOTICE, 'state', ['userid' => $recipient->id]));
    }

    /**
     * Review attribution can be removed without deleting the operational incident.
     */
    public function test_privacy_erasure_preserves_operational_review_time(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();
        set_config('recipients', (string)$admin->id, util::C);
        $start = store::slot(time()) - 900;
        $this->observation($start, 'down');
        $this->observation($start + 300, 'down');
        $id = $DB->get_field(store::INCIDENT, 'id', ['checkkey' => 'readiness'], MUST_EXIST);
        store::acknowledge($id);
        $context = \context_system::instance();
        $contextids = array_map('intval', provider::get_contexts_for_userid($admin->id)->get_contextids());
        $this->assertContains((int)$context->id, $contextids);
        provider::delete_data_for_user(new approved_contextlist($admin, util::C, [$context->id]));
        $row = $DB->get_record(store::INCIDENT, ['id' => $id], '*', MUST_EXIST);
        $this->assertEquals(0, $row->ackuserid);
        $this->assertGreaterThan(0, $row->timeack);
        $this->assertEmpty(util::configured_recipients());
    }

    /**
     * Retention removes expired evidence and preserves unresolved incidents.
     */
    public function test_retention_preserves_open_incidents(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retention', 30, util::C);
        $start = store::slot(time()) - 32 * DAYSECS;
        foreach (['down', 'down', 'up', 'up', 'down', 'down'] as $i => $state) {
            $this->observation($start + $i * 300, $state);
        }
        (new task\purge())->execute();
        $this->assertEquals(0, $DB->count_records(store::SAMPLE));
        $this->assertEquals(1, $DB->count_records(store::INCIDENT));
        $this->assertTrue($DB->record_exists(store::INCIDENT, ['checkkey' => 'readiness', 'closed' => 0]));
    }

    /**
     * Monitor machine access enforces digest matching and explicitly encodes unknown values.
     */
    public function test_machine_access_and_no_data_encoding(): void {
        $token = str_repeat('z', 48);
        $hash = hash('sha256', $token);
        $this->assertTrue(gateway::valid('Bearer ' . $token, $hash));
        $this->assertFalse(gateway::valid('Bearer ' . $token . 'z', $hash));
        $this->assertFalse(gateway::valid('Bearer ' . $token, strtoupper($hash)));
        $this->assertFalse(gateway::valid('Basic ' . $token, $hash));
        $this->assertFalse(gateway::secure_transport('off'));
        $this->assertTrue(gateway::secure_transport('on'));
        $body = metrics::render(null, time(), ['stale' => 900], ['last' => 0, 'age' => null, 'state' => 'unknown']);
        $this->assertStringContainsString("skillups_moodle_sample_fresh 0\n", $body);
        $this->assertStringContainsString("skillups_moodle_readiness NaN\n", $body);
        $this->assertStringContainsString("skillups_moodle_cron_healthy NaN\n", $body);
        $this->assertStringNotContainsString($token, $body);
    }
}
