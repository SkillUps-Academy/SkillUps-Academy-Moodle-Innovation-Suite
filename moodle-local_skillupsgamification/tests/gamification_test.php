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

namespace local_skillupsgamification;

use context_course;
use core\task\manager;
use core_privacy\local\request\approved_contextlist;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Native completion, award consistency, challenges and privacy regressions.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access::class)]
#[CoversClass(admin::class)]
#[CoversClass(engine::class)]
#[CoversClass(goals::class)]
#[CoversClass(observer::class)]
#[CoversClass(privacy\provider::class)]
#[CoversClass(ranking::class)]
#[CoversClass(store::class)]
#[CoversClass(task\award_completion::class)]
#[CoversClass(team::class)]
final class gamification_test extends \advanced_testcase {
    /**
     * Load core Moodle completion and group APIs.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecompletion', 1);
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/grouplib.php');
        require_once($CFG->dirroot . '/group/lib.php');
    }

    /**
     * Set up a course and two participants with a general completion rule.
     *
     * @param array $coursevalues Native configuration overrides
     * @param array $settings Plugin configuration overrides
     * @param array $rule Rule overrides
     * @return array
     */
    private function course(array $coursevalues = [], array $settings = [], array $rule = []): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course($coursevalues + ['enablecompletion' => 1]);
        $a = $generator->create_and_enrol($course, 'student');
        $b = $generator->create_and_enrol($course, 'student');
        admin::course((int)$course->id, (object)($settings + [
            'enabled' => 1, 'rankingenabled' => 1, 'dailycap' => 0, 'xplevel' => 20,
        ]));
        $this->rule((int)$course->id, $rule);
        return [$course, $a, $b];
    }

    /**
     * Save a scoped award rule and return its ID.
     *
     * @param int $courseid Course ID
     * @param array $values Rule overrides
     * @return int
     */
    private function rule(int $courseid, array $values = []): int {
        global $DB;
        admin::rule($courseid, 0, (object)($values + [
            'name' => 'Verified learning', 'kind' => 'activity', 'sourceid' => 0, 'enabled' => 1,
            'points' => 10, 'xp' => 25, 'maxdaily' => 0, 'maxawards' => 0, 'starts' => 0, 'ends' => 0,
        ]));
        return (int)array_key_first($DB->get_records(store::RULE, ['courseid' => $courseid], 'id DESC', 'id', 0, 1));
    }

    /**
     * Create a verified automatic or manual test activity.
     *
     * @param stdClass $course Course
     * @param bool $automatic Whether Moodle completes the page when viewed
     * @return \cm_info
     */
    private function activity(stdClass $course, bool $automatic = true): \cm_info {
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'completion' => $automatic ? COMPLETION_TRACKING_AUTOMATIC : COMPLETION_TRACKING_MANUAL,
            'completionview' => (int)$automatic,
        ]);
        return get_fast_modinfo($course)->get_cm($page->cmid);
    }

    /**
     * Trigger a Moodle-native completion for the given participant.
     *
     * @param stdClass $course Course
     * @param \cm_info $cm Scoped activity
     * @param stdClass $user Participant
     * @return stdClass Native completion record
     */
    private function complete(stdClass $course, \cm_info $cm, stdClass $user): stdClass {
        $this->setUser($user);
        $native = new \completion_info($course);
        $native->set_module_viewed($cm, $user->id);
        return $native->get_data($cm, false, $user->id);
    }

    /**
     * Run this plugin's queued native jobs.
     */
    private function dispatch(): void {
        foreach (manager::get_adhoc_tasks(task\award_completion::class) as $task) {
            $task->execute();
            manager::delete_adhoc_task((int)$task->get_id());
        }
    }

    /**
     * Configure a goal with overrides as the administrator.
     *
     * @param int $courseid Course ID
     * @param array $values Goal overrides
     * @return int
     */
    private function goal(int $courseid, array $values = []): int {
        global $DB;
        admin::goal($courseid, 0, (object)($values + [
            'name' => 'Verified achievement', 'description' => 'Synthetic goal', 'kind' => 'badge',
            'icon' => 'star', 'scope' => 'individual', 'metric' => 'points', 'target' => 10,
            'cost' => 10, 'stock' => 0, 'enabled' => 1, 'starts' => 0, 'ends' => 0,
        ]));
        return (int)array_key_first($DB->get_records(store::GOAL, ['courseid' => $courseid], 'id DESC', 'id', 0, 1));
    }

    /**
     * Native event, retry and replacement rules cannot grant the same source twice.
     */
    public function test_completion_replay_and_replacement_rule_are_idempotent(): void {
        global $DB;
        [$course, $learner] = $this->course();
        $cm = $this->activity($course);
        $event = $this->complete($course, $cm, $learner);
        $tasks = manager::get_adhoc_tasks(task\award_completion::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $task->execute();
        $task->execute();
        $this->setAdminUser();
        $this->rule((int)$course->id, ['sourceid' => (int)$cm->id, 'points' => 100]);
        $this->assertFalse(engine::award(
            $course->id,
            $learner->id,
            'activity',
            $cm->id,
            $event->id,
            $event->timemodified
        ));
        $this->assertSame(['points' => 10, 'xp' => 25, 'balance' => 10], goals::totals($course->id, $learner->id));
        $this->assertSame(1, $DB->count_records(store::LEDGER));
    }

    /**
     * Wrong source, nonexistent records, manual completion and revoked enrollment cannot earn.
     */
    public function test_unverified_completions_do_not_award(): void {
        global $DB;
        [$course, $learner, $other] = $this->course();
        $automatic = $this->activity($course);
        $manual = $this->activity($course, false);
        $native = new \completion_info($course);
        $this->setUser($learner);
        $native->update_state($manual, COMPLETION_COMPLETE, $learner->id);
        $record = $native->get_data($manual, false, $learner->id);
        $this->assertFalse(engine::award($course->id, $learner->id, 'activity', $manual->id, $record->id, $record->timemodified));
        $evidence = $this->complete($course, $automatic, $learner);
        $this->assertFalse(engine::award(
            $course->id,
            $other->id,
            'activity',
            $automatic->id,
            $evidence->id,
            $evidence->timemodified
        ));
        $this->assertFalse(engine::award(
            $course->id,
            $learner->id,
            'activity',
            $automatic->id,
            $evidence->id + 500,
            $evidence->timemodified
        ));
        $this->setAdminUser();
        $enrolment = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $enrolment->update_user_enrol($instance, $learner->id, ENROL_USER_SUSPENDED);
        $this->dispatch();
        $this->assertSame(0, $DB->count_records(store::LEDGER));
    }

    /**
     * Administrative completion overrides are ignored.
     */
    public function test_teacher_override_is_ignored(): void {
        global $DB;
        [$course, $learner] = $this->course();
        $cm = $this->activity($course);
        $completion = new \completion_info($course);
        $completion->update_state($cm, COMPLETION_COMPLETE, $learner->id, true);
        $this->dispatch();
        $this->assertSame(0, $DB->count_records(store::LEDGER));
    }

    /**
     * Exact rule precedence, course daily cap and rule window are enforced.
     */
    public function test_limits_and_exact_rule_priority(): void {
        global $DB;
        [$course, $learner] = $this->course([], ['dailycap' => 15], ['maxdaily' => 1]);
        $exact = $this->activity($course);
        $generic = $this->activity($course);
        $future = $this->activity($course);
        $id = $this->rule($course->id, ['sourceid' => (int)$exact->id, 'points' => 15]);
        $this->rule($course->id, ['sourceid' => (int)$future->id, 'starts' => time() + DAYSECS]);
        $this->complete($course, $exact, $learner);
        $this->dispatch();
        $this->complete($course, $generic, $learner);
        $this->dispatch();
        $this->complete($course, $future, $learner);
        $this->dispatch();
        $row = $DB->get_record(store::LEDGER, ['courseid' => $course->id, 'userid' => $learner->id], '*', MUST_EXIST);
        $this->assertSame($id, (int)$row->ruleid);
        $this->assertSame(15, (int)$row->points);
    }

    /**
     * Native course completion earns once.
     */
    public function test_core_course_completion(): void {
        [$course, $learner] = $this->course([], [], ['kind' => 'course']);
        $record = new \completion_completion(['userid' => $learner->id, 'course' => $course->id]);
        $record->mark_complete();
        $this->assertCount(1, manager::get_adhoc_tasks(task\award_completion::class));
        $this->dispatch();
        $this->assertSame(10, goals::totals($course->id, $learner->id)['points']);
    }

    /**
     * Exclusion prevents awards and removes public alias consent.
     */
    public function test_excluded_participant_and_opt_in(): void {
        [$course, $learner] = $this->course();
        $this->setUser($learner);
        ranking::consent($course->id, true);
        $this->assertNotEmpty(store::player($course->id, $learner->id)->alias);
        $this->setAdminUser();
        admin::exclude($course->id, $learner->id, true);
        $this->complete($course, $this->activity($course), $learner);
        $this->dispatch();
        $this->assertSame(0, goals::totals($course->id, $learner->id)['points']);
        $this->assertEmpty(store::player($course->id, $learner->id)->alias);
        $this->assertSame([[], []], ranking::read($course));
    }

    /**
     * Individual and team achievements require distinct verified source records.
     */
    public function test_team_goal_and_badge_are_claimed_once(): void {
        global $DB;
        [$course, $first, $second] = $this->course();
        $grouping = groups_create_grouping((object)['courseid' => $course->id, 'name' => 'Teams']);
        $group = groups_create_group((object)['courseid' => $course->id, 'name' => 'Team A']);
        groups_assign_grouping($grouping, $group);
        groups_add_member($group, $first->id);
        groups_add_member($group, $second->id);
        admin::course($course->id, (object)['enabled' => 1, 'xplevel' => 20, 'dailycap' => 0, 'groupingid' => $grouping]);
        $badge = $this->goal($course->id);
        $challenge = $this->goal($course->id, ['kind' => 'challenge', 'scope' => 'team', 'target' => 20]);
        $cm = $this->activity($course);
        $this->complete($course, $cm, $first);
        $this->complete($course, $cm, $second);
        $this->dispatch();
        $this->setUser($first);
        $this->assertSame((int)$group, team::membership(store::configuration($course->id), $first->id));
        $this->assertSame(goals::claim($course->id, $badge), goals::claim($course->id, $badge));
        $this->assertSame(goals::claim($course->id, $challenge), goals::claim($course->id, $challenge));
        $this->assertSame(2, $DB->count_records(store::GRANT));
    }

    /**
     * Reward stock and spendable points remain consistent on repeated requests.
     */
    public function test_reward_stock_and_balance(): void {
        [$course, $first, $second] = $this->course();
        $reward = $this->goal($course->id, ['kind' => 'reward', 'cost' => 7, 'stock' => 1]);
        $cm = $this->activity($course);
        $this->complete($course, $cm, $first);
        $this->complete($course, $cm, $second);
        $this->dispatch();
        $this->setUser($first);
        $receipt = goals::claim($course->id, $reward);
        $this->assertSame($receipt, goals::claim($course->id, $reward));
        $this->assertSame(['points' => 10, 'xp' => 25, 'balance' => 3], goals::totals($course->id, $first->id));
        $this->setAdminUser();
        goals::fulfil($course->id, $receipt);
        $this->setUser($second);
        $this->expectExceptionMessage(access::text('claimunavailable'));
        goals::claim($course->id, $reward);
    }

    /**
     * Ranking requires viewer opt-in and never includes unconsenting learners.
     */
    public function test_ranking_consent_and_scope(): void {
        [$course, $first, $second] = $this->course();
        $cm = $this->activity($course);
        $this->complete($course, $cm, $first);
        $this->complete($course, $cm, $second);
        $this->dispatch();
        $this->setUser($first);
        $this->assertSame([[], []], ranking::read($course));
        ranking::consent($course->id, true);
        [$rows] = ranking::read($course);
        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('userid', $rows[0]);
        $this->assertStringNotContainsString($first->firstname, $rows[0]['label']);
        $this->setUser($second);
        ranking::consent($course->id, true);
        $this->assertCount(2, ranking::read($course)[0]);
        ranking::consent($course->id, false);
        $this->assertSame([[], []], ranking::read($course));
    }

    /**
     * Consent cannot reveal participants in another separated group.
     */
    public function test_separate_groups_limit_rankings(): void {
        [$course, $first, $second] = $this->course(['groupmode' => SEPARATEGROUPS]);
        $grouping = groups_create_grouping((object)['courseid' => $course->id, 'name' => 'Separated teams']);
        foreach ([$first, $second] as $index => $user) {
            $group = groups_create_group((object)['courseid' => $course->id, 'name' => 'Team ' . $index]);
            groups_assign_grouping($grouping, $group);
            groups_add_member($group, $user->id);
        }
        admin::course($course->id, (object)[
            'enabled' => 1, 'rankingenabled' => 1, 'xplevel' => 20, 'dailycap' => 0, 'groupingid' => $grouping,
        ]);
        $cm = $this->activity($course);
        foreach ([$first, $second] as $user) {
            $this->complete($course, $cm, $user);
            ranking::consent($course->id, true);
        }
        $this->dispatch();
        $this->setUser($first);
        [$rows, $teams] = ranking::read($course);
        $this->assertCount(1, $rows);
        $alias = strtoupper(store::player($course->id, $first->id)->alias);
        $this->assertSame(access::text('playeralias', $alias), $rows[0]['label']);
        $this->assertSame([], $teams);
    }

    /**
     * Missing Learning Path integration cannot manufacture evidence.
     */
    public function test_learning_path_fails_closed(): void {
        [$course, $learner] = $this->course([], ['pathenabled' => 1], ['kind' => 'path', 'sourceid' => 15]);
        $this->assertFalse(engine::award($course->id, $learner->id, 'path', 15, 0, time()));
    }

    /**
     * Erasure removes preference, points and pending native tasks.
     */
    public function test_privacy_erasure_is_course_scoped(): void {
        global $DB;
        [$course, $learner] = $this->course();
        $this->complete($course, $this->activity($course), $learner);
        $this->dispatch();
        $this->complete($course, $this->activity($course), $learner);
        ranking::consent($course->id, true);
        $context = context_course::instance($course->id);
        $contexts = privacy\provider::get_contexts_for_userid($learner->id)->get_contextids();
        $this->assertContains((int)$context->id, array_map('intval', $contexts));
        privacy\provider::delete_data_for_user(new approved_contextlist($learner, access::C, [$context->id]));
        $this->assertSame([], manager::get_adhoc_tasks(task\award_completion::class));
        $this->assertFalse($DB->record_exists(store::PLAYER, ['courseid' => $course->id, 'userid' => $learner->id]));
        $this->assertSame(0, goals::totals($course->id, $learner->id)['points']);
    }
}
