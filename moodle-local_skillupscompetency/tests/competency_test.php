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

namespace local_skillupscompetency;

use core_competency\api;
use core_privacy\local\request\approved_contextlist;
use local_skillupscompetency\privacy\provider;

/**
 * Exercise native competency, rubric, permissions, file and reporting integrations.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class competency_test extends \advanced_testcase {
    /**
     * Prepare synthetic course, roles and a linked competency.
     *
     * @return array
     */
    private function fixture(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/filelib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id, 'groupmode' => SEPARATEGROUPS]);
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $student = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $outside = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $outside->id, 'userid' => $other->id]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        $core = $generator->get_plugin_generator('core_competency');
        $framework = $core->create_framework(['shortname' => 'Research framework']);
        $item = $core->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Evaluate sources']);
        $cid = (int)$item->get('id');
        catalog::link($course, $cid);
        return compact('course', 'category', 'teacher', 'student', 'other', 'framework', 'item', 'cid', 'group');
    }

    /**
     * Build a valid submission for the tests.
     *
     * @param array $overrides Proposal field overrides
     * @return \stdClass
     */
    private function proposal(array $overrides = []): \stdClass {
        return (object)($overrides + [
            'requestkey' => bin2hex(random_bytes(16)), 'levelvalue' => 2, 'observations' => 'Evidence supports the selected level.',
        ]);
    }

    /**
     * Verify a program term and its native linked competency.
     */
    public function test_catalog_matrix_and_reports(): void {
        global $DB;
        $f = $this->fixture();
        $pid = program::save('program', (object)['categoryid' => $f['category']->id, 'code' => 'RES', 'name' => 'Research']);
        $tid = program::save('term', (object)[
            'programid' => $pid, 'name' => 'Current term', 'firstday' => '2020-01-01', 'lastday' => '2035-01-01',
        ]);
        program::save('course', (object)['programid' => $pid, 'courseid' => $f['course']->id]);
        $goalid = program::save('goal', (object)[
            'programid' => $pid, 'termid' => $tid, 'competencyid' => $f['cid'], 'expected' => 3, 'crosscut' => 1,
        ]);
        catalog::indicator($f['cid'], 3, 'Compare independently sourced evidence.');
        $annotation = $DB->get_field(store::IND, 'indicator', ['competencyid' => $f['cid']]);
        $this->assertSame('Compare independently sourced evidence.', $annotation);
        $this->assertEquals(1, store::get(store::GOAL, $goalid)->crosscut);
        $this->assertCount(1, program::goals_for_course((int)$f['course']->id));
        $p = store::get(store::PROG, $pid);
        $t = store::get(store::TERM, $tid);
        $this->assertCount(2, iterator_to_array(reports::matrix($p, $t)));
        api::grade_competency_in_course($f['course'], $f['student']->id, $f['cid'], 2);
        $scoped = iterator_to_array(reports::matrix($p, $t, (int)$f['course']->id, (int)$f['group']->id));
        $this->assertCount(1, $scoped);
        $this->assertSame(1, $scoped[0]['gap']);
        $this->assertSame('C', $scoped[0]['expected']);
        $this->assertSame("'=1+2", reports::cell('=1+2'));
        $data = catalog::interchange($f['framework']);
        $data['indicators'][0]['indicator'] = 'Second annotated indicator';
        catalog::interchange($f['framework'], json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertSame('Second annotated indicator', catalog::interchange($f['framework'])['indicators'][0]['indicator']);
    }

    /**
     * Verify self-assessment, authorized review and native evidence.
     */
    public function test_review_publishes_native_rating_and_protects_pending_work(): void {
        global $DB;
        $f = $this->fixture();
        $this->setUser($f['student']);
        $id = assessment::submit($f['course'], (int)$f['student']->id, $f['cid'], 0, $this->proposal());
        $this->assertSame(0, assessment::current((int)$f['course']->id, (int)$f['student']->id, $f['cid']));
        $this->setUser($f['other']);
        $this->assertFalse(access::read_assessment(store::get(store::ASSESS, $id)));
        $this->setUser($f['teacher']);
        $this->assertFalse(access::can_see_subject($f['course'], (int)$f['other']->id));
        $this->assertTrue(access::read_assessment(store::get(store::ASSESS, $id)));
        try {
            assessment::decide($id, true, 'Unauthorized approval.');
            $this->fail('A teacher without review permission cannot publish.');
        } catch (\required_capability_exception $error) {
            $this->assertSame('pending', store::get(store::ASSESS, $id)->status);
        }
        $this->setAdminUser();
        assessment::decide($id, true, 'Validated by academic coordination.');
        $this->assertSame('approved', store::get(store::ASSESS, $id)->status);
        $this->assertSame(2, assessment::current((int)$f['course']->id, (int)$f['student']->id, $f['cid']));
        $this->assertTrue($DB->record_exists('competency_evidence', ['grade' => 2]));
        $this->expectException(\moodle_exception::class);
        assessment::decide($id, true, 'Repeated decision.');
    }

    /**
     * Verify a native rating change prevents publishing a stale proposal.
     */
    public function test_stale_proposal_can_be_rejected_without_changing_native_level(): void {
        $f = $this->fixture();
        $this->setUser($f['teacher']);
        $id = assessment::submit($f['course'], (int)$f['student']->id, $f['cid'], 0, $this->proposal());
        $this->setUser($f['student']);
        $this->assertFalse(access::read_assessment(store::get(store::ASSESS, $id)));
        $this->setAdminUser();
        api::grade_competency_in_course($f['course'], $f['student']->id, $f['cid'], 3);
        try {
            assessment::decide($id, true, 'Stale approval.');
            $this->fail('An outdated proposal must fail.');
        } catch (\moodle_exception $e) {
            $this->assertSame('stalelevel', $e->errorcode);
        }
        assessment::decide($id, false, 'Prepare a new proposal.');
        $this->assertSame('rejected', store::get(store::ASSESS, $id)->status);
        $this->assertSame(3, assessment::current((int)$f['course']->id, (int)$f['student']->id, $f['cid']));
    }

    /**
     * Verify use of a native assignment rubric and preservation of activity grades.
     */
    public function test_native_rubric_and_activity_link(): void {
        global $DB;
        $f = $this->fixture();
        $generator = $this->getDataGenerator();
        $assignment = $generator->create_module('assign', ['course' => $f['course']->id]);
        catalog::link($f['course'], $f['cid'], (int)$assignment->cmid);
        $rubrics = $generator->get_plugin_generator('gradingform_rubric');
        $context = \context_module::instance($assignment->cmid);
        $rubrics->create_instance($context, 'mod_assign', 'submissions', 'Research rubric', '', [
            'Evidence' => ['Absent' => 0, 'Strong' => 4], 'Argument' => ['Absent' => 0, 'Strong' => 4],
        ]);
        $definition = rubric::definition($f['course'], (int)$assignment->cmid);
        $this->assertCount(2, $definition['criteria']);
        $criteria = [];
        foreach ($definition['criteria'] as $key => $criterion) {
            $criteria[$key] = array_key_last($criterion['levels']);
        }
        $this->assertCount(1, access::links($f['course'], (int)$assignment->cmid));
        $this->setUser($f['teacher']);
        $id = assessment::submit($f['course'], (int)$f['student']->id, $f['cid'], (int)$assignment->cmid, $this->proposal([
            'rubric' => 1, 'criteria' => $criteria, 'rubrichash' => rubric::signature($definition),
        ]));
        $row = store::get(store::ASSESS, $id);
        $this->assertSame('rubric', $row->kind);
        $this->assertEquals(4, $row->levelvalue);
        $this->assertCount(2, json_decode($row->rubricdata, true)['criteria']);
        $this->setAdminUser();
        assessment::decide($id, true, 'Rubric check complete.');
        $this->assertFalse($DB->record_exists('assign_grades', ['assignment' => $assignment->id, 'userid' => $f['student']->id]));
    }

    /**
     * Verify private evidence ownership and Moodle Privacy API erasure.
     */
    public function test_private_evidence_and_erasure(): void {
        global $DB;
        $f = $this->fixture();
        $user = $f['student'];
        $this->setUser($user);
        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($user->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draft, 'filepath' => '/', 'filename' => 'synthetic.pdf',
        ], '%PDF-1.4 synthetic document for privacy tests');
        $id = assessment::submit($f['course'], (int)$user->id, $f['cid'], 0, $this->proposal(['attachments' => $draft]));
        $context = \context_course::instance($f['course']->id);
        $this->assertCount(1, get_file_storage()->get_area_files($context->id, access::C, assessment::AREA, $id, 'id ASC', false));
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int)$user->id)->get_contextids());
        provider::delete_data_for_user(new approved_contextlist($user, access::C, [$context->id]));
        $this->assertFalse($DB->record_exists(store::ASSESS, ['id' => $id]));
        $this->assertEmpty(get_file_storage()->get_area_files($context->id, access::C, assessment::AREA, $id, 'id ASC', false));
    }

    /**
     * Verify scheduled maintenance cleans deleted native references.
     */
    public function test_scheduled_maintenance_removes_orphaned_references(): void {
        global $DB;
        $f = $this->fixture();
        catalog::indicator($f['cid'], 1, 'Example indicator');
        $programid = program::save('program', (object)['categoryid' => $f['category']->id, 'code' => 'RES', 'name' => 'Research']);
        program::save('course', (object)['programid' => $programid, 'courseid' => $f['course']->id]);
        $this->assertTrue(delete_course($f['course'], false));
        $this->assertTrue(api::delete_competency($f['cid']));
        $task = new \local_skillupscompetency\task\maintenance();
        $task->execute();
        $this->assertFalse($DB->record_exists(store::IND, ['competencyid' => $f['cid']]));
        $this->assertFalse($DB->record_exists(store::MAP, ['courseid' => $f['course']->id]));
        $this->assertTrue($DB->record_exists(store::PROG, ['id' => $programid]));
    }

    /**
     * Verify course and category boundaries cannot be crossed with a direct request.
     */
    public function test_category_and_course_isolation(): void {
        $f = $this->fixture();
        $other = $this->getDataGenerator()->create_course();
        $identity = $this->getDataGenerator()->create_and_enrol($other, 'student');
        $this->setUser($identity);
        $this->assertFalse(access::can_see_subject($f['course'], (int)$f['student']->id));
        $this->setAdminUser();
        $pid = program::save('program', (object)['categoryid' => $f['category']->id, 'code' => 'RES', 'name' => 'Research']);
        $this->expectException(\moodle_exception::class);
        program::save('course', (object)['programid' => $pid, 'courseid' => $other->id]);
    }
}
