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

use core_privacy\local\request\approved_contextlist;
use local_skillupsintegration\external\status;
use local_skillupsintegration\external\submit;
use local_skillupsintegration\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Exercise simulated, staged and authorized integration changes through Moodle.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(profile::class)]
#[CoversClass(runs::class)]
#[CoversClass(worker::class)]
#[CoversClass(ingress::class)]
#[CoversClass(target::class)]
#[CoversClass(staging::class)]
#[CoversClass(provider::class)]
#[CoversClass(submit::class)]
#[CoversClass(status::class)]
final class workflow_test extends \advanced_testcase {
    /** @var string Synthetic environment bundle. */
    private const REF = 'SKILLUPS_HUB_WORKFLOW_QA';
    /** @var string Synthetic independent webhook key. */
    private const KEY = 'synthetic-Moodle-test-key-2026-workflow-qa';
    /** @var string Synthetic retiring webhook key. */
    private const PREVIOUS = 'synthetic-retiring-test-key-2026-workflow';

    /**
     * Create a webhook-only integration using a synthetic deployment reference.
     *
     * @param array $changes Optional profile settings
     * @return int
     */
    private function source(array $changes = []): int {
        return profile::save((object)($changes + [
            'id' => 0, 'revision' => 0, 'name' => 'Synthetic source', 'type' => 'inbound', 'enabled' => 1,
            'users' => 1, 'courses' => 0, 'categoryid' => 0, 'pushuserid' => 0, 'webhook' => 1,
            'secretref' => self::REF, 'policy' => 'preserve', 'mapping' => '', 'endpoint' => '',
        ]));
    }

    /**
     * Provide representative canonical identity data.
     *
     * @param string $suffix Identity suffix
     * @param string $first First name
     * @return array
     */
    private function member(string $suffix, string $first = 'Sample'): array {
        return ['id' => $suffix, 'username' => 'synthetic_' . $suffix,
            'firstname' => $first, 'lastname' => 'User', 'email' => $suffix . '@example.invalid'];
    }

    /**
     * Prepare an authorized event with real HMAC processing.
     *
     * @param int $source Source
     * @param string $event Event key
     * @param array $rows Records
     * @param string $key Test signing key
     * @param int $delta Offset in seconds
     * @return array
     */
    private function push(int $source, string $event, array $rows, string $key = self::KEY, int $delta = 0): array {
        $ts = (string)(time() + $delta);
        $body = json_encode(['event' => $event, 'records' => $rows], JSON_THROW_ON_ERROR);
        $hmac = hash_hmac('sha256', $ts . "\n" . $source . "\n" . $body, $key);
        putenv(self::REF . '=' . json_encode([
            'webhook' => self::KEY, 'previouswebhook' => self::PREVIOUS, 'previousuntil' => time() + 60,
        ]));
        try {
            return ingress::webhook($source, $ts, 'sha256=' . $hmac, $body);
        } finally {
            putenv(self::REF);
        }
    }

    /**
     * Preview captures changes and linked records are only created after explicit approval.
     */
    public function test_human_approval_and_idempotency(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $source = $this->source(['courses' => 1, 'categoryid' => $category->id]);
        $rows = [
            ['entity' => 'user', 'fields' => $this->member('approved')],
            ['entity' => 'course', 'fields' => ['id' => 'c1', 'shortname' => 'QA-C1', 'fullname' => 'QA Creative Writing']],
        ];
        $ack = $this->push($source, 'first', $rows, self::PREVIOUS);
        $runid = $ack['runid'];
        $this->assertFalse($ack['replayed']);
        $this->assertSame($runid, $this->push($source, 'first', $rows)['runid']);
        worker::process($runid);
        $this->assertFalse($DB->record_exists('user', ['username' => 'synthetic_approved']));
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'QA-C1']));
        runs::approve($runid, runs::get($runid)->sealed);
        worker::process($runid);
        worker::process($runid);
        $user = $DB->get_record('user', ['username' => 'synthetic_approved'], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['shortname' => 'QA-C1'], '*', MUST_EXIST);
        $this->assertSame('nologin', $user->auth);
        $this->assertEquals(0, $course->visible);
        $this->assertEquals($category->id, $course->category);
        $this->assertSame('done', runs::get($runid)->state);
        $this->assertEquals(2, $DB->count_records(store::LINK));
        $again = $this->push($source, 'second', $rows);
        $this->assertEquals(2, $DB->count_records(store::ITEM, ['runid' => $again['runid'], 'state' => 'same']));
    }

    /**
     * A post-preview identity collision cannot overwrite the native account and a healthy row continues.
     */
    public function test_stale_row_does_not_block_independent_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->source();
        $ack = $this->push($source, 'collision', [
            ['entity' => 'user', 'fields' => $this->member('occupied')],
            ['entity' => 'user', 'fields' => $this->member('independent')],
        ]);
        $this->getDataGenerator()->create_user(['username' => 'synthetic_occupied', 'firstname' => 'Keep']);
        runs::approve($ack['runid'], runs::get($ack['runid'])->sealed);
        worker::process($ack['runid']);
        $this->assertSame('partial', runs::get($ack['runid'])->state);
        $this->assertSame('Keep', $DB->get_field('user', 'firstname', ['username' => 'synthetic_occupied']));
        $this->assertTrue($DB->record_exists('user', ['username' => 'synthetic_independent']));
        $row = $DB->get_record(store::ITEM, ['runid' => $ack['runid'], 'state' => 'conflict'], '*', MUST_EXIST);
        $this->assertSame('collision', $row->errorcode);
    }

    /**
     * Update only managed fields and require a fresh source-preferred preview after local edits.
     */
    public function test_managed_updates_and_conflict_policy(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $scope = ['categoryid' => $category->id, 'courses' => 1];
        $source = $this->source($scope);
        $rows = [
            ['entity' => 'user', 'fields' => $this->member('managed')],
            ['entity' => 'course', 'fields' => ['id' => 'managed', 'shortname' => 'QA-MANAGED', 'fullname' => 'Old title']],
        ];
        $apply = function (string $event) use ($source, &$rows): int {
            $runid = $this->push($source, $event, $rows)['runid'];
            runs::approve($runid, runs::get($runid)->sealed);
            worker::process($runid);
            return $runid;
        };
        $this->assertSame('done', runs::get($apply('created'))->state);
        $rows[0]['fields']['firstname'] = 'Revised';
        $rows[1]['fields']['fullname'] = 'Revised title';
        $this->assertSame('done', runs::get($apply('updated'))->state);
        $user = $DB->get_record('user', ['username' => 'synthetic_managed'], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['shortname' => 'QA-MANAGED'], '*', MUST_EXIST);
        $this->assertSame('Revised', $user->firstname);
        $this->assertSame('Revised title', $course->fullname);
        $this->assertEquals(0, $course->visible);
        $edited = (object)['id' => $user->id, 'firstname' => 'Locally edited'];
        if (method_exists(\core\user::class, 'update_user')) {
            \core\user::update_user($edited, false);
        } else {
            require_once($CFG->dirroot . '/user/lib.php');
            user_update_user($edited, false);
        }
        $rows[0]['fields']['firstname'] = 'New upstream';
        $conflicted = $this->push($source, 'local-edit', $rows)['runid'];
        $this->assertTrue($DB->record_exists(store::ITEM, ['runid' => $conflicted, 'errorcode' => 'localchange']));
        $this->source($scope + ['id' => $source, 'revision' => profile::get($source)->revision, 'policy' => 'source']);
        $this->assertSame('done', runs::get($apply('resolved'))->state);
        $this->assertSame('New upstream', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }

    /**
     * Reusing an event key with different bytes and signed requests outside the window are rejected.
     */
    public function test_signatures_and_mismatched_replays(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->source();
        $record = [['entity' => 'user', 'fields' => $this->member('first')]];
        foreach ([[str_repeat('x', 40), 0], [self::KEY, -301]] as [$key, $offset]) {
            try {
                $this->push($source, 'bad', $record, $key, $offset);
                $this->fail('An invalid signature was accepted');
            } catch (problem $failure) {
                $this->assertSame('signature', $failure->reason);
            }
        }
        $this->push($source, 'valid', $record);
        $this->expectExceptionMessage(access::text('err_replay'));
        $this->push($source, 'valid', [['entity' => 'user', 'fields' => $this->member('second')]]);
    }

    /**
     * Disabling a connector after approval prevents the queued application.
     */
    public function test_configuration_revision_fences_application(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->source();
        $ack = $this->push($source, 'disable', [['entity' => 'user', 'fields' => $this->member('disabled')]]);
        $runid = $ack['runid'];
        runs::approve($runid, runs::get($runid)->sealed);
        $this->source(['id' => $source, 'revision' => profile::get($source)->revision, 'enabled' => 0, 'secretref' => '']);
        worker::process($runid);
        $this->assertSame('superseded', runs::get($runid)->state);
        $this->assertFalse($DB->record_exists('user', ['username' => 'synthetic_disabled']));
        $this->assertSame(self::REF, profile::get($source)->secretref);
    }

    /**
     * A Moodle service account can submit to its assigned connector and cannot approve writes.
     */
    public function test_rest_operator_isolation(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        $system = \context_system::instance();
        assign_capability(access::CAP . 'ingest', CAP_ALLOW, $role, $system->id);
        role_assign($role, $owner->id, $system->id);
        role_assign($role, $other->id, $system->id);
        $source = $this->source(['webhook' => 0, 'pushuserid' => $owner->id]);
        $CFG->wwwroot = 'https://integration.example.invalid';
        $this->setUser($owner);
        $json = json_encode(['event' => 'rest', 'records' => [['entity' => 'user', 'fields' => $this->member('rest')]]]);
        $run = submit::execute($source, $json);
        $this->assertSame('ready', status::execute($run['runid'])['state']);
        $this->assertFalse(access::allowed($owner->id, 'apply'));
        $this->setUser($other);
        $this->expectExceptionMessage(access::text('err_unavailable'));
        status::execute($run['runid']);
    }

    /**
     * Erasure applies to approved plugin evidence and leaves a native user record in place.
     */
    public function test_privacy_erases_only_matching_staged_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $native = $this->getDataGenerator()->create_user(['email' => 'scope@example.invalid']);
        $source = $this->source();
        $run = $this->push($source, 'privacy', [
            ['entity' => 'user', 'fields' => $this->member('scope')],
            ['entity' => 'user', 'fields' => $this->member('other')],
        ]);
        $system = \context_system::instance();
        $userctx = \context_user::instance($native->id);
        provider::delete_data_for_user(new approved_contextlist($native, access::C, [$userctx->id]));
        $this->assertEquals(2, $DB->count_records(store::ITEM, ['runid' => $run['runid']]));
        $this->assertEquals([$system->id], provider::get_contexts_for_userid($native->id)->get_contextids());
        provider::delete_data_for_user(new approved_contextlist($native, access::C, [$system->id]));
        $this->assertEquals(1, $DB->count_records(store::ITEM, ['runid' => $run['runid']]));
        $this->assertTrue($DB->record_exists('user', ['id' => $native->id]));
    }
}
