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

namespace local_skillupsscheduler;

use core_privacy\local\request\approved_contextlist;
use local_skillupsscheduler\privacy\provider;

/**
 * Verify reservations, scope, Moodle integrations and personal data.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scheduler_test extends \advanced_testcase {
    /**
     * Create a course, enrollments and campus resources for the scenario.
     *
     * @return array
     */
    private function setup_schedule(): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');
        require_once($CFG->dirroot . '/local/skillupsscheduler/lib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $g = $this->getDataGenerator();
        $category = $g->create_category();
        $course = $g->create_course(['category' => $category->id]);
        $teacher = $g->create_and_enrol($course, 'editingteacher');
        $substitute = $g->create_and_enrol($course, 'teacher');
        $student = $g->create_and_enrol($course, 'student');
        $outside = $g->create_and_enrol($course, 'student');
        $group = $g->create_group(['courseid' => $course->id]);
        $other = $g->create_group(['courseid' => $course->id]);
        $g->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $g->create_group_member(['groupid' => $other->id, 'userid' => $outside->id]);
        $site = catalog::save('site', (object)['categoryid' => $category->id, 'name' => 'North', 'timezone' => 'America/New_York']);
        $parent = catalog::save('cycle', (object)[
            'siteid' => $site, 'name' => 'Academic year', 'startday' => '2030-01-01', 'endday' => '2030-12-31',
        ]);
        $cycle = catalog::save('cycle', (object)[
            'siteid' => $site, 'name' => 'Spring', 'parentid' => $parent, 'startday' => '2030-02-01', 'endday' => '2030-06-30',
        ]);
        $physical = catalog::save('room', (object)['siteid' => $site, 'name' => 'Studio', 'kind' => 'physical', 'capacity' => 10]);
        $virtual = catalog::save('room', (object)[
            'siteid' => $site, 'name' => 'Meeting', 'kind' => 'virtual', 'capacity' => 20,
            'url' => 'https://meet.example.invalid/studio',
        ]);
        $block = catalog::save('block', (object)[
            'siteid' => $site, 'name' => 'Workshop', 'shiftname' => 'Morning',
            'weekday' => 1, 'begin' => '08:00', 'finish' => '12:00',
        ]);
        $values = [
            'courseid' => $course->id, 'cycleid' => $cycle, 'title' => 'Workshop', 'mode' => 'hybrid', 'teacherid' => $teacher->id,
            'groupid' => $group->id, 'roomid' => $physical, 'virtualid' => $virtual, 'blockid' => $block,
            'day' => '2030-03-04', 'begin' => '09:00', 'finish' => '10:00', 'count' => 1, 'interval' => 7, 'publish' => 1,
        ];
        return compact(
            'site',
            'cycle',
            'parent',
            'course',
            'category',
            'teacher',
            'student',
            'outside',
            'substitute',
            'other',
            'values',
        );
    }

    /**
     * Verify recurrence keeps wall time and fails atomically.
     */
    public function test_recurrence_keeps_wall_time_and_fails_atomically(): void {
        global $DB;
        $f = $this->setup_schedule();
        $ids = schedule::save($f['site'], (object)(['count' => 2] + $f['values']));
        $first = store::get(store::SLOT, $ids[0]);
        $second = store::get(store::SLOT, $ids[1]);
        $this->assertSame(7 * DAYSECS - HOURSECS, (int)$second->starts - (int)$first->starts);
        $this->assertSame($first->series, $second->series);
        $periodslots = planner::slots($first->starts - 60, $second->ends + 60, ['cycleid' => $f['parent']]);
        $this->assertCount(2, iterator_to_array($periodslots));
        $this->assertTrue($DB->record_exists('event', ['component' => scope::C, 'id' => $second->eventid]));
        try {
            schedule::save($f['site'], (object)(['day' => '2030-02-25', 'count' => 2] + $f['values']));
            $this->fail('The second occurrence must prevent the whole batch.');
        } catch (\moodle_exception $e) {
            $this->assertSame('publicationblocked', $e->errorcode);
        }
        $this->assertSame(2, $DB->count_records(store::SLOT));
        $this->assertSame(2, $DB->count_records(store::AUDIT));
        $this->assertSame(2, $DB->count_records('event', ['component' => scope::C]));
    }

    /**
     * Verify conflicts cover resources assignment capacity and period.
     */
    public function test_conflicts_cover_resources_assignment_capacity_and_period(): void {
        $f = $this->setup_schedule();
        schedule::save($f['site'], (object)$f['values']);
        $draft = schedule::save($f['site'], (object)(['publish' => 0] + $f['values']))[0];
        $this->assertEqualsCanonicalizing(
            ['teacherbusy', 'roombusy', 'virtualbusy', 'groupbusy'],
            conflicts::detect(store::get(store::SLOT, $draft), true),
        );
        $different = store::get(store::SLOT, $draft);
        $different->teacherid = $f['substitute']->id;
        $different->groupid = $f['other']->id;
        $this->assertEqualsCanonicalizing(['roombusy', 'virtualbusy'], conflicts::detect($different, true));
        $tiny = catalog::save('room', (object)['siteid' => $f['site'], 'name' => 'Tiny', 'kind' => 'physical', 'capacity' => 1]);
        $invalid = schedule::save($f['site'], (object)([
            'blockid' => 0, 'teacherid' => 0, 'groupid' => 0, 'roomid' => $tiny, 'day' => '2030-01-01', 'publish' => 0,
        ] + $f['values']))[0];
        $this->assertEqualsCanonicalizing(
            ['missingteacher', 'outsidecycle', 'overcapacity'],
            conflicts::detect(store::get(store::SLOT, $invalid)),
        );
        $next = schedule::save($f['site'], (object)(['begin' => '10:00', 'finish' => '11:00'] + $f['values']))[0];
        $this->assertGreaterThan(0, $next);
        $this->assertSame([], planner::courses_without_teachers($f['site'], (int)$f['course']->id));
        $unstaffed = $this->getDataGenerator()->create_course(['category' => $f['category']->id, 'fullname' => 'Unstaffed course']);
        $this->assertSame('Unstaffed course', planner::courses_without_teachers($f['site'], (int)$unstaffed->id)[0]['course']);
    }

    /**
     * Verify campus capability and group visibility apply to calendar.
     */
    public function test_campus_capability_and_group_visibility_apply_to_calendar(): void {
        $f = $this->setup_schedule();
        $slot = store::get(store::SLOT, schedule::save($f['site'], (object)$f['values'])[0]);
        $event = \calendar_event::load($slot->eventid);
        $this->assertTrue(scope::visible($slot, (int)$f['student']->id));
        $this->assertFalse(scope::visible($slot, (int)$f['outside']->id));
        $this->assertTrue(local_skillupsscheduler_core_calendar_is_event_visible($event, (int)$f['student']->id));
        $this->assertFalse(local_skillupsscheduler_core_calendar_is_event_visible($event, (int)$f['outside']->id));
        $g = $this->getDataGenerator();
        $reader = $g->create_user();
        $role = $g->create_role();
        $context = \context_coursecat::instance($f['category']->id);
        assign_capability(scope::CAP . 'consult', CAP_ALLOW, $role, $context->id);
        role_assign($role, $reader->id, $context->id);
        $this->assertFalse(scope::visible($slot, (int)$reader->id));
        catalog::save('grant', (object)['siteid' => $f['site'], 'userid' => $reader->id]);
        $this->assertTrue(scope::visible($slot, (int)$reader->id));
        $other = catalog::save('site', (object)['categoryid' => $f['category']->id, 'name' => 'West', 'timezone' => 'UTC']);
        $this->assertFalse(scope::staff(store::get(store::SITE, $other), 'consult', (int)$reader->id));
        $this->setUser($reader);
        $this->expectException(\moodle_exception::class);
        schedule::cancel((int)$slot->id, (int)$slot->revision, 'Forbidden');
    }

    /**
     * Verify revision changes and cancellation update calendar and queue.
     */
    public function test_revision_changes_and_cancellation_update_calendar_and_queue(): void {
        global $DB;
        $f = $this->setup_schedule();
        $id = schedule::save($f['site'], (object)$f['values'])[0];
        $old = store::get(store::SLOT, $id);
        $edited = (object)([
            'id' => $id, 'revision' => 1, 'begin' => '10:00', 'finish' => '11:00',
            'teacherid' => $f['substitute']->id, 'reason' => 'Room unavailable',
        ] + $f['values']);
        schedule::save($f['site'], $edited);
        $current = store::get(store::SLOT, $id);
        $this->assertEquals($old->eventid, $current->eventid);
        $this->assertEquals($current->starts, $DB->get_field('event', 'timestart', ['id' => $old->eventid]));
        $this->assertSame(
            $edited->reason,
            $DB->get_field(store::AUDIT, 'reason', ['slotid' => $id, 'operation' => 'substituted'], MUST_EXIST),
        );
        try {
            schedule::save($f['site'], $edited);
            $this->fail('An outdated form must fail.');
        } catch (\moodle_exception $e) {
            $this->assertSame('stale', $e->errorcode);
        }
        schedule::cancel($id, 2, 'Campus closed');
        $this->assertSame('cancelled', store::get(store::SLOT, $id)->status);
        $this->assertFalse($DB->record_exists('event', ['id' => $old->eventid]));
        $this->assertSame(3, $DB->count_records(store::QUEUE, ['slotid' => $id]));
        $replacement = schedule::save($f['site'], (object)$f['values']);
        $this->assertNotEmpty($replacement);
    }

    /**
     * Verify messaging sends to affected users and drains job.
     */
    public function test_messaging_sends_to_affected_users_and_drains_job(): void {
        global $DB;
        $f = $this->setup_schedule();
        set_config('messaging', 1);
        $sink = $this->redirectMessages();
        $id = schedule::save($f['site'], (object)$f['values'])[0];
        notices::run();
        $sent = $sink->get_messages();
        $sink->close();
        $this->assertFalse($DB->record_exists(store::QUEUE, ['slotid' => $id]));
        $this->assertEqualsCanonicalizing(
            [$f['teacher']->id, $f['student']->id],
            array_map(static fn($message) => $message->useridto, $sent),
        );
    }

    /**
     * Verify privacy erasure unpublishes future assignment.
     */
    public function test_privacy_erasure_unpublishes_future_assignment(): void {
        global $DB;
        $f = $this->setup_schedule();
        $id = schedule::save($f['site'], (object)$f['values'])[0];
        $slot = store::get(store::SLOT, $id);
        $teacher = $f['teacher'];
        catalog::save('grant', (object)['siteid' => $f['site'], 'userid' => $teacher->id]);
        $this->assertCount(2, provider::get_contexts_for_userid((int)$teacher->id)->get_contextids());
        $ids = [\context_system::instance()->id, \context_course::instance($slot->courseid)->id];
        provider::delete_data_for_user(new approved_contextlist($teacher, scope::C, $ids));
        $this->assertSame('draft', store::get(store::SLOT, $id)->status);
        $this->assertFalse($DB->record_exists(store::GRANT, ['userid' => $teacher->id]));
        $this->assertFalse($DB->record_exists('event', ['id' => $slot->eventid]));
        $this->assertCount(0, provider::get_contexts_for_userid((int)$teacher->id)->get_contextids());
    }

    /**
     * Verify ics folds and escapes text and skips drafts.
     */
    public function test_ics_folds_and_escapes_text_and_skips_drafts(): void {
        $f = $this->setup_schedule();
        $id = schedule::save($f['site'], (object)(['title' => str_repeat('á', 30) . ";test,\r\nINJECTED"] + $f['values']))[0];
        $slot = store::get(store::SLOT, $id);
        $draft = clone $slot;
        $draft->status = 'draft';
        $result = calendar::export([$slot, $draft]);
        $this->assertSame(1, substr_count($result, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART:20300304T140000Z', $result);
        $unfolded = str_replace("\r\n ", '', $result);
        $this->assertStringContainsString('\;test\,\nINJECTED', $unfolded);
        foreach (explode("\r\n", $result) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }

    /**
     * Verify unrelated activity dates and course removal.
     */
    public function test_unrelated_activity_dates_and_course_removal(): void {
        global $DB;
        $f = $this->setup_schedule();
        $due = time() + WEEKSECS;
        $assignment = $this->getDataGenerator()->create_module('assign', [
            'course' => $f['course']->id, 'duedate' => $due,
        ]);
        $id = schedule::save($f['site'], (object)$f['values'])[0];
        $this->assertEquals($due, $DB->get_field('assign', 'duedate', ['id' => $assignment->id]));
        $slot = store::get(store::SLOT, $id);
        $this->assertTrue(delete_course($f['course'], false));
        $this->assertFalse($DB->record_exists(store::SLOT, ['id' => $id]));
        $this->assertFalse($DB->record_exists(store::AUDIT, ['slotid' => $id]));
        $this->assertFalse($DB->record_exists(store::QUEUE, ['slotid' => $id]));
        $this->assertFalse($DB->record_exists('event', ['id' => $slot->eventid]));
    }

    /**
     * Verify ambiguous wall time is rejected.
     */
    public function test_ambiguous_wall_time_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        catalog::local_time('2030-11-03', '01:30', 'America/New_York');
    }
}
