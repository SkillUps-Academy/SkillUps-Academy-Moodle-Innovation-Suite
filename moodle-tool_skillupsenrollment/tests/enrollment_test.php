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

namespace tool_skillupsenrollment;

use core_privacy\local\request\approved_contextlist;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_skillupsenrollment\privacy\provider;

/**
 * Exercise native enrollment, scheduling, scope and recovery.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(importer::class)]
#[CoversClass(validator::class)]
#[CoversClass(mutation::class)]
#[CoversClass(jobs::class)]
#[CoversClass(worker::class)]
#[CoversClass(provider::class)]
#[CoversClass(support::class)]
#[CoversClass(task\dispatch::class)]
final class enrollment_test extends \advanced_testcase {
    /**
     * Build an explicit intent for a native course and user.
     */
    private function input(\stdClass $course, \stdClass $user, string $action = 'enrol'): array {
        return ['action' => $action, 'courseid' => (string)$course->id, 'userid' => (string)$user->id];
    }

    /**
     * Run one authorized simulation through the real worker.
     */
    private function run_batch(array $rows): int {
        $id = jobs::draft($rows, 100, 0);
        jobs::command($id, 'confirm');
        worker::run($id);
        return $id;
    }

    /**
     * Read one row for isolated assertions.
     */
    private function row(int $job, int $position = 1): \stdClass {
        global $DB;
        return $DB->get_record(jobs::ROW, ['jobid' => $job, 'position' => $position], '*', MUST_EXIST);
    }

    /**
     * Create a course and native manual instance.
     */
    private function course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        return [$course, $manual];
    }

    /**
     * CSV and XLSX support literal values, headers and UTF-8.
     */
    public function test_importer(): void {
        $path = make_request_directory() . '/test.csv';
        file_put_contents($path, "\xef\xbb\xbfaction,username,shortname\n\"enrol\",\"test.user\",\"COURSE\"\n");
        $this->assertSame('test.user', importer::read($path, 'csv')[2]['username']);
        $path = make_request_directory() . '/test.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['action', 'userid', 'courseid']));
        $writer->addRow(Row::fromValues(['enrol', 3, 10]));
        $writer->close();
        $this->assertSame('3', importer::read($path, 'xlsx')[2]['userid']);
        $path = make_request_directory() . '/formula.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['action', 'userid', 'courseid']));
        $writer->addRow(new Row([new FormulaCell('=1+1', null), ...Row::fromValues([3, 10])->getCells()]));
        $writer->close();
        $this->expectExceptionMessage(support::text('formula'));
        importer::read($path, 'xlsx');
    }

    /**
     * Simulate without writes, detect duplicate rows and reject cross-course groups.
     */
    public function test_preview_validates_scope_and_does_not_enrol(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->course();
        [$another] = $this->course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $another->id]);
        $user = $this->getDataGenerator()->create_user();
        $input = $this->input($course, $user);
        $job = jobs::draft([1 => $input, 2 => $input, 3 => $input + ['groupid' => $group->id]], 100, 0);
        $this->assertSame('pending', $this->row($job)->state);
        $this->assertSame('duplicate', $this->row($job, 2)->errorcode);
        $this->assertSame('invalidgroup', $this->row($job, 3)->errorcode);
        worker::run($job);
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user));
        $this->assertEquals(0, $this->row($job, 3)->userid);
    }

    /**
     * Enroll students, teachers and a custom coordinator and add the selected group once.
     */
    public function test_staff_students_group_and_idempotence(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/group/lib.php');
        [$course, $manual] = $this->course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $coordinator = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'coordinator']);
        set_role_contextlevels($role, [CONTEXT_COURSE]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $rows = [
            1 => $this->input($course, $student) + ['role' => 'student', 'groupid' => $group->id],
            2 => $this->input($course, $teacher) + ['role' => 'editingteacher'],
            3 => $this->input($course, $coordinator) + ['role' => $role],
        ];
        $first = $this->run_batch($rows);
        $this->assertEquals(3, jobs::counts($first)['applied']);
        $this->assertTrue(groups_is_member($group->id, $student->id));
        $ctx = \context_course::instance($course->id);
        $this->assertTrue(user_has_role_assignment($coordinator->id, $role, $ctx->id));
        worker::run($first);
        $repeat = $this->run_batch($rows);
        $this->assertEquals(3, jobs::counts($repeat)['noop']);
        $this->assertEquals(3, $DB->count_records('user_enrolments', ['enrolid' => $manual->id]));
        $this->assertEquals(3, $DB->count_records('role_assignments', ['contextid' => $ctx->id]));
        $this->assertEquals(1, $DB->count_records('groups_members', ['groupid' => $group->id, 'userid' => $student->id]));
    }

    /**
     * Add a role without replacing an existing role or resetting enrollment dates.
     */
    public function test_role_and_status_changes_keep_dates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $manual] = $this->course();
        $user = $this->getDataGenerator()->create_user();
        $student = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $teacher = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $start = time() - DAYSECS;
        $end = time() + DAYSECS;
        enrol_get_plugin('manual')->enrol_user($manual, $user->id, $student, $start, $end, ENROL_USER_ACTIVE);
        $rolejob = $this->run_batch([1 => $this->input($course, $user, 'role') + ['role' => 'teacher']]);
        $this->assertSame('applied', $this->row($rolejob)->state);
        $context = \context_course::instance($course->id);
        $this->assertTrue(user_has_role_assignment($user->id, $student, $context->id));
        $this->assertTrue(user_has_role_assignment($user->id, $teacher, $context->id));
        $suspend = $this->run_batch([1 => $this->input($course, $user, 'suspend')]);
        $this->assertSame('applied', $this->row($suspend)->state);
        $this->assertFalse(is_enrolled($context, $user, '', true));
        $activate = $this->run_batch([1 => $this->input($course, $user, 'reactivate')]);
        $this->assertSame('applied', $this->row($activate)->state);
        $record = $DB->get_record('user_enrolments', ['enrolid' => $manual->id, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertEquals($start, $record->timestart);
        $this->assertEquals($end, $record->timeend);
        $this->assertTrue(is_enrolled($context, $user, '', true));
    }

    /**
     * Change self-enrollment status without affecting a manual instance or its roles.
     */
    public function test_self_method_only_changes_selected_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $manual] = $this->course();
        $user = $this->getDataGenerator()->create_user();
        $student = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $self = enrol_get_plugin('self');
        $selfid = $self->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'roleid' => $student]);
        $instance = $DB->get_record('enrol', ['id' => $selfid], '*', MUST_EXIST);
        $self->enrol_user($instance, $user->id, $student);
        enrol_get_plugin('manual')->enrol_user($manual, $user->id, $student);
        $input = $this->input($course, $user, 'suspend') + ['instanceid' => $selfid, 'method' => 'self'];
        $job = $this->run_batch([1 => $input]);
        $this->assertSame('applied', $this->row($job)->state);
        $this->assertEquals(ENROL_USER_SUSPENDED, $DB->get_field(
            'user_enrolments',
            'status',
            ['enrolid' => $selfid, 'userid' => $user->id]
        ));
        $this->assertEquals(ENROL_USER_ACTIVE, $DB->get_field(
            'user_enrolments',
            'status',
            ['enrolid' => $manual->id, 'userid' => $user->id]
        ));
        $invalid = jobs::draft([1 => array_replace($input, ['action' => 'enrol'])], 100, 0);
        $this->assertSame('invalidmethod', $this->row($invalid)->errorcode);
    }

    /**
     * Preserve external changes between simulation and confirmation.
     */
    public function test_conflict_preserves_external_enrollment(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $instance] = $this->course();
        $user = $this->getDataGenerator()->create_user();
        $draft = jobs::draft([1 => $this->input($course, $user) + ['role' => 'editingteacher']], 100, 0);
        jobs::command($draft, 'confirm');
        $student = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        enrol_get_plugin('manual')->enrol_user($instance, $user->id, $student);
        worker::run($draft);
        $row = $this->row($draft);
        $this->assertSame('changed', $row->errorcode);
        $this->assertFalse(user_has_role_assignment($user->id, $row->roleid, \context_course::instance($course->id)->id));
        $this->expectExceptionMessage(support::text('invalidaction'));
        jobs::command($draft, 'retry', (int)$row->id);
    }

    /**
     * Keep other students' successful results when one student becomes ineligible.
     */
    public function test_row_failure_and_selective_retry(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->course();
        $first = $this->getDataGenerator()->create_user();
        $next = $this->getDataGenerator()->create_user();
        $job = jobs::draft([1 => $this->input($course, $first), 2 => $this->input($course, $next)], 100, 0);
        jobs::command($job, 'confirm');
        $DB->set_field('user', 'suspended', 1, ['id' => $first->id]);
        worker::run($job);
        $this->assertSame('invaliduser', $this->row($job)->errorcode);
        $this->assertSame('applied', $this->row($job, 2)->state);
        $DB->set_field('user', 'suspended', 0, ['id' => $first->id]);
        jobs::command($job, 'retry', (int)$this->row($job)->id);
        worker::run($job);
        $this->assertSame('applied', $this->row($job)->state);
        $this->assertEquals(1, $this->row($job, 2)->attempts);
    }

    /**
     * Restrict a delegated operator in the wrong course and revoke their queued work.
     */
    public function test_scope_and_queue_revocation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->course();
        [$outside] = $this->course();
        $actor = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'factoryoperator']);
        $system = \context_system::instance();
        assign_capability('tool/skillupsenrollment:operate', CAP_ALLOW, $role, $system);
        role_assign($role, $actor->id, $system->id);
        $this->getDataGenerator()->enrol_user($actor->id, $course->id, 'editingteacher');
        $this->setUser($actor);
        $job = jobs::draft([1 => $this->input($course, $student), 2 => $this->input($outside, $student)], 100, 0);
        $this->assertSame('pending', $this->row($job)->state, $this->row($job)->errorcode);
        $this->assertSame('denied', $this->row($job, 2)->errorcode);
        jobs::command($job, 'confirm');
        role_unassign($role, $actor->id, $system->id);
        $this->setAdminUser();
        worker::run($job);
        $this->assertSame('denied', $this->row($job)->errorcode);
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $student));
    }

    /**
     * Keep scheduling and privacy data separate from actual enrollment.
     */
    public function test_future_schedule_and_privacy(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->course();
        $student = $this->getDataGenerator()->create_user();
        $job = jobs::draft([1 => $this->input($course, $student)], 100, time() + DAYSECS);
        jobs::command($job, 'confirm');
        (new task\dispatch())->execute();
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(task\process_job::class));
        worker::run($job);
        $this->assertSame('pending', $this->row($job)->state);
        $context = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist($student, support::C, [$context->id]));
        $this->assertEquals(0, $this->row($job)->userid);
        $this->assertSame('cancelled', $this->row($job)->state);
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $student));
    }

    /**
     * Enforce separate groups both in preview and report access.
     */
    public function test_separate_groups_operator_scope(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/group/lib.php');
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $context = \context_course::instance($course->id);
        $actor = $this->getDataGenerator()->create_user();
        $outside = $this->getDataGenerator()->create_user();
        $new = $this->getDataGenerator()->create_user();
        $permissions = $this->getDataGenerator()->create_role(['shortname' => 'groupoperator']);
        $teacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $system = \context_system::instance();
        assign_capability('tool/skillupsenrollment:operate', CAP_ALLOW, $permissions, $system);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacher, $context);
        role_assign($permissions, $actor->id, $system->id);
        $this->getDataGenerator()->enrol_user($actor->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($outside->id, $course->id, 'student');
        $own = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $other = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($own->id, $actor->id);
        groups_add_member($other->id, $outside->id);
        $this->setUser($actor);
        $this->assertFalse(support::can_report($course->id, $actor->id, $outside->id));
        $job = jobs::draft([
            1 => $this->input($course, $new) + ['groupid' => $own->id],
            2 => $this->input($course, $outside, 'suspend'),
        ], 100, 0);
        $this->assertSame('pending', $this->row($job)->state, $this->row($job)->errorcode);
        $this->assertTrue(ui::row_visible($this->row($job)));
        $this->assertSame('denied', $this->row($job, 2)->errorcode);
        jobs::command($job, 'confirm');
        worker::run($job);
        $this->assertTrue(groups_is_member($own->id, $new->id));
        $this->assertTrue(is_enrolled($context, $outside, '', true));
    }
}
