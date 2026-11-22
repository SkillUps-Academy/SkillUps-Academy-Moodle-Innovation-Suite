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

namespace local_skillupsflow;

use local_skillupsflow\privacy\provider;
use local_skillupsflow\task\dispatch;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Moodle integration for state transitions, native effects and durable admission.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(engine::class)]
#[CoversClass(store::class)]
#[CoversClass(dispatch::class)]
#[CoversClass(observer::class)]
#[CoversClass(provider::class)]
final class engine_test extends \advanced_testcase {
    /**
     * True paths, native writes, wait dates and repeated admission.
     */
    public function test_branch_delay_and_deduplication(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $id = $this->publish(['type' => 'manual', 'courseid' => (int)$course->id], [
            ['id' => 'check', 'type' => 'condition', 'operator' => 'and', 'tests' => ['hascourse', 'visiblecourse'],
                'yes' => 'hide', 'no' => 'skip'],
            ['id' => 'hide', 'type' => 'visibility', 'value' => 0, 'next' => 'wait'],
            ['id' => 'wait', 'type' => 'delay', 'minutes' => 1, 'next' => 'skip'],
            ['id' => 'skip', 'type' => 'stop'],
        ]);
        $run = store::admit($id, 'manual', 'same');
        $this->assertSame($run, store::admit($id, 'manual', 'same'));
        $this->assertEquals(1, $DB->count_records('local_skillupsflow_run'));
        engine::execute($run);
        $this->assertSame('hide', $this->record($run)->node);
        engine::execute($run);
        $this->assertEquals(0, get_course($course->id)->visible);
        engine::execute($run);
        $this->assertGreaterThan(time(), (int)$this->record($run)->due);
        engine::execute($run);
        $this->assertSame('queued', $this->record($run)->status);
        $this->due($run);
        engine::execute($run);
        $this->assertSame('completed', $this->record($run)->status);
        $this->assertTrue($DB->record_exists('local_skillupsflow_log', ['runid' => $run, 'outcome' => 'delayed']));
    }

    /**
     * Retry an unresolvable target, keep completed changes and route the failure.
     */
    public function test_partial_failure_routes_after_bounded_retries(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $id = $this->publish(['type' => 'manual', 'courseid' => (int)$course->id], [
            ['id' => 'hide', 'type' => 'visibility', 'value' => 0, 'next' => 'missing'],
            ['id' => 'missing', 'type' => 'suspend', 'userid' => 2000000000, 'error' => 'fallback'],
            ['id' => 'fallback', 'type' => 'stop'],
        ]);
        $run = store::admit($id, 'manual', 'once');
        engine::execute($run);
        for ($i = 0; $i < 3; $i++) {
            $this->due($run);
            engine::execute($run);
        }
        $this->assertSame('fallback', $this->record($run)->node);
        $this->assertEquals(2, $DB->count_records('local_skillupsflow_log', ['runid' => $run, 'outcome' => 'retry']));
        $this->assertEquals(0, get_course($course->id)->visible);
        engine::execute($run);
        $this->assertSame('completed', $this->record($run)->status);
    }

    /**
     * Recover a missing worker job and do not resend an ambiguous notification.
     */
    public function test_recovery_and_ambiguous_send(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = $this->publish(['type' => 'manual'], [['id' => 'send', 'type' => 'notify', 'body' => 'Notice']]);
        $run = store::admit($id, 'manual', 'notification');
        $taskscope = ['component' => 'local_skillupsflow', 'classname' => '\local_skillupsflow\task\execute_run'];
        $DB->delete_records('task_adhoc', $taskscope);
        $DB->set_field('local_skillupsflow_run', 'wake', time() - 10, ['id' => $run]);
        (new dispatch())->execute();
        $this->assertTrue($DB->record_exists('task_adhoc', $taskscope));
        $DB->set_field('local_skillupsflow_run', 'status', 'sending', ['id' => $run]);
        $this->due($run);
        engine::execute($run);
        engine::execute($run);
        $this->assertSame('failed', $this->record($run)->status);
        $this->assertEquals(1, $DB->count_records('local_skillupsflow_log', ['runid' => $run, 'outcome' => 'uncertain']));
    }

    /**
     * A paused workflow does not advance; only a separate authorized reviewer can decide.
     */
    public function test_human_decision_and_pause(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $author = (int)$USER->id;
        $id = $this->publish(['type' => 'manual'], [
            ['id' => 'review', 'type' => 'approval', 'yes' => 'finish'],
            ['id' => 'finish', 'type' => 'stop'],
        ]);
        $active = (int)$DB->get_field('local_skillupsflow_flow', 'active', ['id' => $id]);
        $run = store::admit($id, 'manual', 'approve');
        store::activate($id, 0);
        engine::execute($run);
        $this->assertSame('queued', $this->record($run)->status);
        store::activate($id, $active);
        engine::execute($run);
        $this->assertSame('approval', $this->record($run)->status);
        try {
            engine::decide($run, true);
            $this->fail('Self-approval unexpectedly succeeded');
        } catch (\moodle_exception $exception) {
            $this->assertSame('decisionunavailable', $exception->errorcode);
        }
        $reviewer = $this->getDataGenerator()->create_user();
        $role = create_role('Reviewer', 'flowreviewer', '');
        $context = \context_system::instance();
        assign_capability('local/skillupsflow:approve', CAP_ALLOW, $role, $context);
        role_assign($role, $reviewer->id, $context->id);
        $this->setUser($reviewer);
        engine::decide($run, true);
        $this->assertNotEquals($author, $reviewer->id);
        $this->assertSame('finish', $this->record($run)->node);
        $this->assertTrue($DB->record_exists('local_skillupsflow_log', ['runid' => $run,
            'outcome' => 'approved', 'actorid' => $reviewer->id]));
    }

    /**
     * Recurrence records one overdue slot and advances to the next future slot.
     */
    public function test_recurring_trigger_and_event_observer(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = $this->publish(
            ['type' => 'interval', 'minutes' => 1, 'start' => time() - 3600],
            [['id' => 'end', 'type' => 'stop']]
        );
        $eventflow = $this->publish(['type' => 'user_created'], [['id' => 'end', 'type' => 'stop']]);
        $target = $this->getDataGenerator()->create_user();
        (new dispatch())->execute();
        (new dispatch())->execute();
        $this->assertEquals(1, $DB->count_records('local_skillupsflow_run', ['flowid' => $id]));
        $this->assertGreaterThan(time(), (int)$DB->get_field('local_skillupsflow_flow', 'nextdue', ['id' => $id]));
        $this->assertTrue($DB->record_exists('local_skillupsflow_run', ['flowid' => $eventflow, 'userid' => $target->id]));
    }

    /**
     * Erasing a fixed target revokes, rather than retargets, an active revision.
     */
    public function test_privacy_revokes_fixed_target(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $target = $this->getDataGenerator()->create_user();
        $id = $this->publish(['type' => 'manual', 'userid' => (int)$target->id], [['id' => 'end', 'type' => 'stop']]);
        $run = store::admit($id, 'manual', 'privacy');
        $context = \context_system::instance();
        $this->assertEquals([$context->id], provider::get_contexts_for_userid($target->id)->get_contextids());
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $target,
            'local_skillupsflow',
            [$context->id]
        ));
        $this->assertEquals(0, $DB->get_field('local_skillupsflow_flow', 'active', ['id' => $id]));
        $this->assertEquals(0, $this->record($run)->userid);
        $this->assertSame('cancelled', $this->record($run)->status);
    }

    /**
     * Exercise native enrolment and one Moodle notification without external delivery.
     */
    public function test_native_enrolment_and_notification(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $target = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $student = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $id = $this->publish(['type' => 'manual', 'userid' => (int)$target->id, 'courseid' => (int)$course->id], [
            ['id' => 'enrol', 'type' => 'enrol', 'roleid' => $student, 'next' => 'notify'],
            ['id' => 'notify', 'type' => 'notify', 'body' => 'Flow test message'],
        ]);
        $sink = $this->redirectMessages();
        $run = store::admit($id, 'manual', 'native');
        engine::execute($run);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $target->id, '', true));
        $this->assertSame('notify', $this->record($run)->node);
        engine::execute($run);
        engine::execute($run);
        $this->assertSame('completed', $this->record($run)->status);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Create and activate a validated revision owned by the current account.
     */
    private function publish(array $trigger, array $nodes): int {
        global $DB, $USER;
        $graph = definition::parse(json_encode(['format' => 1, 'trigger' => $trigger, 'entry' => $nodes[0]['id'],
            'nodes' => $nodes]));
        $id = store::save(0, 0, 'Workflow', $graph, $USER->id);
        store::activate($id, (int)$DB->get_field('local_skillupsflow_flow', 'latest', ['id' => $id]));
        return $id;
    }

    /**
     * Read one current run.
     */
    private function record(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_skillupsflow_run', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Simulate the passage of time without real-minute waits.
     */
    private function due(int $id): void {
        global $DB;
        $DB->set_field('local_skillupsflow_run', 'due', time() - 1, ['id' => $id]);
    }
}
