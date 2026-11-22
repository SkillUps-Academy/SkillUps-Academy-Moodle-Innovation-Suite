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

namespace report_skillupsinsight;

/**
 * Reconcile Insight snapshots with seeded Moodle data and authorization.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class analytics_test extends \advanced_testcase {
    /**
     * Flush the native log reader/writer before Moodle resets its test data.
     */
    protected function tearDown(): void {
        get_log_manager(true);
        parent::tearDown();
    }

    /**
     * Moodle sources and role fixtures.
     */
    private function fixture(): array {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->libdir . '/gradelib.php');
        set_config('enablecompletion', 1);
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        get_log_manager(true);
        set_config('smallcell', 1, util::C);
        $generator = $this->getDataGenerator();
        $allowed = $generator->create_category(['name' => 'Health sciences']);
        $denied = $generator->create_category(['name' => 'Restricted category']);
        $course = $generator->create_course(['category' => $allowed->id, 'enablecompletion' => 1, 'fullname' => 'Physiology']);
        $other = $generator->create_course(['category' => $denied->id, 'fullname' => 'Private lab']);
        $learner = $generator->create_user();
        $second = $generator->create_user();
        $suspended = $generator->create_user(['suspended' => 1]);
        $teacher = $generator->create_user();
        $viewer = $generator->create_user();
        foreach ([$learner, $second, $suspended] as $person) {
            $generator->enrol_user($person->id, $course->id, 'student');
        }
        $generator->enrol_user($learner->id, $other->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $managerroles = get_archetype_roles('manager');
        role_assign(reset($managerroles)->id, $viewer->id, \context_coursecat::instance($allowed->id)->id);
        return [$course, $other, $learner, $second, $suspended, $teacher, $viewer];
    }

    /**
     * Write standard log fixture rows with realistic origin and visibility fields.
     */
    private function log(\stdClass $course, \stdClass $user, string $event, int $when, array $extra = []): void {
        global $DB;
        $context = \context_course::instance($course->id);
        $DB->insert_record('logstore_standard_log', $extra + [
            'courseid' => $course->id, 'userid' => $user->id, 'contextid' => $context->id,
            'contextlevel' => CONTEXT_COURSE, 'contextinstanceid' => $course->id, 'eventname' => $event,
            'component' => 'core', 'action' => 'viewed', 'target' => 'course', 'crud' => 'r', 'edulevel' => 2,
            'anonymous' => 0, 'origin' => 'web', 'timecreated' => $when,
        ]);
    }

    /**
     * Seed instructor and student actions, an academic grade and a completion receipt.
     */
    private function seed(\stdClass $course, \stdClass $learner, \stdClass $second, \stdClass $suspended, \stdClass $teacher): int {
        $now = time();
        $this->log($course, $learner, '\mod_quiz\event\attempt_submitted', $now - 10, ['component' => 'mod_quiz']);
        $this->log($course, $learner, '\mod_quiz\event\attempt_submitted', $now - 9, ['component' => 'mod_quiz']);
        $this->log($course, $second, '\core\event\course_viewed', $now - 9, ['anonymous' => 1]);
        $this->log($course, $second, '\core\event\course_viewed', $now - 9, ['realuserid' => $teacher->id]);
        $this->log($course, $second, '\core\event\course_viewed', $now - 9000);
        $this->log($course, $suspended, '\core\event\course_viewed', $now - 9);
        $this->log($course, $teacher, '\core\event\user_graded', $now - 8);
        $activity = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id, 'grade' => 100, 'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        grade_update(
            'mod/assign',
            $course->id,
            'mod',
            'assign',
            $activity->id,
            0,
            (object)['userid' => $learner->id, 'rawgrade' => 80]
        );
        grade_regrade_final_grades($course->id);
        $criteria = new \completion_criteria_activity();
        $configuration = (object)['id' => $course->id, 'criteria_activity' => [$activity->cmid => 1]];
        $criteria->update_config($configuration);
        (new \completion_completion(['course' => $course->id, 'userid' => $learner->id]))->mark_complete($now);
        return $now;
    }

    /**
     * Each recorded indicator has the expected source, population and exclusions.
     */
    public function test_moodle_reconciliation(): void {
        [$course, , $learner, $second, $suspended, $teacher] = $this->fixture();
        $now = $this->seed($course, $learner, $second, $suspended, $teacher);
        $values = metrics::values(metrics::capture($course, $now - 600, $now + 60));
        $this->assertEquals([
            'enrolled' => 2, 'newlearners' => 2, 'active' => 1, 'participation' => 50.0,
            'educators' => 1, 'teaching' => 1, 'academic' => 80.0, 'completion' => 50.0,
        ], $values);
        set_config('enabled_stores', '', 'tool_log');
        get_log_manager(true);
        $withoutlogs = metrics::values(metrics::capture($course, $now - 600, $now + 60));
        $this->assertNull($withoutlogs['active']);
        $this->assertNull($withoutlogs['participation']);
        $this->assertNull($withoutlogs['teaching']);
        $this->assertEquals(80.0, $withoutlogs['academic']);
    }

    /**
     * A generation remains invisible until all authorized snapshot rows are published.
     */
    public function test_atomic_publication_and_category_isolation(): void {
        global $DB;
        [$course, $other, , , , , $viewer] = $this->fixture();
        $id = $DB->insert_record(warehouse::PERIOD, ['name' => 'Term', 'begins' => time() - 300, 'ends' => time() + 300]);
        warehouse::step($id, 1);
        $first = $DB->get_record(warehouse::PERIOD, ['id' => $id]);
        $this->assertEquals(0, $first->published);
        $this->assertNull(warehouse::run($first));
        warehouse::step($id, 10);
        $current = $DB->get_record(warehouse::PERIOD, ['id' => $id]);
        $this->assertEquals($first->building, $current->published);
        $this->assertCount(2, $DB->get_records(warehouse::DATA, ['runid' => $current->published]));
        $this->setUser($viewer);
        $config = report::config(['period' => $id, 'category' => $course->category]);
        $view = report::build($config);
        $this->assertEquals(1, $view['coverage']);
        $this->assertEquals(2, $view['summary']['enrolled']);
        $this->assertSame('Physiology', $view['rows'][0]['label']);
        $this->expectException(\moodle_exception::class);
        report::build(report::config(['period' => $id, 'category' => $other->category]), 'export');
    }

    /**
     * Course-wide summaries are unavailable when all-group access is prohibited.
     */
    public function test_course_scope_requires_allgroup_access(): void {
        global $DB;
        [$course, , , , , $teacher] = $this->fixture();
        $this->setUser($teacher);
        $this->assertTrue(scope::can_course($course, 'view'));
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $role, \context_course::instance($course->id)->id);
        $this->assertFalse(scope::can_course($course, 'view'));
        $this->expectExceptionMessage(util::str('outofscope'));
        scope::context(0, $course->id);
    }

    /**
     * Add raw measures before deriving rates and suppress small denominators.
     */
    public function test_weighted_metrics_coverage_and_formula_safety(): void {
        $this->resetAfterTest();
        set_config('smallcell', 3, util::C);
        $a = ['loglearners' => 2, 'participating' => 2] + metrics::zero();
        $b = ['loglearners' => 8, 'participating' => 2] + metrics::zero();
        $this->assertNull(metrics::values($a)['participation']);
        $this->assertSame(40.0, metrics::values(metrics::sum($a, $b))['participation']);
        $this->assertNull(report::values($a, 0)['enrolled']);
        $this->assertSame("' =1+2", report::sheettext(' =1+2'));
        $this->assertSame('Safe label', report::sheettext('Safe label'));
    }

    /**
     * Revoked category authorization disables recurring delivery and preserves the privacy boundary.
     */
    public function test_scheduler_reauthorizes_and_erasure_is_scoped(): void {
        global $DB;
        [$course, , $learner, , , , $viewer] = $this->fixture();
        $config = report::config(['category' => $course->category]);
        $preset = $DB->insert_record(report::PRESET, ['name' => 'Owner report', 'userid' => $learner->id,
            'config' => util::json($config), 'timecreated' => time()]);
        $otherpreset = $DB->insert_record(report::PRESET, ['name' => 'Other report', 'userid' => $viewer->id,
            'config' => util::json($config), 'timecreated' => time()]);
        $watch = $DB->insert_record(report::WATCH, ['userid' => $learner->id, 'presetid' => $preset, 'metric' => 'active',
            'direction' => 'above', 'target' => 2, 'nextrun' => time(), 'laststatus' => 'pending']);
        scheduler::run($watch);
        $after = $DB->get_record(report::WATCH, ['id' => $watch]);
        $this->assertEquals(0, $after->enabled);
        $this->assertSame('unauthorized', $after->laststatus);
        $this->assertSame('unavailable', scheduler::status('above', null, 2.0, true));
        $this->assertSame('reached', scheduler::status('above', 2.0, 2.0, true));
        $this->assertSame('unreached', scheduler::status('below', 3.0, 2.0, true));
        \report_skillupsinsight\privacy\provider::erase($learner->id);
        $this->assertFalse($DB->record_exists(report::WATCH, ['id' => $watch]));
        $this->assertFalse($DB->record_exists(report::PRESET, ['id' => $preset]));
        $this->assertTrue($DB->record_exists(report::PRESET, ['id' => $otherpreset]));
    }
}
