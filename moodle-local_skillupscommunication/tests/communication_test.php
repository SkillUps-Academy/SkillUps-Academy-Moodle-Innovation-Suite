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

namespace local_skillupscommunication;

use advanced_testcase;
use local_skillupscommunication\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Exercise audience isolation, frozen approvals and idempotent background handoff.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access::class)]
#[CoversClass(audience::class)]
#[CoversClass(campaign::class)]
#[CoversClass(catalog::class)]
#[CoversClass(dispatch::class)]
#[CoversClass(transport::class)]
#[CoversClass(provider::class)]
final class communication_test extends advanced_testcase {
    /**
     * Prepare two unrelated institutional scopes and a native group.
     *
     * @return array Scoped synthetic Moodle data
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $outside = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id, 'groupmode' => SEPARATEGROUPS]);
        $other = $generator->create_course(['category' => $outside->id]);
        $learner = $generator->create_and_enrol($course, 'student');
        $peer = $generator->create_and_enrol($course, 'student');
        $foreign = $generator->create_and_enrol($other, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        groups_add_member($group, $learner);
        return [\context_coursecat::instance($category->id), $course, $learner, $peer, $foreign, $group, $other];
    }

    /**
     * Prepare ordinary authoring fields.
     *
     * @param array $overrides Test-specific fields
     * @return \stdClass Form data
     */
    private function input(array $overrides = []): \stdClass {
        return (object)($overrides + [
            'subject' => 'Synthetic reminder', 'body' => 'Complete the Moodle activity by Friday.',
            'kind' => 'reminder', 'channel' => 'webnotice', 'sendat' => 0, 'revision' => 0,
        ]);
    }

    /**
     * Create, freeze and approve one scoped campaign.
     *
     * @param \context $scope Moodle scope
     * @param array $selection Authoring inputs
     * @return \stdClass Approved campaign
     */
    private function queue(\context $scope, array $selection): \stdClass {
        global $USER;
        $id = campaign::save($scope, 0, $this->input($selection));
        $record = store::get(store::CAMP, $id);
        $ids = audience::ids($scope, campaign::filters($record), (int)$USER->id);
        campaign::change($id, 1, 'submit', audience::fingerprint($ids, 1));
        campaign::change($id, 2, 'approve');
        return store::get(store::CAMP, $id);
    }

    /**
     * Category, group and role selection intersect without crossing authorization boundaries.
     */
    public function test_scoped_course_group_and_role(): void {
        global $USER, $DB;
        [$scope, $course, $learner, $peer, , $group, $other] = $this->fixture();
        $role = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $ids = audience::ids($scope, ['courseid' => $course->id, 'groupid' => $group->id, 'roleid' => $role], (int)$USER->id);
        $this->assertSame([(int)$learner->id], $ids);
        $this->assertEqualsCanonicalizing([$learner->id, $peer->id], audience::ids($scope, ['roleid' => $role], (int)$USER->id));
        $this->expectExceptionMessage(ui::t('unavailable'));
        audience::ids($scope, ['courseid' => $other->id], (int)$USER->id);
    }

    /**
     * Named program, campus, cohort and explicit-set criteria remain bounded by their category.
     */
    public function test_institutional_mappings_and_explicit_accounts(): void {
        global $USER, $DB;
        [$scope, , $learner, $peer, $foreign] = $this->fixture();
        $child = $this->getDataGenerator()->create_category(['parent' => $scope->instanceid]);
        $nested = \context_coursecat::instance($child->id);
        catalog::save($scope, 'segment', 0, (object)[
            'name' => 'Campus North', 'segmentkind' => 'campus', 'targetcontext' => $scope->id,
        ]);
        catalog::save($scope, 'segment', 0, (object)[
            'name' => 'Education', 'segmentkind' => 'program', 'targetcontext' => $nested->id,
        ]);
        catalog::save($scope, 'segment', 0, (object)[
            'name' => 'Reviewers', 'segmentkind' => 'set', 'usernames' => $learner->username,
        ]);
        $course = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        $cohort = $this->getDataGenerator()->create_cohort(['contextid' => $scope->id]);
        cohort_add_member($cohort->id, $learner->id);
        cohort_add_member($cohort->id, $peer->id);
        $selector = ['cohortid' => $cohort->id];
        foreach (['campus', 'program', 'set'] as $kind) {
            $selector[$kind . 'id'] = $DB->get_field(store::SEG, 'id', ['kind' => $kind]);
        }
        $this->assertSame([(int)$learner->id], audience::ids($scope, $selector, (int)$USER->id));
        $this->expectExceptionMessage(ui::t('invalidmembers'));
        catalog::save($scope, 'segment', 0, (object)[
            'name' => 'Foreign', 'segmentkind' => 'set', 'usernames' => $foreign->username,
        ]);
    }

    /**
     * Group restrictions remain enforced for a category coordinator without all-group access.
     */
    public function test_coordinator_cannot_target_other_group(): void {
        [$scope, $course, , , , $group] = $this->fixture();
        $gen = $this->getDataGenerator();
        $actor = $gen->create_user();
        $role = $gen->create_role();
        assign_capability(access::CAP . 'view', CAP_ALLOW, $role, $scope);
        assign_capability(access::CAP . 'compose', CAP_ALLOW, $role, $scope);
        role_assign($role, $actor->id, $scope);
        $this->setUser($actor);
        $this->assertTrue(access::can($scope, 'compose'));
        $this->assertFalse(access::can($scope, 'approve'));
        $this->expectExceptionMessage(ui::t('invalidgroup'));
        audience::ids($scope, ['courseid' => $course->id, 'groupid' => $group->id], (int)$actor->id);
    }

    /**
     * A changed audience cannot be approved with a stale snapshot.
     */
    public function test_approval_requires_exact_preview_audience(): void {
        global $DB, $USER;
        [$scope, $course, $learner, $peer, , $group] = $this->fixture();
        $id = campaign::save($scope, 0, $this->input(['courseid' => $course->id, 'groupid' => $group->id]));
        $ids = audience::ids($scope, ['courseid' => $course->id, 'groupid' => $group->id], (int)$USER->id);
        campaign::change($id, 1, 'submit', audience::fingerprint($ids, 1));
        groups_add_member($group, $peer);
        try {
            campaign::change($id, 2, 'approve');
            $this->fail('Reviewer approved a changed audience');
        } catch (\moodle_exception $error) {
            $this->assertSame('stale', $error->errorcode);
        }
        $this->assertSame('review', store::get(store::CAMP, $id)->state);
        $this->assertEquals($learner->id, $DB->get_field(store::REC, 'userid', ['campaignid' => $id]));
    }

    /**
     * Moodle Messaging accepts only eligible rows and a repeated wave does not send twice.
     */
    public function test_native_delivery_wave_is_idempotent(): void {
        global $DB;
        [$scope, $course, $learner, $peer] = $this->fixture();
        $job = $this->queue($scope, ['courseid' => $course->id, 'channel' => 'emailnotice']);
        $DB->set_field('user', 'suspended', 1, ['id' => $peer->id]);
        $sink = $this->redirectMessages();
        dispatch::run($job->id, $job->wave);
        dispatch::run($job->id, $job->wave);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertEquals($learner->id, $messages[0]->useridto);
        $this->assertSame('emailnotice', $messages[0]->eventtype);
        $this->assertSame('accepted', $DB->get_field(store::REC, 'state', ['campaignid' => $job->id, 'userid' => $learner->id]));
        $this->assertSame('skipped', $DB->get_field(store::REC, 'state', ['campaignid' => $job->id, 'userid' => $peer->id]));
        $this->assertSame('complete', store::get(store::CAMP, $job->id)->state);
    }

    /**
     * Future schedules and cancellation keep Moodle from handing off a message.
     */
    public function test_future_schedule_and_cancel(): void {
        global $DB;
        [$scope, $course, , , , $group] = $this->fixture();
        $job = $this->queue($scope, ['courseid' => $course->id, 'groupid' => $group->id, 'sendat' => time() + HOURSECS]);
        $sink = $this->redirectMessages();
        dispatch::run($job->id, $job->wave);
        $this->assertSame('queued', store::get(store::CAMP, $job->id)->state);
        campaign::change($job->id, $job->revision, 'cancel');
        dispatch::run($job->id, $job->wave);
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
        $this->assertSame('cancelled', $DB->get_field(store::REC, 'state', ['campaignid' => $job->id]));
    }

    /**
     * Known no-handoff outcomes retry, and an interrupted handoff stays uncertain.
     */
    public function test_retry_and_interrupted_handoff(): void {
        global $DB;
        [$scope, $course, $learner, $peer] = $this->fixture();
        $job = $this->queue($scope, ['courseid' => $course->id]);
        $DB->set_field(store::REC, 'state', 'sending', ['campaignid' => $job->id, 'userid' => $peer->id]);
        $calls = 0;
        $sender = static function () use (&$calls): array {
            $calls++;
            return ['retry', 0, 'moodlerejected'];
        };
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $job = store::get(store::CAMP, $job->id);
            dispatch::run($job->id, $job->wave, $sender);
            $DB->set_field(store::REC, 'nexttry', 0, ['campaignid' => $job->id, 'state' => 'pending']);
        }
        $this->assertSame(3, $calls);
        $this->assertSame('failed', $DB->get_field(store::REC, 'state', ['campaignid' => $job->id, 'userid' => $learner->id]));
        $this->assertSame('uncertain', $DB->get_field(store::REC, 'state', ['campaignid' => $job->id, 'userid' => $peer->id]));
        $job = store::get(store::CAMP, $job->id);
        campaign::change($job->id, $job->revision, 'retry');
        $job = store::get(store::CAMP, $job->id);
        dispatch::run($job->id, $job->wave, static fn() => ['accepted', 7, '']);
        $this->assertSame('accepted', $DB->get_field(store::REC, 'state', ['campaignid' => $job->id, 'userid' => $learner->id]));
        $this->assertSame('uncertain', $DB->get_field(store::REC, 'state', ['campaignid' => $job->id, 'userid' => $peer->id]));
    }

    /**
     * A prohibited descendant remains excluded from a category-wide audience.
     */
    public function test_descendant_permission_override(): void {
        [$scope, , $learner] = $this->fixture();
        $gen = $this->getDataGenerator();
        $branch = $gen->create_category(['parent' => $scope->instanceid]);
        $childscope = \context_coursecat::instance($branch->id);
        $course = $gen->create_course(['category' => $branch->id]);
        $blocked = $gen->create_and_enrol($course, 'student');
        $actor = $gen->create_user();
        $role = $gen->create_role();
        assign_capability(access::CAP . 'view', CAP_ALLOW, $role, $scope);
        assign_capability(access::CAP . 'view', CAP_PROHIBIT, $role, $childscope);
        role_assign($role, $actor->id, $scope);
        $this->setUser($actor);
        $selection = audience::ids($scope, [], $actor->id);
        $this->assertContains((int)$learner->id, $selection);
        $this->assertNotContains((int)$blocked->id, $selection);
    }

    /**
     * A one-recipient batch leaves a newer wave while ignoring the old wave.
     */
    public function test_bounded_batch_progression(): void {
        global $DB;
        [$scope, $course] = $this->fixture();
        set_config('batchsize', 1, access::C);
        $job = $this->queue($scope, ['courseid' => $course->id]);
        $calls = 0;
        $sender = static function () use (&$calls): array {
            $calls++;
            return ['accepted', $calls, ''];
        };
        dispatch::run($job->id, $job->wave, $sender);
        dispatch::run($job->id, $job->wave, $sender);
        $this->assertSame(1, $calls);
        $fresh = store::get(store::CAMP, $job->id);
        $this->assertGreaterThan($job->wave, $fresh->wave);
        dispatch::run($fresh->id, $fresh->wave, $sender);
        $this->assertSame(2, $calls);
        $this->assertEquals(2, $DB->count_records(store::REC, ['campaignid' => $job->id, 'state' => 'accepted']));
    }

    /**
     * External connector evidence is used only while a class is explicitly authorized.
     */
    public function test_authorized_connector_contract(): void {
        [$scope, , $learner] = $this->fixture();
        require_once(__DIR__ . '/fixtures/verified_connector.php');
        $class = verified_connector::class;
        $channel = 'external' . hash('sha256', $class);
        $this->assertArrayNotHasKey($channel, transport::channels());
        set_config('connectors', $class, access::C);
        $this->assertArrayHasKey($channel, transport::channels());
        $mock = (object)['channel' => $channel, 'id' => 9, 'subject' => 'Test', 'body' => 'Synthetic'];
        $this->assertSame(['delivered', 0, ''], transport::send($mock, $learner));
        set_config('connectors', '', access::C);
        $this->assertSame(['retry', 0, 'disabledchannel'], transport::send($mock, $learner));
    }

    /**
     * Privacy erasure removes the subject's pending delivery row while keeping peers.
     */
    public function test_privacy_erases_only_approved_recipient(): void {
        global $DB;
        [$scope, $course, $learner, $peer] = $this->fixture();
        $job = $this->queue($scope, ['courseid' => $course->id]);
        $list = provider::get_contexts_for_userid($learner->id);
        $this->assertContains((int)$scope->id, array_map('intval', $list->get_contextids()));
        $request = new \core_privacy\local\request\approved_contextlist($learner, access::C, [$scope->id]);
        provider::delete_data_for_user($request);
        $this->assertFalse($DB->record_exists(store::REC, ['campaignid' => $job->id, 'userid' => $learner->id]));
        $this->assertTrue($DB->record_exists(store::REC, ['campaignid' => $job->id, 'userid' => $peer->id]));
    }
}
