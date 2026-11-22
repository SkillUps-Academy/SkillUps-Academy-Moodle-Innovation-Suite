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

namespace local_skillupssuccess;

use local_skillupssuccess\privacy\deletion;

/**
 * Moodle-backed evidence reconciliation and authorization tests with synthetic records.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow_test extends \advanced_testcase {
    /**
     * Create two learners, a permitted delegated tutor and native assessment.
     */
    private function fixture(): array {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $now = time();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => $now - 35 * DAYSECS]);
        $learner = $gen->create_user(['firstname' => 'Luis', 'lastname' => 'Synthetic']);
        $other = $gen->create_user();
        $tutor = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id, 'student');
        $gen->enrol_user($other->id, $course->id, 'student');
        $gen->enrol_user($tutor->id, $course->id, 'teacher');
        $context = \context_course::instance($course->id);
        $role = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        foreach (['moodle/site:accessallgroups', 'moodle/grade:viewall', 'report/log:view'] as $native) {
            assign_capability($native, CAP_ALLOW, $role, $context->id, true);
        }
        $enrol = $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'manual'], MUST_EXIST);
        foreach ([$learner, $other] as $user) {
            $params = ['enrolid' => $enrol, 'userid' => $user->id];
            // Synthetic dates simulate course membership well before collection.
            $DB->set_field('user_enrolments', 'timecreated', $now - 30 * DAYSECS, $params);
            $DB->set_field('user_enrolments', 'timestart', $now - 30 * DAYSECS, $params);
        }
        $DB->insert_record('user_lastaccess', ['userid' => $other->id, 'courseid' => $course->id, 'timeaccess' => $now - 200]);
        $assign = $gen->create_module('assign', [
            'course' => $course->id, 'duedate' => $now - DAYSECS, 'allowsubmissionsfromdate' => $now - 10 * DAYSECS,
            'grade' => 100, 'submissiondrafts' => 0, 'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $gen->get_plugin_generator('mod_assign')->create_submission([
            'cmid' => $assign->cmid, 'userid' => $other->id, 'onlinetext' => 'Synthetic submission already sent.',
        ]);
        grade_update(
            'mod/assign',
            $course->id,
            'mod',
            'assign',
            $assign->id,
            0,
            (object)['userid' => $learner->id, 'rawgrade' => 52]
        );
        grade_regrade_final_grades($course->id);
        $native = new \assign(\context_module::instance($assign->cmid), get_coursemodule_from_id('assign', $assign->cmid), $course);
        store::configure($course, rules::config(['participation' => 0, 'overdue' => 1]), true, true);
        return [$course, $learner, $other, $tutor, $native];
    }

    /**
     * Respect native extensions and the student-specific grade history.
     */
    public function test_native_signals_respect_extensions_and_grades(): void {
        global $DB;
        [$course, $learner, , , $native] = $this->fixture();
        $now = time();
        $DB->insert_record(store::OBS, [
            'courseid' => $course->id, 'userid' => $learner->id, 'daykey' => intdiv($now, DAYSECS) - 8,
            'captured' => $now - 8 * DAYSECS, 'factors' => '{}', 'risk' => 'normal', 'ruleversion' => 'fixture',
            'gradepct' => 68, 'modelversion' => '', 'modelfingerprint' => '',
        ]);
        $data = evidence::collect($course, $learner->id, $now);
        $this->assertGreaterThanOrEqual(29, $data['inactive']);
        $this->assertSame(1, $data['overdue']);
        $this->assertEquals(52, $data['grade']);
        $this->assertEquals(16, $data['decline']);
        $this->assertTrue($native->save_user_extension($learner->id, $now + 3 * DAYSECS));
        $this->assertSame(0, evidence::collect($course, $learner->id, $now)['overdue']);
    }

    /**
     * Idempotent capture, scoped case assignment, stale revision and unchanged native grades.
     */
    public function test_workflow_and_grade_consistency(): void {
        global $DB;
        [$course, $learner, $other, $tutor, $native] = $this->fixture();
        $grade = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
        $before = $grade->get_final($learner->id);
        $submissionbefore = $native->get_user_submission($other->id, false);
        store::scan($course->id);
        store::scan($course->id);
        $this->assertEquals(2, $DB->count_records(store::OBS, ['courseid' => $course->id]));
        $this->assertEquals(2, $DB->count_records(store::SAMPLE, ['courseid' => $course->id]));
        $case = $DB->get_record(store::CASES, ['courseid' => $course->id, 'userid' => $learner->id], '*', MUST_EXIST);
        $this->assertSame('high', $case->risk);
        $this->assertArrayHasKey($native->get_course_module()->id, evidence::pending($course, $case->id));
        $this->assertTrue(scope::allowed($course, 'view', $tutor->id));
        $this->setUser($tutor);
        try {
            store::case_for($course, $case->id);
            $this->fail('An unassigned tutor could open the case.');
        } catch (\moodle_exception $error) {
            $this->assertSame('outofscope', $error->errorcode);
        }
        $this->setAdminUser();
        store::intervene($course, $case->id, 0, 'assign', 'Support rotation.', $tutor->id);
        $this->setUser($tutor);
        $assigned = store::case_for($course, $case->id);
        $this->assertEquals($tutor->id, $assigned->tutorid);
        store::intervene($course, $case->id, $assigned->revision, 'monitor', 'Agreed to meet after class.', 0);
        $this->assertSame('monitor', store::case_for($course, $case->id)->state);
        $this->assertEquals($before, $grade->get_final($learner->id));
        $this->assertEquals($submissionbefore, $native->get_user_submission($other->id, false));
        try {
            store::intervene($course, $case->id, $assigned->revision, 'resolved', 'A stale window.', 0);
            $this->fail('A stale case revision overwrote history.');
        } catch (\moodle_exception $error) {
            $this->assertSame('stale', $error->errorcode);
        }
        $uncorrelated = $this->getDataGenerator()->create_course();
        $this->expectExceptionMessage(util::str('outofscope'));
        store::case_for($uncorrelated, $case->id);
    }

    /**
     * Prospective labels are collected only after their horizon and remain separate from interventions.
     */
    public function test_quality_horizon_and_privacy_erasure(): void {
        global $DB;
        [$course, $learner, $other] = $this->fixture();
        store::scan($course->id);
        $sample = $DB->get_record(store::SAMPLE, ['userid' => $learner->id, 'courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(1, $sample->rulealert);
        $this->assertEquals(-1, $sample->modelalert);
        $negative = $DB->get_record(store::SAMPLE, ['userid' => $other->id, 'courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(0, $negative->rulealert);
        try {
            store::assess($course, $sample->id, 1);
            $this->fail('An outcome was recorded before its prospective window closed.');
        } catch (\moodle_exception $error) {
            $this->assertSame('stale', $error->errorcode);
        }
        $DB->set_field(store::SAMPLE, 'due', time() - 1, ['id' => $sample->id]);
        store::assess($course, $sample->id, 0);
        $cohort = quality::cohorts([$course->id => $course], $sample->cohort, false);
        $this->assertSame(1, reset($cohort[$course->id])['fp']);
        $contexts = \local_skillupssuccess\privacy\provider::courseids($learner->id);
        $this->assertSame([(int)$course->id], array_map('intval', $contexts));
        deletion::by_course($course->id, $learner->id);
        $this->assertFalse($DB->record_exists(store::OBS, ['userid' => $learner->id]));
        $this->assertFalse($DB->record_exists(store::CASES, ['userid' => $learner->id]));
        $this->assertTrue($DB->record_exists(store::SAMPLE, ['userid' => $other->id]));
    }
}
