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

namespace local_skillupsexam;

use advanced_testcase;
use local_skillupsexam\privacy\provider;

/**
 * Exercise planning, native activity boundaries, supervision, messaging and privacy.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(plan::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(catalog::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(operations::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(roster::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(native::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scope::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(notifications::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class exam_test extends advanced_testcase {
    /**
     * Build a published-capable exam fixture.
     *
     * @param string $mod Module type
     * @param array $activity Overrides
     * @return array Synthetic fixtures
     */
    private function fixture(string $mod = 'quiz', array $activity = []): array {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $cat = $gen->create_category();
        $course = $gen->create_course(['category' => $cat->id, 'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $learner = $gen->create_and_enrol($course, 'student');
        $other = $gen->create_and_enrol($course, 'student');
        $group = $gen->create_group(['courseid' => $course->id]);
        $outside = $gen->create_group(['courseid' => $course->id]);
        groups_add_member($group, $learner);
        groups_add_member($outside, $other);
        $time = time() + HOURSECS;
        $module = $gen->create_module($mod, $activity + ['course' => $course->id]);
        $dates = (object)['name' => 'Term', 'starts' => $time - DAYSECS, 'ends' => $time + WEEKSECS];
        $period = catalog::save('period', (int)$cat->id, $dates);
        $room = catalog::save('room', (int)$cat->id, (object)['name' => 'Lab', 'campus' => 'North', 'capacity' => 1]);
        $parameters = (object)[
            'name' => 'Final exam', 'requestkey' => bin2hex(random_bytes(16)), 'periodid' => $period,
            'roomid' => $room, 'teacherid' => $teacher->id, 'supervisor' => 0,
            'starts' => $time, 'duration' => 60, 'cmid' => $module->cmid,
            'groupid' => $group->id, 'regmode' => 'manual', 'mode' => 'hybrid',
            'instruction' => 'Bring your note sheet', 'publish' => 1,
        ];
        return [$course, $teacher, $learner, $other, $parameters, $module, $group];
    }

    /**
     * Verify scoped roster operations and the audit trail.
     */
    public function test_manual_registration_attendance_and_incident(): void {
        global $DB;
        [$course, , $learner, $other, $parameters] = $this->fixture();
        $id = plan::save($course, $parameters);
        operations::apply($id, 'register', (int)$learner->id, '', '');
        operations::apply($id, 'register', (int)$learner->id, '', '');
        operations::apply($id, 'mark', (int)$learner->id, 'present', 'Checked');
        operations::apply($id, 'incident', (int)$learner->id, 'medium', 'Lost connection');
        $exam = store::get(store::EXAM, $id);
        $this->assertSame(1, roster::count($exam));
        $this->assertSame('present', $DB->get_field(store::MARK, 'status', ['examid' => $id]));
        $this->assertSame(4, $DB->count_records(store::AUDIT, ['examid' => $id]));
        $this->assertCount(1, $DB->get_records(store::QUEUE, ['examid' => $id]));
        $this->expectExceptionMessage(scope::text('unavailable'));
        operations::apply($id, 'mark', (int)$other->id, 'absent', '');
    }

    /**
     * A published reservation serializes staff, room and group use.
     */
    public function test_collision_and_cancel_releases_reservation(): void {
        [$course, , , , $parameters] = $this->fixture();
        $id = plan::save($course, $parameters);
        $next = clone $parameters;
        $next->requestkey = bin2hex(random_bytes(16));
        try {
            plan::save($course, $next);
            $this->fail('Overlapping reservation was accepted');
        } catch (\moodle_exception $error) {
            $this->assertSame('overlap', $error->errorcode);
        }
        plan::cancel($id, 1, 'Calendar updated');
        $this->assertSame('cancelled', store::get(store::EXAM, $id)->status);
        $this->assertGreaterThan($id, plan::save($course, $next));
    }

    /**
     * Active registration must fit in the selected room.
     */
    public function test_capacity_and_rollback(): void {
        global $DB;
        [$course, , , , $parameters] = $this->fixture();
        $parameters->groupid = 0;
        $parameters->regmode = 'active';
        try {
            plan::save($course, $parameters);
            $this->fail('Over-capacity reservation was accepted');
        } catch (\moodle_exception $error) {
            $this->assertSame('capacityexceeded', $error->errorcode);
        }
        $this->assertSame(0, $DB->count_records(store::EXAM));
        $this->assertSame(0, $DB->count_records(store::QUEUE));
    }

    /**
     * An old edit form cannot overwrite a more recent version.
     */
    public function test_optimistic_revision(): void {
        [$course, , , , $parameters] = $this->fixture();
        $parameters->publish = 0;
        $parameters->id = plan::save($course, $parameters);
        $parameters->reason = 'Refine planning';
        $parameters->revision = 1;
        plan::save($course, $parameters);
        $this->expectExceptionMessage(scope::text('stale'));
        plan::save($course, $parameters);
    }

    /**
     * A non-editing teacher in a different group cannot query this exam.
     */
    public function test_group_and_course_isolation(): void {
        [$course, , $learner, , $parameters, , $group] = $this->fixture();
        $id = plan::save($course, $parameters);
        $exam = store::get(store::EXAM, $id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $role = $this->getDataGenerator()->create_role();
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $role, \context_course::instance($course->id));
        role_assign($role, $teacher->id, \context_course::instance($course->id));
        $this->setUser($teacher);
        $this->assertFalse(scope::exam($exam, $course));
        groups_add_member($group, $teacher);
        $this->assertTrue(scope::exam($exam, $course));
        $foreign = $this->getDataGenerator()->create_course();
        $this->assertFalse(scope::exam($exam, $foreign));
        $this->setUser($learner);
        $this->assertFalse(scope::exam($exam, $course));
    }

    /**
     * Read a real Quiz attempt and its native setup, under teacher permissions.
     */
    public function test_quiz_attempts_and_configuration(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        [$course, $teacher, $learner, $other, $parameters, $quiz] = $this->fixture();
        $parameters->regmode = 'active';
        $id = plan::save($course, $parameters);
        $exam = store::get(store::EXAM, $id);
        $this->assertContains('noquestions', native::window($exam, $course)['warnings']);
        $generator = $this->getDataGenerator();
        $questions = $generator->get_plugin_generator('core_question');
        $category = $questions->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $questions->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz, 0, 1.0);
        \mod_quiz\quiz_settings::create_for_cmid((int)$quiz->cmid)->get_grade_calculator()->recompute_quiz_sumgrades();
        $quizgen = $generator->get_plugin_generator('mod_quiz');
        $this->setUser($learner);
        $quizgen->create_attempt($quiz->id, $learner->id);
        $this->setUser($teacher);
        $records = native::results($exam, $course, [$learner->id, $other->id]);
        $this->assertSame(1, $records[$learner->id]['count']);
        $this->assertNotEmpty($records[$learner->id]['state']);
        $this->assertArrayNotHasKey($other->id, $records);
    }

    /**
     * Respect the anonymous-marking boundary and never recalculate native grades.
     */
    public function test_assign_blind_marking_and_read_only_grade(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        [$course, $teacher, $learner, , $parameters, $assign] = $this->fixture('assign', ['blindmarking' => 1]);
        $parameters->regmode = 'active';
        $id = plan::save($course, $parameters);
        $exam = store::get(store::EXAM, $id);
        $this->setUser($teacher);
        $this->assertFalse(native::permitted($exam, $course));
        $DB->set_field('assign', 'revealidentities', 1, ['id' => $assign->id]);
        $assignment = new \assign(\context_module::instance($assign->cmid), null, null);
        $submission = $assignment->get_user_submission($learner->id, true);
        $DB->set_field('assign_submission', 'status', ASSIGN_SUBMISSION_STATUS_SUBMITTED, ['id' => $submission->id]);
        $item = \grade_item::fetch([
            'courseid' => $course->id,
            'iteminstance' => $assign->id,
            'itemmodule' => 'assign',
            'itemnumber' => 0,
        ]);
        $item->update_final_grade($learner->id, 85, 'gradebook');
        $DB->set_field('grade_items', 'needsupdate', 0, ['id' => $item->id]);
        $data = native::results($exam, $course, [$learner->id]);
        $this->assertSame(1, $data[$learner->id]['count']);
        $this->assertNotEmpty($data[$learner->id]['grade']);
        $DB->set_field('grade_items', 'needsupdate', 1, ['id' => $item->id]);
        $data = native::results($exam, $course, [$learner->id]);
        $this->assertNull($data[$learner->id]['grade']);
        $this->assertEquals(1, $DB->get_field('grade_items', 'needsupdate', ['id' => $item->id]));
    }

    /**
     * Moodle permissions and repeated cron runs preserve notice isolation.
     */
    public function test_cron_notices(): void {
        global $DB;
        [$course, $teacher, $learner, $other, $parameters] = $this->fixture();
        $parameters->regmode = 'active';
        $id = plan::save($course, $parameters);
        set_config('notifications', 1, scope::C);
        $sink = $this->redirectMessages();
        notifications::run();
        notifications::run();
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(4, $messages);
        $recipients = array_unique(array_column($messages, 'useridto'));
        $this->assertEqualsCanonicalizing([$teacher->id, $learner->id], array_values($recipients));
        $this->assertNotContains($other->id, array_column($messages, 'useridto'));
        $this->assertSame(2, $DB->count_records(store::QUEUE, ['examid' => $id, 'status' => 'done']));
    }

    /**
     * Verify roster completeness and the deterministic cursor after fifty entries.
     */
    public function test_large_roster_and_cursor(): void {
        [$course, , , , $parameters] = $this->fixture();
        $gen = $this->getDataGenerator();
        for ($i = 0; $i < 120; $i++) {
            $gen->create_and_enrol($course, 'student');
        }
        $parameters->roomid = 0;
        $parameters->mode = 'online';
        $parameters->groupid = 0;
        $parameters->regmode = 'active';
        $id = plan::save($course, $parameters);
        $exam = store::get(store::EXAM, $id);
        $this->assertSame(122, roster::count($exam));
        $first = array_slice(iterator_to_array(roster::users($exam)), 0, 50);
        $rest = iterator_to_array(roster::users($exam, true, true, (int)end($first)->id));
        $this->assertCount(72, $rest);
        $this->assertGreaterThan(end($first)->id, reset($rest)->id);
    }

    /**
     * A supervisor without editing rights cannot act on exams assigned to another account.
     */
    public function test_supervisor_assignment(): void {
        [$course, , $learner, , $parameters, , $group] = $this->fixture();
        $gen = $this->getDataGenerator();
        $role = $gen->create_role();
        $context = \context_course::instance($course->id);
        assign_capability(scope::CAP . 'supervise', CAP_ALLOW, $role, $context);
        $supervisor = $gen->create_and_enrol($course, 'teacher');
        role_assign($role, $supervisor->id, $context);
        groups_add_member($group, $supervisor);
        $unassigned = $gen->create_and_enrol($course, 'teacher');
        groups_add_member($group, $unassigned);
        role_assign($role, $unassigned->id, $context);
        $parameters->supervisor = $supervisor->id;
        $parameters->regmode = 'active';
        $id = plan::save($course, $parameters);
        $exam = store::get(store::EXAM, $id);
        $this->setUser($supervisor);
        $this->assertTrue(scope::exam($exam, $course, 'supervise'));
        operations::apply($id, 'mark', (int)$learner->id, 'late', '');
        $this->setUser($unassigned);
        $this->assertFalse(scope::exam($exam, $course, 'supervise'));
        $editorrole = $gen->create_role();
        assign_capability(scope::CAP . 'edit', CAP_ALLOW, $editorrole, $context);
        $editor = $gen->create_and_enrol($course, 'editingteacher');
        role_assign($editorrole, $editor->id, $context);
        $this->setUser($editor);
        $this->assertTrue(scope::exam($exam, $course, 'edit'));
        $this->assertFalse(scope::exam($exam, $course, 'supervise'));
        $this->expectExceptionMessage(scope::text('unavailable'));
        operations::apply($id, 'mark', (int)$learner->id, 'present', '');
    }

    /**
     * Erase a subject only in approved contexts and retain anonymous audit entries.
     */
    public function test_privacy_cleanup(): void {
        global $DB;
        [$course, , $learner, , $parameters] = $this->fixture();
        $id = plan::save($course, $parameters);
        operations::apply($id, 'register', (int)$learner->id, '', '');
        operations::apply($id, 'mark', (int)$learner->id, 'present', 'Synthetic detail');
        $context = \context_course::instance($course->id);
        $list = provider::get_contexts_for_userid($learner->id);
        $this->assertContains((int)$context->id, array_map('intval', $list->get_contextids()));
        $approved = new \core_privacy\local\request\approved_contextlist($learner, scope::C, [$context->id]);
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists(store::SEAT, ['examid' => $id]));
        $this->assertFalse($DB->record_exists(store::MARK, ['examid' => $id]));
        $this->assertTrue($DB->record_exists(store::AUDIT, ['examid' => $id, 'action' => 'mark', 'subjectid' => 0]));
    }
}
