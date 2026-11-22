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

namespace report_skillupsquality;

use core_privacy\local\request\approved_contextlist;
use PHPUnit\Framework\Attributes\CoversClass;
use report_skillupsquality\licensing\entitlements;
use report_skillupsquality\privacy\provider;

/**
 * Exercise real Moodle course fixtures, authorization and data lifecycle.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(catalog::class)]
#[CoversClass(engine::class)]
#[CoversClass(checks::class)]
#[CoversClass(inspection::class)]
#[CoversClass(store::class)]
#[CoversClass(review::class)]
#[CoversClass(scope::class)]
#[CoversClass(provider::class)]
#[CoversClass(task\maintain::class)]
#[CoversClass(entitlements::class)]
final class quality_test extends \advanced_testcase {
    /**
     * Empty course is objective and empty sections stay advisory.
     */
    public function test_empty_course_and_severity_configuration(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $result = engine::audit($course, catalog::policy());
        $this->assertEquals(85, $result['score']);
        $this->assertEquals(1, $result['objective']);
        $this->assertGreaterThan(0, $result['advisory']);
        set_config('severity_emptycourse', 'critical', scope::COMPONENT);
        $this->assertEquals(75, engine::audit($course, catalog::policy())['score']);
        set_config('enable_emptycourse', 0, scope::COMPONENT);
        $this->assertEquals(100, engine::audit($course, catalog::policy())['score']);
    }

    /**
     * Verify course dates, native module settings and missing rubric only by reading the course.
     */
    public function test_dates_grading_and_rubric_do_not_write_core_data(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'sumgrades' => 0, 'grade' => 10]);
        // Represent legacy inconsistent dates bypassed by normal activity form validation.
        $now = time();
        $DB->set_field('assign', 'allowsubmissionsfromdate', $now + DAYSECS, ['id' => $assign->id]);
        $DB->set_field('assign', 'duedate', $now, ['id' => $assign->id]);
        $manager = get_grading_manager(\context_module::instance($assign->cmid), 'mod_assign', 'submissions');
        $manager->set_active_method('rubric');
        $grade = \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id]);
        $DB->set_field('grade_items', 'grademax', $grade->grademin, ['id' => $grade->id]);
        $before = $DB->get_record('assign', ['id' => $assign->id]);
        $areas = $DB->count_records('grading_areas');
        $definitions = $DB->count_records('grading_definitions');
        $audited = engine::audit($course, catalog::policy());
        $codes = array_column($audited['findings'], 'rulecode');
        foreach (['dates', 'quizgrade', 'rubric', 'grade'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertEquals($before, $DB->get_record('assign', ['id' => $assign->id]));
        $this->assertEquals($areas, $DB->count_records('grading_areas'));
        $this->assertEquals($definitions, $DB->count_records('grading_definitions'));
        $this->assertEquals(40, $audited['score']);
    }

    /**
     * Distinguish a missing embedded file from one registered in the same context.
     */
    public function test_embedded_assets_and_invalid_url(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'contentformat' => FORMAT_HTML,
            'content' => '<img src="@@PLUGINFILE@@/absent.png"><a href="@@PLUGINFILE@@/present.txt">File</a>',
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($page->cmid)->id,
            'component' => 'mod_page', 'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'present.txt',
        ], 'authorized fixture');
        $url = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id, 'externalurl' => 'https://example.invalid',
        ]);
        $DB->set_field('url', 'externalurl', 'https:///missing-host', ['id' => $url->id]);
        $result = engine::audit($course, catalog::policy());
        $findings = array_values(array_filter($result['findings'], static fn($row) => $row['rulecode'] === 'links'));
        $bycm = array_column($findings, 'evidence', 'targetid');
        $this->assertSame(['badlinks' => 0, 'missingfiles' => 1], store::decode($bycm[$page->cmid]));
        $this->assertSame(['badlinks' => 1, 'missingfiles' => 0], store::decode($bycm[$url->cmid]));
        $this->assertEquals(92, $result['score']);
        $this->assertStringNotContainsString('absent.png', $bycm[$page->cmid]);
    }

    /**
     * Verify empty page detection, hidden flags and completion configuration.
     */
    public function test_hidden_and_completion_are_separate_advisories(): void {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'content' => '<p>&nbsp;</p>', 'visible' => 0,
        ]);
        $result = engine::audit($course, catalog::policy());
        $codes = array_column($result['findings'], 'rulecode');
        $this->assertContains('emptycontent', $codes);
        $this->assertContains('hidden', $codes);
        $this->assertContains('completion', $codes);
        $this->assertEquals(92, $result['score']);
    }

    /**
     * Reject duplicates and publish an audit exactly once.
     */
    public function test_adhoc_recovers_and_publishes_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $queued = store::enqueue([$course]);
        $runid = reset($queued['ids']);
        $this->assertGreaterThan(0, $runid);
        $this->assertEquals([], store::enqueue([$course])['ids']);
        $tasks = \core\task\manager::get_adhoc_tasks(task\inspect_course::class);
        $this->assertCount(1, $tasks);
        $this->assertEquals($runid, reset($tasks)->get_custom_data()->runid);
        store::change($runid, 'running');
        reset($tasks)->execute();
        reset($tasks)->execute();
        $this->assertEquals('done', store::run($runid)->state);
        $this->assertEquals(1, $DB->count_records(store::FINDING, ['runid' => $runid, 'rulecode' => 'emptycourse']));
    }

    /**
     * An audit submitted by a teacher cannot run after revocation of the role.
     */
    public function test_requester_permission_is_revalidated(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $runid = store::enqueue([$course])['ids'][0];
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($manual, $user->id);
        $this->setAdminUser();
        $this->assertFalse(scope::allowed($course, 'run', (int)$user->id));
        store::execute($runid);
        $this->assertEquals('denied', store::run($runid)->state);
        $this->assertFalse($DB->record_exists(store::FINDING, ['runid' => $runid]));
    }

    /**
     * A teacher cannot read a different course's finding even if the ID is known.
     */
    public function test_finding_id_scope_is_enforced(): void {
        global $DB;
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $first->id, 'editingteacher');
        $this->setAdminUser();
        $runid = store::enqueue([$second])['ids'][0];
        store::execute($runid);
        $item = $DB->get_field(store::FINDING, 'id', ['runid' => $runid, 'rulecode' => 'emptycourse'], MUST_EXIST);
        $this->setUser($user);
        $this->expectExceptionMessage(scope::text('scopefailure'));
        store::finding((int)$item);
    }

    /**
     * Categories and explicit batches resolve only courses inside their path.
     */
    public function test_category_and_explicit_batch_scope(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $outside = $this->getDataGenerator()->create_category();
        $a = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $b = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $c = $this->getDataGenerator()->create_course(['category' => $outside->id]);
        $found = scope::batch('category', $category->id, '');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_column($found, 'id'));
        $this->assertEquals([$b->id], array_column(scope::batch('selected', $category->id, (string)$b->id), 'id'));
        $this->expectExceptionMessage(scope::text('scopefailure'));
        scope::batch('selected', $category->id, $b->id . ',' . $c->id);
    }

    /**
     * Reconcile findings between audits and anonymize plan notes without altering quality results.
     */
    public function test_comparison_plans_and_privacy(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $prior = store::enqueue([$course])['ids'][0];
        store::execute($prior);
        $old = store::run($prior);
        $item = $DB->get_record(store::FINDING, ['runid' => $prior, 'rulecode' => 'emptycourse'], '*', MUST_EXIST);
        store::save_plan($item->id, 'planned', 'Add onboarding', 0);
        $plan = $DB->get_record(store::PLAN, ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEmpty(review::compliance(null, [$plan]));
        store::save_plan(0, 'working', 'Add introduction', 0, $plan->id);
        $DB->set_field(store::PLAN, 'timemodified', time() - 1, ['id' => $plan->id]);
        $plan = store::plan($plan->id);
        $this->assertEquals('working', $plan->state);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => 'Welcome']);
        $DB->set_field(store::RUN, 'timecreated', time() - 100, ['id' => $prior]);
        $current = store::enqueue([$course])['ids'][0];
        store::execute($current);
        $fresh = store::run($current);
        $delta = review::compare($fresh, $old);
        $this->assertArrayHasKey($item->fingerprint, $delta['absent']);
        $this->assertEquals('absent', review::compliance($fresh, [$plan])[$plan->id]);
        $admin = get_admin();
        $context = \context_course::instance($course->id);
        provider::delete_data_for_user(new approved_contextlist($admin, scope::COMPONENT, [$context->id]));
        $this->assertEmpty(store::plan($plan->id)->notes);
        $this->assertEquals(0, store::run($prior)->userid);
        $this->assertEquals('done', store::run($current)->state);
    }

    /**
     * Retention removes old reports and their evidence.
     */
    public function test_retention_removes_evidence(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $run = store::enqueue([$course])['ids'][0];
        store::execute($run);
        $DB->set_field(store::RUN, 'timemodified', time() - 190 * DAYSECS, ['id' => $run]);
        (new task\maintain())->execute();
        $this->assertFalse($DB->record_exists(store::RUN, ['id' => $run]));
        $this->assertFalse($DB->record_exists(store::FINDING, ['runid' => $run]));
    }

    /**
     * A link that becomes uninspectable cannot count as resolved evidence.
     */
    public function test_partial_link_checks_do_not_verify_corrections(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'content' => '<img src="@@PLUGINFILE@@/absent.png">', 'contentformat' => FORMAT_HTML,
        ]);
        $before = store::enqueue([$course])['ids'][0];
        store::execute($before);
        $item = $DB->get_record(store::FINDING, ['runid' => $before, 'rulecode' => 'links'], '*', MUST_EXIST);
        store::save_plan($item->id, 'working', 'Review the image', 0);
        $DB->set_field(store::PLAN, 'timemodified', time() - 1, ['courseid' => $course->id]);
        $plan = $DB->get_record(store::PLAN, ['courseid' => $course->id], '*', MUST_EXIST);
        $DB->set_field('page', 'content', str_repeat('x', 8300), ['id' => $page->id]);
        $DB->set_field(store::RUN, 'timecreated', time() - 100, ['id' => $before]);
        $after = store::enqueue([$course])['ids'][0];
        store::execute($after);
        $latest = store::run($after);
        $this->assertContains($item->fingerprint, store::decode($latest->policy)['unverified']);
        $this->assertArrayNotHasKey($item->fingerprint, review::compare($latest, store::run($before))['absent']);
        $this->assertEmpty(review::compliance($latest, [$plan]));
    }

    /**
     * Export entitlements are signed, time-bound and revocable without network access.
     */
    public function test_signed_entitlement(): void {
        global $CFG;
        $this->resetAfterTest();
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('The PHP Sodium extension is unavailable.');
        }
        $pair = sodium_crypto_sign_keypair();
        $payload = json_encode([
            'version' => 1, 'component' => scope::COMPONENT, 'id' => 'quality-qa-entitlement', 'tier' => 'professional',
            'issued' => time() - MINSECS, 'expires' => time() + DAYSECS,
            'sites' => [entitlements::site_id()], 'features' => ['aggregate_export'],
        ], JSON_THROW_ON_ERROR);
        $signature = sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair));
        $CFG->report_skillupsquality_entitlement = base64_encode($payload) . '.' . base64_encode($signature);
        $CFG->report_skillupsquality_licensepublickey = base64_encode(sodium_crypto_sign_publickey($pair));
        $this->assertSame(['aggregate_export'], entitlements::status()['features']);
        $CFG->report_skillupsquality_revoked = ['quality-qa-entitlement'];
        $this->assertSame('community', entitlements::status()['state']);
    }
}
