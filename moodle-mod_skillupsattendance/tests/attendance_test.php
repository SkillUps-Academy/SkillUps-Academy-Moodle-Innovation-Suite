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

namespace mod_skillupsattendance;

use core_privacy\local\request\approved_contextlist;
use mod_skillupsattendance\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Verify session scheduling, scoped enrollment, attendance and privacy.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(activity::class)]
#[CoversClass(session::class)]
#[CoversClass(marks::class)]
#[CoversClass(justification::class)]
#[CoversClass(report::class)]
#[CoversClass(grades::class)]
#[CoversClass(alerts::class)]
#[CoversClass(provider::class)]
final class attendance_test extends \advanced_testcase {
    /**
     * Create an authorized activity and two actively enrolled students.
     *
     * @param array $settings Activity overrides
     * @return array Module and users
     */
    private function fixture(array $settings = []): array {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $first = $generator->create_user();
        $second = $generator->create_user();
        $generator->enrol_user($first->id, $course->id, 'student');
        $generator->enrol_user($second->id, $course->id, 'student');
        $values = $settings + ['course' => $course->id, 'name' => 'Attendance fixture'];
        $record = $generator->create_module('skillupsattendance', $values);
        return [activity::by_instance($record->id), $first, $second];
    }

    /**
     * Create a session using the same service used by Moodle Forms.
     *
     * @param activity $activity Module
     * @param array $values Overrides
     * @return int Session ID
     */
    private function schedule(activity $activity, array $values = []): int {
        return session::save($activity, (object)($values + [
            'session' => 0, 'revision' => 0, 'name' => 'Attendance sample', 'teacherid' => get_admin()->id,
            'method' => 'manual', 'modality' => 'onsite', 'groupid' => 0, 'starts' => time() - 600,
            'ends' => time() + 900, 'early' => 0, 'grace' => 0, 'late' => 5, 'extra' => 0, 'checkout' => 1,
            'frequency' => 'week', 'occurrences' => 1, 'newpin' => '',
        ]));
    }

    /**
     * Weekly schedules preserve local wall time across daylight saving changes.
     */
    public function test_recurrence_uses_local_time_and_native_calendar(): void {
        global $DB;
        $this->resetAfterTest();
        [$activity] = $this->fixture();
        $zone = new \DateTimeZone('America/New_York');
        $start = new \DateTimeImmutable('2026-03-04 09:00', $zone);
        $end = $start->modify('+1 hour');
        $dates = session::dates($start->getTimestamp(), $end->getTimestamp(), 2, 'week', $zone);
        $next = (new \DateTimeImmutable('@' . $dates[1][0]))->setTimezone($zone);
        $this->assertSame('09:00', $next->format('H:i'));
        $this->assertEquals(7 * DAYSECS - HOURSECS, $dates[1][0] - $dates[0][0]);
        $id = $this->schedule($activity, ['occurrences' => 2]);
        $this->assertEquals(2, $DB->count_records(store::SESSION, ['attendanceid' => $activity->record->id]));
        $first = store::session($activity, $id);
        $this->assertTrue($DB->record_exists('event', ['id' => $first->eventid, 'modulename' => 'skillupsattendance']));
        $replacement = $first->starts + HOURSECS;
        $this->schedule($activity, [
            'session' => $id, 'revision' => $first->revision, 'starts' => $replacement, 'ends' => $first->ends + HOURSECS,
        ]);
        $this->assertEquals($replacement, $DB->get_field('event', 'timestart', ['id' => $first->eventid]));
        session::cancel($activity, $id);
        $this->assertFalse($DB->record_exists('event', ['id' => $first->eventid]));
        $this->assertSame('cancelled', store::session($activity, $id)->state);
    }

    /**
     * A learner must be in the session group. Scanning and logging out are idempotent.
     */
    public function test_temporary_qr_and_group_isolation(): void {
        global $DB;
        $this->resetAfterTest();
        [$activity, $first, $second] = $this->fixture(['groupmode' => SEPARATEGROUPS]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $activity->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $first->id]);
        $id = $this->schedule($activity, ['method' => 'qr', 'groupid' => $group->id]);
        $record = store::session($activity, $id);
        $now = time();
        $this->setUser($second);
        $this->assertFalse(session::visible($activity, $record));
        try {
            marks::self_record($activity, $id, '', marks::token($record, $now), now: $now);
            $this->fail('A learner outside the group recorded attendance');
        } catch (\moodle_exception $error) {
            $this->assertSame('closedwindow', $error->errorcode);
        }
        $this->setUser($first);
        try {
            marks::self_record($activity, $id, '', marks::token($record, $now - 180), now: $now);
            $this->fail('An expired QR token was accepted');
        } catch (\moodle_exception $error) {
            $this->assertSame('badcredential', $error->errorcode);
        }
        $this->assertSame('late', marks::self_record($activity, $id, '', marks::token($record, $now), now: $now));
        marks::self_record($activity, $id, '', marks::token($record, $now), now: $now + 1);
        marks::self_record($activity, $id, '', '', true, $now + 2);
        marks::self_record($activity, $id, '', '', true, $now + 3);
        $this->assertEquals(1, $DB->count_records(store::MARK, ['sessionid' => $id]));
        $mark = $DB->get_record(store::MARK, ['sessionid' => $id, 'userid' => $first->id], '*', MUST_EXIST);
        $this->assertEquals($now, $mark->timein);
        $this->assertEquals($now + 2, $mark->timeexit);
    }

    /**
     * The PIN is stored only as a password hash and bad submissions consume a short-lived attempt budget.
     */
    public function test_access_code_rate_limit(): void {
        global $DB;
        $this->resetAfterTest();
        [$activity, $first] = $this->fixture();
        $id = $this->schedule($activity, ['method' => 'code', 'late' => 20, 'newpin' => '654321']);
        $this->assertNotSame('654321', store::session($activity, $id)->pin);
        $this->setUser($first);
        $now = time();
        for ($i = 0; $i < 5; $i++) {
            try {
                marks::self_record($activity, $id, 'invalid', '', now: $now);
                $this->fail('An invalid access code was accepted');
            } catch (\moodle_exception $error) {
                $this->assertSame('badcredential', $error->errorcode);
            }
        }
        try {
            marks::self_record($activity, $id, '654321', '', now: $now);
            $this->fail('The retry budget was not enforced');
        } catch (\moodle_exception $error) {
            $this->assertSame('limited', $error->errorcode);
        }
        $this->assertSame('present', marks::self_record($activity, $id, '654321', '', now: $now + 301));
        $this->assertFalse($DB->record_exists(store::FAIL, ['sessionid' => $id]));
    }

    /**
     * Finalization snapshots absent learners and justification decisions protect file ownership.
     */
    public function test_justification_review_and_privacy(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        [$activity, $first, $second] = $this->fixture();
        $id = $this->schedule($activity, ['ends' => time() - 30]);
        $this->assertTrue(marks::finalize($activity, $id));
        $this->assertFalse(marks::finalize($activity, $id));
        $this->assertEquals(2, $DB->count_records(store::MARK, ['sessionid' => $id, 'state' => 'absent']));
        $this->setUser($first);
        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($first->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draft, 'filepath' => '/', 'filename' => 'sample.pdf', 'userid' => $first->id,
        ], "%PDF-1.4\nSynthetic evidence");
        $reason = justification::reasons($activity)[0];
        $request = justification::submit($activity, $id, $reason, 'Test evidence', $draft);
        [$details, $record] = justification::read($activity, $request);
        $this->assertTrue(justification::may_view($activity, $details, $record));
        $this->setUser($second);
        $this->assertFalse(justification::may_view($activity, $details, $record));
        $this->setAdminUser();
        justification::decide($activity, $request, 'approved', 'Reviewed');
        $this->assertSame('excused', $DB->get_field(store::MARK, 'state', ['sessionid' => $id, 'userid' => $first->id]));
        $this->assertEquals([$activity->context->id], provider::get_contexts_for_userid($first->id)->get_contextids());
        $owner = new approved_contextlist($first, activity::C, [$activity->context->id]);
        provider::delete_data_for_user($owner);
        $this->assertFalse($DB->record_exists(store::JUST, ['id' => $request]));
        $storage = get_file_storage();
        $this->assertFalse($storage->file_exists($activity->context->id, activity::C, 'evidence', $request, '/', 'sample.pdf'));
        $this->assertTrue($DB->record_exists(store::MARK, ['sessionid' => $id, 'userid' => $second->id]));
    }

    /**
     * Reports reconcile finalized states and only explicit approval reaches the gradebook.
     */
    public function test_report_and_explicit_grade_publication(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/gradelib.php');
        [$activity, $first, $second] = $this->fixture(['grades' => 1, 'minimum' => 75, 'consecutive' => 1]);
        $a = $this->schedule($activity, ['ends' => time() - 30, 'modality' => 'onsite']);
        $b = $this->schedule($activity, ['ends' => time() - 15, 'modality' => 'online']);
        marks::manual($activity, $a, [$first->id => 'late', $second->id => 'excused']);
        marks::finalize($activity, $a);
        marks::finalize($activity, $b);
        $totals = report::overview($activity);
        $this->assertEquals(50, $totals[$first->id]['percent']);
        $this->assertEquals(0, $totals[$second->id]['percent']);
        $this->assertContains('minimum', $totals[$first->id]['flags']);
        $this->assertEquals(0, report::overview($activity, ['modality' => 'online'])[$first->id]['percent']);
        $key = [
            'courseid' => $activity->course->id, 'itemmodule' => 'skillupsattendance', 'iteminstance' => $activity->record->id,
        ];
        $this->assertFalse($DB->record_exists('grade_items', $key));
        $this->assertSame(['published' => 2, 'skipped' => 0], grades::publish($activity));
        $item = \grade_item::fetch($key);
        $this->assertEquals(50, \grade_grade::fetch(['itemid' => $item->id, 'userid' => $first->id])->rawgrade);
        marks::manual($activity, $b, [$first->id => 'present']);
        $this->assertEquals(50, \grade_grade::fetch(['itemid' => $item->id, 'userid' => $first->id])->rawgrade);
        $this->assertEquals(100, report::overview($activity)[$first->id]['percent']);
        $this->assertSame("'=SUM(A1)", report::safe_cell('=SUM(A1)'));
    }

    /**
     * Course reports retain group restrictions in every included Moodle activity.
     */
    public function test_course_reports_keep_separate_group_scope(): void {
        global $DB;
        $this->resetAfterTest();
        [$module, $first, $second] = $this->fixture(['groupmode' => SEPARATEGROUPS]);
        $generator = $this->getDataGenerator();
        $a = $generator->create_group(['courseid' => $module->course->id]);
        $b = $generator->create_group(['courseid' => $module->course->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $first->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $second->id]);
        $reader = $generator->create_user();
        $generator->enrol_user($reader->id, $module->course->id, 'teacher');
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $reader->id]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $coursecontext = \context_course::instance($module->course->id);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $coursecontext->id);
        $other = $generator->create_module('skillupsattendance', [
            'course' => $module->course->id, 'groupmode' => SEPARATEGROUPS, 'name' => 'Second attendance',
        ]);
        $secondmodule = activity::by_instance($other->id);
        $due = time() - 30;
        $left = $this->schedule($module, ['groupid' => $a->id, 'ends' => $due]);
        $right = $this->schedule($secondmodule, ['groupid' => $b->id, 'ends' => $due]);
        marks::finalize($module, $left);
        marks::finalize($secondmodule, $right);
        $this->assertCount(2, report::overview($module, ['coursewide' => 1]));
        $this->setUser($reader);
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $module->context));
        $this->assertEquals(SEPARATEGROUPS, groups_get_activity_groupmode($module->cm));
        $authorized = report::overview($module, ['coursewide' => 1]);
        $this->assertEquals([$first->id], array_keys($authorized));
        $this->assertSame([], report::overview($module, ['coursewide' => 1, 'groupid' => $b->id]));
    }

    /**
     * Native Moodle duplication copies the schedule without passing on QR credentials.
     */
    public function test_native_activity_copy_remaps_sessions_and_credentials(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/course/lib.php');
        [$activity] = $this->fixture();
        $original = $this->schedule($activity, ['method' => 'qr', 'starts' => time() + 600, 'ends' => time() + 1200]);
        $old = store::session($activity, $original);
        $cm = get_fast_modinfo($activity->course)->get_cm($activity->cm->id);
        if (method_exists(\core_courseformat\local\cmactions::class, 'duplicate')) {
            $actions = new \core_courseformat\local\cmactions($activity->course);
            $new = $actions->duplicate($cm->id);
        } else {
            $new = duplicate_module($activity->course, $cm);
        }
        $this->assertNotNull($new);
        $record = $DB->get_record(store::SESSION, ['attendanceid' => $new->instance], '*', MUST_EXIST);
        $this->assertSame('qr', $record->method);
        $this->assertSame('scheduled', $record->state);
        $this->assertNotEmpty($record->qrkey);
        $this->assertNotSame($old->qrkey, $record->qrkey);
        $this->assertNotEquals($old->eventid, $record->eventid);
        $this->assertEquals(0, $DB->count_records(store::MARK, ['sessionid' => $record->id]));
    }

    /**
     * A persistent claim prevents repeated warnings when the same risk episode is still active.
     */
    public function test_alert_is_deduplicated(): void {
        global $DB;
        $this->resetAfterTest();
        [$activity, $first, $second] = $this->fixture(['alerts' => 1, 'consecutive' => 1]);
        $id = $this->schedule($activity, ['ends' => time() - 30]);
        marks::manual($activity, $id, [$second->id => 'present']);
        marks::finalize($activity, $id);
        $sink = $this->redirectMessages();
        $this->assertEquals(1, alerts::notify($activity));
        $DB->set_field(store::ACTIVITY, 'alertdirty', 1, ['id' => $activity->record->id]);
        $this->assertEquals(0, alerts::notify($activity));
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertEquals($first->id, $messages[0]->useridto);
    }
}
