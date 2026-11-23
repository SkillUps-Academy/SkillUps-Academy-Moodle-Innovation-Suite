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

namespace local_skillupsgradebookguardian;

use advanced_testcase;
use context_course;
use grade_category;
use grade_item;
use stdClass;

/**
 * Integration and grade data regression tests against Moodle Gradebook.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsgradebookguardian\rules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsgradebookguardian\ledger::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsgradebookguardian\repair::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsgradebookguardian\report::class)]
final class guardian_test extends advanced_testcase {
    /**
     * Load Moodle APIs and provide an authorized default operator.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /**
     * A hidden quiz with a visible grade item and populated grade data.
     *
     * @return array Course, grade item and learner
     */
    private function fixture(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'grade' => 100]);
        set_coursemodule_visible($quiz->cmid, 0);
        grade_update(
            'mod/quiz',
            $course->id,
            'mod',
            'quiz',
            $quiz->id,
            0,
            ['userid' => $user->id, 'rawgrade' => 72, 'feedback' => 'Synthetic feedback'],
            ['hidden' => 0]
        );
        grade_regrade_final_grades($course->id);
        $item = grade_item::fetch([
            'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id,
        ]);
        return [$course, $item, $user];
    }

    /**
     * Compare all persisted grade and grade history rows before and after the assist workflow.
     */
    public function test_preview_approval_and_recovery_preserve_grades_and_totals(): void {
        global $DB;
        [$course, $item, $user] = $this->fixture();
        $total = grade_item::fetch_course_item($course->id);
        $totalbefore = $DB->get_record('grade_items', ['id' => $total->id], '*', MUST_EXIST);
        $gradebefore = $DB->get_records('grade_grades', ['userid' => $user->id], 'id');
        $gradehistory = $DB->get_records('grade_grades_history', ['userid' => $user->id], 'id');
        $this->assertCount(2, $gradebefore);
        $reportid = ledger::diagnose($course);
        $this->assertContains('hiddenmismatch', array_column(ledger::get($course->id, $reportid)->payload['findings'], 'rule'));
        $previewid = repair::preview($course, $reportid, $item->id);
        $this->assertSame(0, (int)grade_item::fetch(['id' => $item->id])->hidden);
        $this->assertSame($previewid, repair::preview($course, $reportid, $item->id));

        $decision = repair::apply($course, $previewid);
        $this->assertSame(1, (int)grade_item::fetch(['id' => $item->id])->hidden);
        $this->assertSame('apply', ledger::get($course->id, $decision)->kind);
        $this->assertEquals($gradebefore, $DB->get_records('grade_grades', ['userid' => $user->id], 'id'));
        $this->assertEquals($totalbefore, $DB->get_record('grade_items', ['id' => $total->id], '*', MUST_EXIST));

        $recovered = repair::apply($course, $previewid, true);
        $this->assertSame('revert', ledger::get($course->id, $recovered)->kind);
        $this->assertSame(0, (int)grade_item::fetch(['id' => $item->id])->hidden);
        $this->assertEquals($gradebefore, $DB->get_records('grade_grades', ['userid' => $user->id], 'id'));
        $this->assertEquals($gradehistory, $DB->get_records('grade_grades_history', ['userid' => $user->id], 'id'));
        $this->assertEquals($totalbefore, $DB->get_record('grade_items', ['id' => $total->id], '*', MUST_EXIST));
    }

    /**
     * Detect formula reference, calculation, cycles, weights and activity relationships without writing Core data.
     */
    public function test_diagnosis_detects_structural_faults_without_writes(): void {
        global $DB;
        [$course, $quizitem] = $this->fixture();
        $generator = $this->getDataGenerator();
        $category = new grade_category([
            'courseid' => $course->id, 'fullname' => 'Synthetic weighted', 'aggregation' => GRADE_AGGREGATE_WEIGHTED_MEAN,
        ], false);
        $category->insert();
        $items = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
            $item = new grade_item([
                'courseid' => $course->id, 'categoryid' => $category->id, 'itemtype' => 'manual',
                'itemname' => "Calculated $name", 'idnumber' => $name, 'aggregationcoef' => 0,
            ], false);
            $item->insert();
            $items[$name] = $item;
        }
        $items['A']->set_calculation('=[[B]]+1');
        $items['B']->set_calculation('=[[A]]+1');
        $items['C']->set_calculation('=[[Absent]]+1');
        $items['D']->set_calculation('=1+');
        $items['E']->set_calculation('=[[B]]+2');
        $generator->create_module('forum', ['course' => $course->id, 'assessed' => 0, 'grade_forum' => 0]);
        $allbefore = $DB->get_records('grade_items', ['courseid' => $course->id], 'id');
        $diagnosis = rules::diagnose(new snapshot($course));
        $findings = $diagnosis['findings'];
        $this->assertContains('weightszero', array_column($findings, 'rule'));
        $this->assertContains('reference', array_column($findings, 'rule'));
        $this->assertContains('formula', array_column($findings, 'rule'));
        $this->assertContains('rootactivity', array_column($findings, 'rule'));
        $this->assertContains('hiddenmismatch', array_column($findings, 'rule'));
        $this->assertContains((int)$quizitem->id, array_column($findings, 'id'));
        $dependencies = array_column(array_filter($findings, static fn(array $row): bool => $row['rule'] === 'dependency'), 'id');
        foreach (['A', 'B', 'E'] as $affected) {
            $this->assertContains((int)$items[$affected]->id, $dependencies);
        }
        $this->assertEquals($allbefore, $DB->get_records('grade_items', ['courseid' => $course->id], 'id'));
    }

    /**
     * Editing teachers can simulate, but the default role cannot approve.
     */
    public function test_teacher_cannot_approve(): void {
        [$course, $item] = $this->fixture();
        $reportid = ledger::diagnose($course);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $preview = repair::preview($course, $reportid, $item->id);
        $this->assertGreaterThan(0, $preview);
        $this->assertFalse(access::canrepair(context_course::instance($course->id)));
        $this->expectException(\required_capability_exception::class);
        repair::apply($course, $preview);
    }

    /**
     * A report ID from course A cannot be used in course B.
     */
    public function test_course_scope_applies_even_to_administrator(): void {
        [$course, $item] = $this->fixture();
        $reportid = ledger::diagnose($course);
        $other = $this->getDataGenerator()->create_course();
        $this->expectException(\dml_missing_record_exception::class);
        repair::preview($other, $reportid, $item->id);
    }

    /**
     * A learner cannot open a course's structural gradebook report.
     */
    public function test_student_cannot_diagnose(): void {
        [$course, , $user] = $this->fixture();
        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        ledger::diagnose($course);
    }

    /**
     * Subsequent Moodle configuration edits invalidate saved approval.
     */
    public function test_stale_preview_rejected(): void {
        [$course, $item] = $this->fixture();
        $preview = repair::preview($course, ledger::diagnose($course), $item->id);
        grade_update('mod/quiz', $course->id, 'mod', 'quiz', $item->iteminstance, 0, null, ['idnumber' => 'different']);
        $this->expectExceptionMessage(access::text('stale'));
        repair::apply($course, $preview);
    }

    /**
     * A completed request cannot run again, even while the preview remains available.
     */
    public function test_repeated_approval_rejected(): void {
        [$course, $item] = $this->fixture();
        $preview = repair::preview($course, ledger::diagnose($course), $item->id);
        repair::apply($course, $preview);
        $this->expectExceptionMessage(access::text('stale'));
        repair::apply($course, $preview);
    }

    /**
     * An administrator can disable repairs at site level.
     */
    public function test_repairs_can_be_disabled(): void {
        [$course, $item] = $this->fixture();
        $reportid = ledger::diagnose($course);
        set_config('repairenabled', 0, access::COMPONENT);
        $this->expectExceptionMessage(access::text('repairdisabled'));
        repair::preview($course, $reportid, $item->id);
    }

    /**
     * Resolved and changed findings have stable identities.
     */
    public function test_comparison_keeps_previous_evidence(): void {
        $old = ['rule' => 'hidden', 'target' => 'item', 'id' => 5, 'evidence' => ['hidden' => 1]];
        $new = array_replace($old, ['evidence' => ['hidden' => 500]]);
        $this->assertSame('changed', report::compare([$new], [$old])[0]['change']);
        $this->assertSame(['hidden' => 1], report::compare([$new], [$old])[0]['previous']);
        $this->assertSame('resolved', report::compare([], [$old])[0]['change']);
        $this->assertSame('added', report::compare([$new], [])[0]['change']);
    }

    /**
     * Grade items without a corresponding activity are technical findings.
     */
    public function test_orphaned_grade_item_requires_technical_intervention(): void {
        $course = $this->getDataGenerator()->create_course();
        grade_update('mod/quiz', $course->id, 'mod', 'quiz', 931234, 0, null, [
            'itemname' => 'Synthetic orphan', 'grademax' => 100,
        ]);
        $codes = array_column(rules::diagnose(new snapshot($course))['findings'], 'rule');
        $this->assertContains('unlinked', $codes);
        $this->assertSame('technical', rules::CATALOGUE['unlinked'][1]);
    }

    /**
     * Expiry task removes old history while retaining recent operations.
     */
    public function test_retention_and_privacy_use_course_scope(): void {
        global $DB, $USER;
        [$course] = $this->fixture();
        $reportid = ledger::diagnose($course);
        $oldid = ledger::append($course->id, 'diagnosis', ['counts' => [], 'findings' => []]);
        $DB->set_field(ledger::TABLE, 'timecreated', time() - 181 * DAYSECS, ['id' => $oldid]);
        (new task\purge())->execute();
        $this->assertFalse($DB->record_exists(ledger::TABLE, ['id' => $oldid]));
        $context = context_course::instance($course->id);
        $contextids = privacy\provider::get_contexts_for_userid((int)$USER->id)->get_contextids();
        $this->assertContains((int)$context->id, array_map('intval', $contextids));
        $list = new \core_privacy\local\request\approved_contextlist($USER, access::COMPONENT, [$context->id]);
        privacy\provider::delete_data_for_user($list);
        $this->assertSame(0, (int)ledger::get($course->id, $reportid)->userid);
    }
}
