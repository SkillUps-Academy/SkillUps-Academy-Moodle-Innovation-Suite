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

namespace local_skillupspathways;

use local_skillupspathways\integration\certification;
use local_skillupspathways\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Validate routing and Moodle evidence isolation with native fixtures.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(graph::class)]
#[CoversClass(progress::class)]
#[CoversClass(evidence::class)]
#[CoversClass(store::class)]
#[CoversClass(access::class)]
#[CoversClass(certification::class)]
#[CoversClass(provider::class)]
final class pathways_test extends \advanced_testcase {
    /**
     * Verify cross-category routing, both joins, and revocation of certification evidence.
     */
    public function test_prerequisites_and_course_isolation(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/completionlib.php');
        $this->setAdminUser();
        $data = $this->getDataGenerator();
        $a = $data->create_category();
        $b = $data->create_category();
        $source = $data->create_course(['category' => $a->id, 'enablecompletion' => 1]);
        $target = $data->create_course(['category' => $b->id, 'enablecompletion' => 1]);
        $student = $data->create_user();
        $data->enrol_user($student->id, $source->id, 'student');
        $first = $data->create_module('page', ['course' => $source->id, 'completion' => 1]);
        $alternative = $data->create_module('page', ['course' => $target->id, 'completion' => 1]);
        $any = $data->create_module('page', ['course' => $source->id, 'completion' => 1]);
        $all = $data->create_module('page', ['course' => $source->id, 'completion' => 1]);
        $nodes = [
            self::node('first', $source->id, $first->cmid),
            self::node('privatebranch', $target->id, $alternative->cmid, ['required' => false, 'title' => 'Restricted title']),
            self::node('anybranch', $source->id, $any->cmid, ['join' => 'any']),
            self::node('allbranch', $source->id, $all->cmid, ['required' => false]),
        ];
        $edges = [];
        foreach (['first', 'privatebranch'] as $parent) {
            foreach (['anybranch', 'allbranch'] as $child) {
                $edges[] = ['from' => $parent, 'to' => $child];
            }
        }
        $pathid = store::save($source->id, 0, 0, self::draft($nodes, $edges), true);
        $path = store::path($source->id, $pathid);
        $version = store::version($pathid, $path->activeversion);
        $this->setUser($student);
        progress::sync($path, $source);
        $result = progress::evaluate($version, $student->id);
        $this->assertSame(
            ['first' => 'ready', 'privatebranch' => 'unavailable', 'anybranch' => 'blocked', 'allbranch' => 'blocked'],
            $result['states']
        );
        $restricted = array_column($result['rows'], null, 'key')['privatebranch'];
        $this->assertSame(access::text('restrictedstep'), $restricted['title']);
        $this->assertSame('', $restricted['url']);
        $this->assertNull(certification::verified_completion($source->id, $pathid, $student->id));
        $info = new \completion_info($source);
        $info->update_state(get_fast_modinfo($source)->get_cm($first->cmid), COMPLETION_COMPLETE, $student->id);
        $this->assertSame('ready', progress::evaluate($version, $student->id)['states']['anybranch']);
        $this->assertSame('blocked', progress::evaluate($version, $student->id)['states']['allbranch']);
        $info->update_state(get_fast_modinfo($source)->get_cm($any->cmid), COMPLETION_COMPLETE, $student->id);
        progress::sync($path, $source);
        $this->assertNotNull(certification::verified_completion($source->id, $pathid, $student->id));
        $data->enrol_user($student->id, $target->id, 'student');
        $targetcm = get_fast_modinfo($target)->get_cm($alternative->cmid);
        (new \completion_info($target))->update_state($targetcm, COMPLETION_COMPLETE, $student->id);
        $this->assertSame('ready', progress::evaluate($version, $student->id)['states']['allbranch']);
        set_coursemodule_visible($alternative->cmid, 0);
        $this->assertSame('unavailable', progress::evaluate($version, $student->id)['states']['privatebranch']);
        $info->update_state(get_fast_modinfo($source)->get_cm($first->cmid), COMPLETION_INCOMPLETE, $student->id);
        $this->assertNull(certification::verified_completion($source->id, $pathid, $student->id));
    }

    /**
     * Publishing pins existing participants until explicit migration and preserves history.
     */
    public function test_versions_and_scoped_erasure(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->getDataGenerator();
        $source = $data->create_course(['enablecompletion' => 1]);
        $student = $data->create_user();
        $other = $data->create_user();
        $data->enrol_user($student->id, $source->id, 'student');
        $data->enrol_user($other->id, $source->id, 'student');
        $page = $data->create_module('page', ['course' => $source->id, 'completion' => 1]);
        $input = self::draft([self::node('first', $source->id, $page->cmid)]);
        $pathid = store::save($source->id, 0, 0, $input, true);
        $path = store::path($source->id, $pathid);
        progress::refresh($pathid, $source->id, $student->id, true);
        progress::refresh($pathid, $source->id, $other->id, true);
        $input->name = 'Version two';
        store::save($source->id, $pathid, $path->revision, $input, true);
        $new = store::path($source->id, $pathid);
        $this->assertEquals($path->activeversion, progress::get($pathid, $student->id)->versionid);
        progress::migrate($source, $pathid, $student->id, $new->activeversion, 0);
        $this->assertEquals($new->activeversion, progress::get($pathid, $student->id)->versionid);
        $this->assertEquals($path->activeversion, progress::get($pathid, $other->id)->versionid);
        $this->assertTrue($DB->record_exists(store::HISTORY, ['pathid' => $pathid, 'newstate' => 'migrated']));
        $contextid = \context_course::instance($source->id)->id;
        $this->assertEquals([$contextid], provider::get_contexts_for_userid($student->id)->get_contextids());
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist($student, access::C, [$contextid]));
        $this->assertNull(progress::get($pathid, $student->id));
        $this->assertNotNull(progress::get($pathid, $other->id));
        $this->expectExceptionMessage(access::text('draftchanged'));
        store::save($source->id, $pathid, $path->revision, $input, false);
    }

    /**
     * Reject cycles even if a destination considers its alternatives optional.
     */
    public function test_cycle_is_rejected(): void {
        $draft = self::draft([
            self::node('one', 2, 1),
            self::node('two', 3, 2, ['join' => 'any', 'required' => false]),
        ], [['from' => 'one', 'to' => 'two'], ['from' => 'two', 'to' => 'one']]);
        $this->expectExceptionMessage(access::text('cycle'));
        graph::parse($draft->graph, true);
    }

    /**
     * Use visible grades and course-specific proficiency, without reading hidden grades as criteria.
     */
    public function test_grade_and_competency_evidence(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/gradelib.php');
        $data = $this->getDataGenerator();
        $course = $data->create_course(['showgrades' => 1]);
        $student = $data->create_user();
        $data->enrol_user($student->id, $course->id, 'student');
        $assignment = $data->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $competencies = $data->get_plugin_generator('core_competency');
        $framework = $competencies->create_framework();
        $competency = $competencies->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = $competency->get('id');
        \core_competency\api::add_competency_to_course($course->id, $competencyid);
        $pathid = store::save($course->id, 0, 0, self::draft([
            self::node('assessment', $course->id, $assignment->cmid, [
                'completion' => false, 'grade' => 70, 'competency' => $competencyid,
            ]),
        ]), true);
        $path = store::path($course->id, $pathid);
        $version = store::version($pathid, $path->activeversion);
        $this->assertSame('ready', progress::evaluate($version, $student->id)['states']['assessment']);
        $item = \grade_item::fetch([
            'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assignment->id,
        ]);
        $item->update_raw_grade($student->id, 90);
        grade_regrade_final_grades($course->id);
        $this->assertSame('ready', progress::evaluate($version, $student->id)['states']['assessment']);
        \core_competency\api::grade_competency_in_course($course->id, $student->id, $competencyid, 4);
        $this->assertSame('complete', progress::evaluate($version, $student->id)['states']['assessment']);
        $item->set_hidden(1);
        $this->assertSame('ready', progress::evaluate($version, $student->id)['states']['assessment']);
    }

    /**
     * Reject explicit report group IDs outside a teacher's separate-groups scope.
     */
    public function test_separate_groups_are_enforced(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/group/lib.php');
        $data = $this->getDataGenerator();
        $course = $data->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $data->create_user();
        $data->enrol_user($teacher->id, $course->id, 'teacher');
        $own = $data->create_group(['courseid' => $course->id]);
        $other = $data->create_group(['courseid' => $course->id]);
        groups_add_member($own, $teacher);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, \context_course::instance($course->id));
        $this->setUser($teacher);
        $this->assertSame((int)$own->id, access::report_group($course, 0));
        $this->expectExceptionMessage(access::text('notavailable'));
        access::report_group($course, $other->id);
    }

    /**
     * Build a valid test graph node.
     *
     * @param string $key Node key
     * @param int $course Course ID
     * @param int $cm Course module ID
     * @param array $changes Overrides
     * @return array
     */
    private static function node(string $key, int $course, int $cm, array $changes = []): array {
        return $changes + [
            'key' => $key, 'title' => $key, 'course' => $course, 'cm' => $cm,
            'completion' => true, 'grade' => -1, 'competency' => 0, 'required' => true,
            'join' => 'all', 'lane' => 0, 'row' => 0,
        ];
    }

    /**
     * Wrap graph fixtures in draft fields.
     *
     * @param array $nodes Graph nodes
     * @param array $edges Graph edges
     * @return \stdClass
     */
    private static function draft(array $nodes, array $edges = []): \stdClass {
        return (object)[
            'name' => 'Fixture', 'description' => '', 'enabled' => 1, 'certificate' => 1,
            'graph' => json_encode(['nodes' => $nodes, 'edges' => $edges], JSON_THROW_ON_ERROR),
        ];
    }
}
