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

namespace report_skillupssecurity;

use PHPUnit\Framework\Attributes\CoversClass;
use report_skillupssecurity\control\accounts;
use report_skillupssecurity\control\configuration;
use report_skillupssecurity\control\privileges;
use report_skillupssecurity\privacy\provider;

/**
 * Validate scope, evidence transitions, notification authorization and event minimization.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(engine::class)]
#[CoversClass(store::class)]
#[CoversClass(observer::class)]
#[CoversClass(alerts::class)]
#[CoversClass(configuration::class)]
#[CoversClass(accounts::class)]
#[CoversClass(privileges::class)]
#[CoversClass(provider::class)]
final class security_test extends \advanced_testcase {
    /**
     * Preserve analyst disposition while evidence persists and reopen recurring evidence.
     */
    public function test_finding_lifecycle(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->wwwroot = 'http://audit.example.invalid';
        engine::scan();
        $item = $DB->get_record(store::FINDING, ['ruleid' => 'https'], '*', MUST_EXIST);
        $this->assertSame('risk', $item->kind);
        $this->assertSame('high', $item->severity);
        store::review($item->id, 'accepted', 'Synthetic policy acceptance');
        engine::scan();
        $this->assertSame('accepted', $DB->get_field(store::FINDING, 'reviewstate', ['id' => $item->id]));
        $CFG->wwwroot = 'https://audit.example.invalid';
        engine::scan();
        $this->assertEquals(0, $DB->get_field(store::FINDING, 'present', ['id' => $item->id]));
        $CFG->wwwroot = 'http://audit.example.invalid';
        engine::scan();
        $this->assertSame('new', $DB->get_field(store::FINDING, 'reviewstate', ['id' => $item->id]));
        $this->assertTrue($DB->record_exists(store::HISTORY, ['findingid' => $item->id, 'action' => 'recurred']));
    }

    /**
     * Inventory actual native course permissions and identify dormant privileged accounts.
     */
    public function test_course_privilege_inventory(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->getDataGenerator();
        $course = $data->create_course();
        $scope = \context_course::instance($course->id);
        $role = create_role('Security fixture', 'securityfixture', '');
        assign_capability('moodle/course:delete', CAP_ALLOW, $role, $scope->id);
        $person = $data->create_user(['lastaccess' => time() - 120 * DAYSECS]);
        role_assign($role, $person->id, $scope->id);
        $scan = engine::scan();
        $row = $DB->get_record(store::PRIVILEGE, ['scanid' => $scan, 'userid' => $person->id], '*', MUST_EXIST);
        $this->assertEquals($scope->id, $row->contextid);
        $this->assertSame(['moodle/course:delete'], json_decode($row->capabilities, true));
        $this->assertTrue($DB->record_exists(store::FINDING, ['ruleid' => 'inactive', 'userid' => $person->id]));
        $this->assertTrue($DB->record_exists(store::FINDING, ['ruleid' => 'privilegedinactive', 'userid' => $person->id]));
        assign_capability('moodle/course:delete', CAP_PROHIBIT, $role, $scope->id, true);
        $scan = engine::scan();
        $this->assertFalse($DB->record_exists(store::PRIVILEGE, ['scanid' => $scan, 'userid' => $person->id]));
    }

    /**
     * Persist allowlisted setting labels and remove payload values.
     */
    public function test_native_administrative_event_is_minimized(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $activity = store::ACTIVITY;
        $baseline = (int)$DB->get_field_sql("SELECT MAX(id) FROM {{$activity}}");
        add_to_config_log('forcelogin', 'sensitive-before', 'sensitive-after', '');
        add_to_config_log('password_secret_sample', 'hidden-before', 'hidden-after', '');
        $observed = $DB->get_records_select(store::ACTIVITY, 'action = :action AND id > :baseline', [
            'action' => 'configuration', 'baseline' => $baseline,
        ]);
        $this->assertCount(1, $observed);
        $row = reset($observed);
        $this->assertSame('forcelogin', $row->detail);
        $this->assertStringNotContainsString('sensitive', json_encode($row, JSON_THROW_ON_ERROR));
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $role = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        role_assign($role, $user->id, \context_course::instance($course->id));
        $this->assertTrue($DB->record_exists(store::ACTIVITY, ['action' => 'roleassigned', 'subjectid' => $user->id]));
    }

    /**
     * Deduplicate new alert plans and omit unauthorized configured accounts.
     */
    public function test_outbox_scoped_and_deduplicated(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $outsider = $this->getDataGenerator()->create_user();
        set_config('notifyenabled', 1, access::C);
        set_config('notifyseverity', 'high', access::C);
        set_config('recipients', $USER->id . ',' . $USER->id . ',' . $outsider->id, access::C);
        $CFG->wwwroot = 'http://audit.example.invalid';
        $first = engine::scan();
        $this->assertEquals(1, $DB->count_records(store::OUTBOX, ['scanid' => $first]));
        $this->assertFalse($DB->record_exists(store::OUTBOX, ['userid' => $outsider->id]));
        $second = engine::scan();
        $this->assertFalse($DB->record_exists(store::OUTBOX, ['scanid' => $second]));
    }

    /**
     * Recheck current inventory permission before delivering an alert about identifiable evidence.
     */
    public function test_personal_notification_rechecks_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $scope = \context_system::instance();
        $role = create_role('Alert fixture', 'alertfixture', '');
        $recipient = $this->getDataGenerator()->create_user();
        assign_capability(access::CAP . 'view', CAP_ALLOW, $role, $scope->id);
        assign_capability(access::CAP . 'inventory', CAP_ALLOW, $role, $scope->id);
        role_assign($role, $recipient->id, $scope);
        set_config('notifyenabled', 1, access::C);
        set_config('recipients', '0' . $recipient->id . ',' . $recipient->id, access::C);
        $scan = engine::scan();
        $DB->delete_records(store::OUTBOX, ['scanid' => $scan]);
        alerts::plan($scan, time(), [['userid' => $recipient->id, 'severity' => 'high']]);
        $this->assertEquals(1, $DB->count_records(store::OUTBOX));
        assign_capability(access::CAP . 'inventory', CAP_PROHIBIT, $role, $scope->id, true);
        alerts::dispatch();
        $this->assertSame('skipped', $DB->get_field(store::OUTBOX, 'state', ['scanid' => $scan]));
    }

    /**
     * Erase subject evidence only in the approved system context.
     */
    public function test_system_privacy_erasure(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->getDataGenerator();
        $first = $data->create_user(['lastaccess' => time() - 120 * DAYSECS]);
        $other = $data->create_user(['lastaccess' => time() - 120 * DAYSECS]);
        engine::scan();
        $scope = \context_system::instance();
        $this->assertEquals([$scope->id], provider::get_contexts_for_userid($first->id)->get_contextids());
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist($first, access::C, [$scope->id]));
        $this->assertFalse($DB->record_exists(store::FINDING, ['userid' => $first->id]));
        $this->assertTrue($DB->record_exists(store::FINDING, ['userid' => $other->id]));
    }

    /**
     * Site view does not grant access to individually identified evidence.
     */
    public function test_view_only_role_cannot_read_inventory(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $role = create_role('Auditor fixture', 'auditorfixture', '');
        $scope = \context_system::instance();
        assign_capability(access::CAP . 'view', CAP_ALLOW, $role, $scope->id);
        $user = $this->getDataGenerator()->create_user();
        role_assign($role, $user->id, $scope);
        $this->setUser($user);
        $this->assertTrue(access::can('view'));
        $this->assertFalse(access::can('export'));
        $this->expectException(\required_capability_exception::class);
        access::require('inventory');
    }
}
