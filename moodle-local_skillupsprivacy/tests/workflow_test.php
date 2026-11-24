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
use local_skillupsprivacy\privacy\provider;
use stdClass;
use tool_dataprivacy\api;

/**
 * Test case decisions, permissions, Moodle isolation and deadline processing.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsprivacy\workflow::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsprivacy\bridge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsprivacy\access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsprivacy\monitor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsprivacy\dashboard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsprivacy\privacy\provider::class)]
final class workflow_test extends \advanced_testcase {
    /**
     * Build a synthetic intake record.
     *
     * @param int $userid Subject
     * @param string $kind Case category
     * @param int $nativeid Native request
     * @param int $contextid Scope to assess
     * @return stdClass
     */
    private function intake(int $userid, string $kind = 'access', int $nativeid = 0, int $contextid = 0): stdClass {
        return (object)[
            'userid' => $userid, 'kind' => $kind, 'ownerid' => get_admin()->id,
            'nativeid' => $nativeid, 'contextid' => $contextid, 'due' => time() + DAYSECS,
        ];
    }

    /**
     * Internal decisions leave native state, files and user data untouched.
     */
    public function test_native_approval_stays_separate(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $subject = $this->getDataGenerator()->create_user();
        $native = api::create_data_request($subject->id, api::DATAREQUEST_TYPE_EXPORT, notify: false);
        $reference = (int)$native->get('id');
        $status = $native->get('status');
        $id = workflow::create($this->intake($subject->id, 'export', $reference));
        workflow::transition($id, 1, 'review', 'Verify scope');
        workflow::transition($id, 2, 'approved', 'Refer to Moodle');
        $this->assertSame($status, api::get_request($reference)->get('status'));
        [$inbox, $total] = bridge::inbox(0);
        $this->assertSame(1, $total);
        $this->assertSame($reference, reset($inbox)['id']);

        try {
            workflow::transition($id, 3, 'closed', 'Still waiting');
            $this->fail('An unfinished Moodle request was closed');
        } catch (\moodle_exception $error) {
            $this->assertSame('nativepending', $error->errorcode);
        }
        api::update_request_status($reference, api::DATAREQUEST_STATUS_DOWNLOAD_READY);
        monitor::refresh($id);
        $this->assertEquals(api::DATAREQUEST_STATUS_DOWNLOAD_READY, store::get($id)->nativestatus);
        workflow::transition($id, 3, 'closed', 'Moodle finished preparing the export');
        $this->assertSame('closed', store::get($id)->state);
    }

    /**
     * The linked request must match both user and category, and may be tracked once.
     */
    public function test_link_cannot_cross_subjects_or_categories(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $native = api::create_data_request($subject->id, api::DATAREQUEST_TYPE_EXPORT, notify: false);
        $reference = (int)$native->get('id');
        $this->assertNull(bridge::read($reference + 1000));
        foreach ([$this->intake($other->id, 'export', $reference), $this->intake($subject->id, 'access', $reference)] as $record) {
            try {
                workflow::create($record);
                $this->fail('An invalid reference was linked');
            } catch (\moodle_exception $error) {
                $this->assertSame('nativeinvalid', $error->errorcode);
            }
        }
        workflow::create($this->intake($subject->id, 'export', $reference));
        $this->expectExceptionMessage(access::text('nativelinked'));
        workflow::create($this->intake($subject->id, 'export', $reference));
    }

    /**
     * Both destructive types need clearance and a new hold reopens an approved case.
     */
    public function test_retention_gate_and_reopening(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $subject = $this->getDataGenerator()->create_user();
        foreach (['delete', 'anonymize'] as $kind) {
            $id = workflow::create($this->intake($subject->id, $kind));
            workflow::transition($id, 1, 'review', 'Verify conservation');
            try {
                workflow::transition($id, 2, 'approved', 'Premature');
                $this->fail('Destructive referral was approved without a retention assessment');
            } catch (\moodle_exception $error) {
                $this->assertSame('retentionrequired', $error->errorcode);
            }
            workflow::assess($id, 2, 'cleared', 'Reviewed institutional schedule');
            workflow::transition($id, 3, 'approved', 'Refer internally');
            workflow::assess($id, 4, 'held', 'New retention obligation');
            $this->assertSame('review', store::get($id)->state);
            $this->assertSame('held', store::get($id)->retention);
            $this->assertNotFalse(\core_user::get_user($subject->id));
        }
    }

    /**
     * A protected purpose from Moodle cannot be cleared by a local decision.
     */
    public function test_protected_context_blocks_clearance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $subject = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        $purpose = api::create_purpose((object)[
            'name' => 'Protected fixture', 'retentionperiod' => 'P7Y', 'lawfulbases' => 'gdpr_art_6_1_c', 'protected' => 1,
        ]);
        $category = api::create_category((object)['name' => 'Synthetic course data']);
        $defaultpurpose = api::create_purpose((object)[
            'name' => 'Site default', 'retentionperiod' => 'P1Y', 'lawfulbases' => 'gdpr_art_6_1_c',
        ]);
        api::set_contextlevel((object)[
            'contextlevel' => CONTEXT_SYSTEM, 'purposeid' => $defaultpurpose->get('id'), 'categoryid' => $category->get('id'),
        ]);
        api::set_context_instance((object)[
            'contextid' => $context->id, 'purposeid' => $purpose->get('id'), 'categoryid' => $category->get('id'),
        ]);
        $id = workflow::create($this->intake($subject->id, 'delete', 0, $context->id));
        $this->assertTrue(bridge::retention($context)['protected']);
        $this->expectExceptionMessage(access::text('protected'));
        workflow::assess($id, 1, 'cleared', 'Wrongly bypass a protected purpose');
    }

    /**
     * A stale form cannot overwrite the winner, and course users have no system access.
     */
    public function test_stale_revision_and_privileges(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $subject = $this->getDataGenerator()->create_user();
        $this->assertFalse(access::allowed($subject->id, 'manage'));
        $id = workflow::create($this->intake($subject->id));
        workflow::route($id, 1, get_admin()->id, time() + 2 * DAYSECS);
        $log = $DB->get_record(store::AUDIT, ['caseid' => $id, 'operation' => 'routed'], '*', MUST_EXIST);
        $this->assertEquals(get_admin()->id, $log->targetid);
        try {
            workflow::transition($id, 1, 'review', 'Stale update');
            $this->fail('An outdated form was accepted');
        } catch (\moodle_exception $error) {
            $this->assertSame('stale', $error->errorcode);
        }
        $this->setUser($subject);
        $this->expectExceptionMessage(access::text('restricted'));
        workflow::transition($id, 2, 'review', 'Cross-user attempt');
    }

    /**
     * Moodle-approved provider erasure removes identifying data only at the approved scope.
     */
    public function test_provider_context_isolation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $id = workflow::create($this->intake($subject->id));
        $otherid = workflow::create($this->intake($other->id));
        workflow::transition($id, 1, 'review', 'Private case note');
        $system = \context_system::instance()->id;
        $personal = \context_user::instance($subject->id)->id;
        $this->assertEquals([$system], array_values(provider::get_contexts_for_userid($subject->id)->get_contextids()));
        provider::delete_data_for_user(new approved_contextlist($subject, access::C, [$personal]));
        $this->assertEquals($subject->id, store::get($id)->userid);
        provider::delete_data_for_user(new approved_contextlist($subject, access::C, [$system]));
        $case = store::get($id);
        $this->assertEquals(0, $case->userid);
        $this->assertEquals(1, $case->archived);
        $this->assertFalse($DB->record_exists(store::AUDIT, ['caseid' => $id]));
        $this->assertEquals($other->id, store::get($otherid)->userid);
        $this->assertSame([], provider::get_contexts_for_userid($subject->id)->get_contextids());
    }

    /**
     * Deadline processing claims a reminder only once and records the result.
     */
    public function test_deadline_delivery_deduplicates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('notifyenabled', 1, access::C);
        $subject = $this->getDataGenerator()->create_user();
        $record = $this->intake($subject->id);
        $record->due = time() - DAYSECS;
        $id = workflow::create($record);
        $sink = $this->redirectMessages();
        monitor::run();
        monitor::run();
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertEquals(1, $DB->count_records(store::NOTICE, ['caseid' => $id, 'level' => 2]));
        $this->assertEquals(1, $DB->count_records(store::AUDIT, ['caseid' => $id, 'operation' => 'notified']));
    }

    /**
     * Paginated reports and totals agree on a multi-page register.
     */
    public function test_register_paginates_and_reconciles_counts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $subject = $this->getDataGenerator()->create_user();
        $id = workflow::create($this->intake($subject->id));
        $seed = store::get($id);
        unset($seed->id);
        $DB->insert_records(store::CASES, array_fill(0, 1999, $seed));
        [$rows, $total] = dashboard::cases(['kind' => 'access'], 79);
        $this->assertCount(25, $rows);
        $this->assertSame(2000, $total);
        $this->assertSame(2000, dashboard::statistics()['summary'][0]['count']);
    }
}
