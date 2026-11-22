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

use local_skillupsaigrading\activity\assign;
use local_skillupsaigrading\privacy\provider;

/**
 * Native integration tests for assignment, permission, and review invariants.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow_test extends \advanced_testcase {
    /**
     * Create an individual assignment, two group scopes and a native rubric.
     */
    private function fixture(bool $separate = false, bool $withgrade = false, bool $allocated = false): array {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $student = $gen->create_user(['firstname' => 'Carmen', 'lastname' => 'Evidence']);
        $other = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($other->id, $course->id, 'student');
        $mode = $separate ? SEPARATEGROUPS : NOGROUPS;
        $assignment = $gen->create_module('assign', [
            'course' => $course->id, 'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 3, 'assignsubmission_file_maxsizebytes' => 0,
            'submissiondrafts' => 0, 'grade' => 100, 'groupmode' => $mode,
            'markingworkflow' => (int)$allocated, 'markingallocation' => (int)$allocated,
            'markercount' => (int)$allocated,
        ]);
        if ($separate) {
            $a = $gen->create_group(['courseid' => $course->id]);
            $b = $gen->create_group(['courseid' => $course->id]);
            $gen->create_group_member(['groupid' => $a->id, 'userid' => $teacher->id]);
            $gen->create_group_member(['groupid' => $a->id, 'userid' => $student->id]);
            $gen->create_group_member(['groupid' => $b->id, 'userid' => $other->id]);
        }
        $context = \context_module::instance($assignment->cmid);
        $native = new \assign($context, get_coursemodule_from_id('assign', $assignment->cmid), $course);
        $assigngen = $gen->get_plugin_generator('mod_assign');
        foreach (
            [$student->id => 'Carmen measured erosion at three locations. Contact carmen@example.org.',
                $other->id => 'Exclusive evidence from another student.'] as $userid => $text
        ) {
            $assigngen->create_submission(['cmid' => $assignment->cmid, 'userid' => $userid, 'onlinetext' => $text]);
        }
        if ($withgrade) {
            $native->save_grade($student->id, (object)['grade' => 80, 'attemptnumber' => 0]);
        }
        if ($allocated) {
            if (method_exists($native, 'update_marker_allocations')) {
                $native->update_marker_allocations($student->id, [1 => [(int)$teacher->id, null]]);
            } else if (method_exists($native, 'update_allocated_markers')) {
                $native->update_allocated_markers($student->id, [(int)$teacher->id]);
            } else {
                $flags = $native->get_user_flags($student->id, true);
                $flags->allocatedmarker = $teacher->id;
                $native->update_user_flags($flags);
            }
        }
        $gen->get_plugin_generator('gradingform_rubric')->create_instance(
            $context,
            'mod_assign',
            'submissions',
            'Fieldwork',
            'Reusable grading rubric',
            ['Measures erosion using evidence' => ['Missing' => 0, 'Partial' => 3, 'Supported' => 6]]
        );
        $this->setUser($teacher);
        return [new assign($assignment->cmid, 'review'), $teacher, $student, $other, $native, $course];
    }

    /**
     * Queue a bound excerpt from the native online text submission.
     */
    private function request(assign $adapter, \stdClass $student, string $provider = 'local', bool $consent = false): int {
        [, $signature, $text] = $adapter->extract($student->id, 0);
        $hash = hash('sha256', evaluation::json($adapter->rubric()));
        return workflow::create(
            $adapter,
            $student->id,
            0,
            str_repeat('a', 32),
            $signature,
            $hash,
            evaluation::redact($text, $student),
            $provider,
            $consent
        );
    }

    /**
     * Require both core and plugin capabilities and refuse a different module's evidence.
     */
    public function test_student_permission_and_cross_module(): void {
        [$adapter, $teacher, $student, , , $course] = $this->fixture();
        $id = $this->request($adapter, $student);
        $other = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        try {
            workflow::read(new assign($other->cmid), $id);
            $this->fail('A record from another module was visible.');
        } catch (\moodle_exception $error) {
            $this->assertSame('accessdenied', $error->errorcode);
        }
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        new assign($adapter->cm->id);
    }

    /**
     * A guessed subject ID or source ID cannot cross the separate-group boundary.
     */
    public function test_separate_groups_guard_submissions_and_statistics(): void {
        [$adapter, $teacher, $student, $other] = $this->fixture(true);
        $this->assertTrue($adapter->visible($student->id));
        $this->assertFalse($adapter->visible($other->id));
        $this->assertArrayNotHasKey($other->id, $adapter->participants());
        $this->setAdminUser();
        $otherjob = $this->request(new assign($adapter->cm->id), $other);
        $gen = $this->getDataGenerator();
        $this->setUser($gen->create_user());
        try {
            new assign($adapter->cm->id);
            $this->fail('Unenrolled user could access the activity.');
        } catch (\moodle_exception $error) {
            $this->assertContains($error->errorcode, ['accessdenied', 'nopermissions']);
        }
        $this->setUser($teacher);
        [$stats] = ui::statistics(new assign($adapter->cm->id));
        $this->assertSame(0, $stats->total);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('accessdenied', access::C));
        workflow::read($adapter, $otherjob);
    }

    /**
     * Restrict direct IDs to currently allocated participants.
     */
    public function test_marker_allocation_limits_review(): void {
        [$adapter, , $student, $other] = $this->fixture(false, false, true);
        $this->assertTrue($adapter->visible($student->id));
        $this->assertFalse($adapter->visible($other->id));
        $this->assertArrayNotHasKey($other->id, $adapter->participants());
        $this->expectExceptionMessage(get_string('accessdenied', access::C));
        $adapter->submission($other->id);
    }

    /**
     * Review writes do not touch native grades and stale parallel decisions are rejected.
     */
    public function test_review_history_deduplication_and_grade_consistency(): void {
        global $DB;
        [$adapter, , $student, , $native] = $this->fixture(false, true);
        $before = $native->get_user_grade($student->id, false, 0);
        $finalbefore = $native->get_grade_item()->get_final($student->id);
        $this->assertSame(80.0, (float)$finalbefore->finalgrade);
        $id = $this->request($adapter, $student);
        $this->assertSame($id, $this->request($adapter, $student));
        $job = workflow::read($adapter, $id);
        $this->assertSame('ready', $job->state);
        $this->assertSame('pending', $job->reviewstate);
        $this->assertNull($job->suggested);
        $this->assertNull($job->excerpt);
        $this->assertStringNotContainsString('Carmen', $job->result);
        $this->assertStringNotContainsString('@example.org', $job->result);
        workflow::decide($adapter, $id, 0, 'edit', 'Add the missing sampling method.');
        $job = workflow::read($adapter, $id);
        workflow::decide($adapter, $id, (int)$job->latestdecision, 'approve', 'The field observations are supported.');
        $approved = workflow::read($adapter, $id);
        $this->assertSame('approved', $approved->reviewstate);
        try {
            workflow::decide($adapter, $id, (int)$job->latestdecision, 'reject', 'Stale teacher window.');
            $this->fail('A stale decision must not replace an approval.');
        } catch (\moodle_exception $error) {
            $this->assertSame('stalesnapshot', $error->errorcode);
        }
        $this->assertSame(2, $DB->count_records(workflow::DECISION, ['jobid' => $id]));
        $this->assertEquals($before, $native->get_user_grade($student->id, false, 0));
        $after = $native->get_grade_item()->get_final($student->id);
        $this->assertEquals($finalbefore, $after);
        $this->assertSame(80.0, $adapter->human_percent($student->id, $adapter->submission($student->id)));
    }

    /**
     * Change the official rubric configuration and invalidate approval of the old snapshot.
     */
    public function test_changed_rubric_invalidates_review(): void {
        [$adapter, , $student] = $this->fixture();
        $id = $this->request($adapter, $student);
        get_grading_manager($adapter->context, 'mod_assign', 'submissions')->set_active_method(null);
        $this->assertFalse(workflow::current($adapter, workflow::read($adapter, $id)));
        $this->expectExceptionMessage(get_string('stalesnapshot', access::C));
        workflow::decide($adapter, $id, 0, 'approve', 'The original rubric was replaced.');
    }

    /**
     * Resolve files only from the selected subject's current attempt.
     */
    public function test_cross_submission_file_isolation(): void {
        [$adapter, , $student, $other] = $this->fixture();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $adapter->context->id, 'component' => 'assignsubmission_file',
            'filearea' => 'submission_files', 'itemid' => $adapter->submission($other->id)->id,
            'filepath' => '/', 'filename' => 'evidence.txt',
        ], 'Private evidence from the other submission.');
        $this->assertArrayNotHasKey($file->get_id(), $adapter->sources($student->id));
        $this->assertSame('Private evidence from the other submission.', $adapter->extract($other->id, $file->get_id())[2]);
        $this->expectExceptionMessage(get_string('invaliddocument', access::C));
        $adapter->extract($student->id, $file->get_id());
    }

    /**
     * Filter unauthorized rows before limiting the statistics sample.
     */
    public function test_largest_dashboard_sample_stays_authorized(): void {
        global $DB;
        [$adapter, , $student, $other] = $this->fixture(true);
        $first = $this->request($adapter, $student);
        $record = $DB->get_record(workflow::JOB, ['id' => $first], '*', MUST_EXIST);
        unset($record->id);
        $record->studentid = $other->id;
        $records = [];
        for ($i = 0; $i < 510; $i++) {
            $record->nonce = str_pad(dechex($i), 32, '0', STR_PAD_LEFT);
            $records[] = clone $record;
        }
        $DB->insert_records(workflow::JOB, $records);
        [$stats, $recent] = ui::statistics($adapter);
        $this->assertSame(1, $stats->total);
        $this->assertSame($first, (int)$recent[0]->id);
    }

    /**
     * Disabling AI after enqueue prevents transfer and purges the excerpt.
     */
    public function test_ai_switch_is_checked_again_in_cron(): void {
        [$adapter, , $student] = $this->fixture();
        global $CFG;
        $CFG->local_skillupsaigrading_providers = ['ollama' => [
            'url' => 'http://127.0.0.1:11434/api/chat', 'allowloopback' => true,
        ]];
        foreach (
            ['enableai' => 1, 'ollama_enabled' => 1, 'ollama_model' => 'fixture',
                'ollama_modelversion' => 'sha256:fixture'] as $setting => $value
        ) {
            set_config($setting, $value, access::C);
        }
        $id = $this->request($adapter, $student, 'ollama', true);
        $this->assertSame('queued', workflow::read($adapter, $id)->state);
        set_config('enableai', 0, access::C);
        workflow::run($adapter->cm->id, $id);
        $job = workflow::read($adapter, $id);
        $this->assertSame('failed', $job->state);
        $this->assertNull($job->excerpt);
        $this->assertNull($job->result);
    }

    /**
     * Erasure removes student-specific derivatives and their decisions.
     */
    public function test_privacy_erases_student_reviews_scoped_to_module(): void {
        global $DB;
        [$adapter, , $student] = $this->fixture();
        $id = $this->request($adapter, $student);
        workflow::decide($adapter, $id, 0, 'approve', 'Approved.');
        $contextids = provider::get_contexts_for_userid($student->id)->get_contextids();
        $this->assertContains((int)$adapter->context->id, array_map('intval', $contextids));
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $student,
            access::C,
            [$adapter->context->id]
        ));
        $this->assertFalse($DB->record_exists(workflow::JOB, ['id' => $id]));
        $this->assertFalse($DB->record_exists(workflow::DECISION, ['jobid' => $id]));
        $this->assertNotEmpty($adapter->submission($student->id));
    }
}
