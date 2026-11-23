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

namespace tool_skillupscoursefactory;

use core_privacy\local\request\approved_contextlist;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_skillupscoursefactory\privacy\provider;

/**
 * Course provisioning, restoration, input validation and interruption tests.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(importer::class)]
#[CoversClass(validator::class)]
#[CoversClass(provisioner::class)]
#[CoversClass(jobs::class)]
#[CoversClass(worker::class)]
#[CoversClass(ui::class)]
#[CoversClass(support::class)]
#[CoversClass(provider::class)]
#[CoversClass(task\dispatch::class)]
final class factory_test extends \advanced_testcase {
    /**
     * Create a realistic test proposal.
     */
    private function payload(int $category, string $code = 'NEW-COURSE'): array {
        return ['fullname' => 'A course request', 'shortname' => $code, 'category' => (string)$category,
            'startdate' => '2027-01-04', 'enddate' => '2027-02-19'];
    }

    /**
     * Find the only row for a test operation.
     */
    private function row(int $job): \stdClass {
        global $DB;
        return $DB->get_record(jobs::ROW, ['jobid' => $job], '*', MUST_EXIST);
    }

    /**
     * CSV, UTF-8 and spreadsheet formula export defenses.
     */
    public function test_csv_import(): void {
        $file = make_request_directory() . '/import.csv';
        file_put_contents($file, "\xef\xbb\xbffullname,shortname,category\r\n\"Orientación, nivel 1\",F1,4\r\n");
        $this->assertSame('Orientación, nivel 1', importer::read($file, 'csv')[2]['fullname']);
        $this->assertSame("'=1+1", ui::csv_safe('=1+1'));
        $this->assertSame('COURSE-1', ui::csv_safe('COURSE-1'));
    }

    /**
     * Read native XLSX rows and reject formula cells without calculation.
     */
    public function test_xlsx_import(): void {
        $file = make_request_directory() . '/import.xlsx';
        $writer = new Writer();
        $writer->openToFile($file);
        $writer->addRow(Row::fromValues(['fullname', 'shortname', 'category', 'startdate']));
        $date = new DateTimeCell(new \DateTimeImmutable('2027-01-04'), (new Style())->setFormat('yyyy-mm-dd'));
        $writer->addRow(new Row([...Row::fromValues(['New course', 'XLSX-1', 7])->getCells(), $date]));
        $writer->close();
        $this->assertSame('2027-01-04', importer::read($file, 'xlsx')[2]['startdate']);
        $file = make_request_directory() . '/formula.xlsx';
        $writer = new Writer();
        $writer->openToFile($file);
        $writer->addRow(Row::fromValues(['fullname', 'shortname', 'category']));
        $writer->addRow(new Row([new FormulaCell('=1+1', null), ...Row::fromValues(['XLSX-1', 7])->getCells()]));
        $writer->close();
        $this->expectExceptionMessage(support::text('formula'));
        importer::read($file, 'xlsx');
    }

    /**
     * Preview reports duplicates and dates without creating a course.
     */
    public function test_preview_is_read_only_and_validates_duplicates(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $records = [
            2 => $this->payload($category->id, 'COURSE'),
            3 => $this->payload($category->id, 'course'),
            4 => array_replace($this->payload($category->id), ['startdate' => '2027-02-30']),
        ];
        $data = validator::preview($records, (int)$USER->id, 'UTC');
        $this->assertSame('', $data[2]['error']);
        $this->assertSame('duplicate', $data[3]['error']);
        $this->assertSame('invaliddate', $data[4]['error']);
        $id = jobs::draft($records, 10, 0, 'UTC');
        $this->assertSame('draft', jobs::visible($id)->state);
        worker::run($id);
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'COURSE']));
    }

    /**
     * Create a published course with the selected instructor and dates exactly once.
     */
    public function test_creation_and_manual_enrollment(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $teacher = $this->getDataGenerator()->create_user();
        $coordinator = $this->getDataGenerator()->create_user();
        $role = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        set_config('coordinatorrole', $role, support::C);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $fieldcategory = $generator->create_category();
        $generator->create_field(['categoryid' => $fieldcategory->get('id'), 'shortname' => 'factoryperiod']);
        set_config('periodfield', 'factoryperiod', support::C);
        $payload = $this->payload($category->id) + [
            'teachers' => (string)$teacher->id, 'coordinators' => (string)$coordinator->id,
            'idnumber' => 'YEAR-1', 'visible' => '1', 'period' => '2027-A',
        ];
        $id = jobs::draft([1 => $payload], 10, 0, 'America/Santo_Domingo');
        $this->assertSame('pending', $this->row($id)->state, $this->row($id)->errorcode);
        jobs::command($id, 'confirm');
        worker::run($id);
        $row = $this->row($id);
        $this->assertSame('done', $row->state, $row->errorcode);
        $course = get_course($row->courseid);
        $this->assertEquals(1, $course->visible);
        $this->assertEquals((new \DateTimeImmutable('2027-01-04T00:00:00-04:00'))->getTimestamp(), $course->startdate);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $teacher, '', true));
        $assignment = get_user_roles(\context_course::instance($course->id), $coordinator->id, false);
        $this->assertContains((int)$role, array_map('intval', array_column($assignment, 'roleid')));
        $customfields = \core_course\customfield\course_handler::create()->export_instance_data_object($course->id);
        $this->assertSame('2027-A', $customfields->factoryperiod);
        worker::run($id);
        $this->assertEquals(1, $DB->count_records('course', ['idnumber' => 'YEAR-1']));
    }

    /**
     * Backup and restore actual activities and their registered files without enrolled learners.
     */
    public function test_clone_preserves_content_without_participants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_course(['fullname' => 'Source course']);
        $category = $this->getDataGenerator()->create_category();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $source->id, 'student');
        $page = $this->getDataGenerator()->create_module('page', [
            'name' => 'Orientation', 'course' => $source->id, 'contentformat' => FORMAT_HTML,
            'content' => '<p>Course content</p><a href="@@PLUGINFILE@@/fixture.txt">Read</a>',
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($page->cmid)->id, 'component' => 'mod_page',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'fixture.txt',
        ], 'Clone me');
        $operation = jobs::draft([1 => $this->payload($category->id, 'CLONE') + ['source' => (string)$source->id]], 10, 0, 'UTC');
        $this->assertSame('pending', $this->row($operation)->state, $this->row($operation)->errorcode);
        jobs::command($operation, 'confirm');
        worker::run($operation);
        $row = $this->row($operation);
        $this->assertSame('done', $row->state, $row->errorcode);
        $modules = get_fast_modinfo((int)$row->courseid)->get_instances_of('page');
        $this->assertCount(1, $modules);
        $context = reset($modules)->context;
        $file = get_file_storage()->get_file($context->id, 'mod_page', 'content', 0, '/', 'fixture.txt');
        $this->assertSame('Clone me', $file->get_content());
        $this->assertFalse(is_enrolled(\context_course::instance($row->courseid), $student));
    }

    /**
     * A row that failed after reserving a destination uses that same destination on retry.
     */
    public function test_selective_retry_reuses_recorded_destination(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $teacher = $this->getDataGenerator()->create_user();
        $id = jobs::draft([1 => $this->payload($category->id) + ['teachers' => (string)$teacher->id]], 10, 0, 'UTC');
        jobs::command($id, 'confirm');
        $row = $this->row($id);
        $owned = provisioner::prepare($row, json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR));
        $DB->set_field('user', 'suspended', 1, ['id' => $teacher->id]);
        worker::run($id);
        $this->assertSame('failed', $this->row($id)->state);
        $DB->set_field('user', 'suspended', 0, ['id' => $teacher->id]);
        jobs::command($id, 'retry', (int)$row->id);
        worker::run($id);
        $this->assertSame('done', $this->row($id)->state, $this->row($id)->errorcode);
        $this->assertEquals($owned, $this->row($id)->courseid);
        $this->assertEquals(1, $DB->count_records('course', ['shortname' => 'NEW-COURSE']));
    }

    /**
     * Interrupted restores require a review, and remain attached to the hidden original destination.
     */
    public function test_interrupted_restore_does_not_replay(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $source = $this->getDataGenerator()->create_course();
        $id = jobs::draft([1 => $this->payload($category->id) + ['source' => (string)$source->id]], 10, 0, 'UTC');
        jobs::command($id, 'confirm');
        $row = $this->row($id);
        $courseid = provisioner::prepare($row, json_decode($row->payload, true, 12, JSON_THROW_ON_ERROR));
        $DB->set_field(jobs::ROW, 'phase', 'restoring', ['id' => $row->id]);
        worker::run($id);
        $this->assertSame('attention', $this->row($id)->state);
        $this->assertFalse((bool)get_course($courseid)->visible);
        $this->expectExceptionMessage(support::text('invalidaction'));
        jobs::command($id, 'retry', (int)$row->id);
    }

    /**
     * Future work stays pending and cancellation prevents course creation.
     */
    public function test_schedule_and_cancel(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $id = jobs::draft([1 => $this->payload($category->id)], 10, time() + DAYSECS, 'UTC');
        jobs::command($id, 'confirm');
        (new task\dispatch())->execute();
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(task\process_job::class));
        worker::run($id);
        $this->assertSame('pending', $this->row($id)->state);
        jobs::command($id, 'cancel');
        worker::run($id);
        $this->assertSame('cancelled', $this->row($id)->state);
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'NEW-COURSE']));
    }

    /**
     * Other users cannot resolve a known operation or execute it after authorization disappears.
     */
    public function test_job_scope_and_queued_revocation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $id = jobs::draft([1 => $this->payload($category->id)], 10, 0, 'UTC');
        jobs::command($id, 'confirm');
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field(jobs::JOB, 'userid', $user->id, ['id' => $id]);
        worker::run($id);
        $this->assertSame('denied', $this->row($id)->errorcode);
        $this->setUser($user);
        $this->expectExceptionMessage(support::text('denied'));
        jobs::visible($id);
    }

    /**
     * Privacy erasure revokes pending enrollment requests and preserves Moodle courses.
     */
    public function test_privacy_removes_staff_ids(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $teacher = $this->getDataGenerator()->create_user();
        $id = jobs::draft([1 => $this->payload($category->id) + ['teachers' => (string)$teacher->id]], 10, 0, 'UTC');
        $context = \context_system::instance();
        $approved = new approved_contextlist($teacher, support::C, [$context->id]);
        $contexts = provider::get_contexts_for_userid($teacher->id)->get_contextids();
        $this->assertContains((int)$context->id, array_map('intval', $contexts));
        provider::delete_data_for_user($approved);
        $row = $this->row($id);
        $this->assertSame('cancelled', $row->state);
        $this->assertEmpty(json_decode($row->payload, true)['staff']);
    }

    /**
     * Use site capacity limits for spreadsheet requests.
     */
    public function test_batch_sizes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('maxbatch', 500, support::C);
        $this->assertSame([10, 50, 100, 200, 500], support::sizes());
        set_config('maxbatch', 100, support::C);
        $this->assertSame([10, 50, 100], support::sizes());
    }

    /**
     * Separate confirmed jobs cannot reserve the same Moodle course identity.
     */
    public function test_duplicate_across_confirmed_jobs(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $first = jobs::draft([1 => $this->payload($category->id)], 10, 0, 'UTC');
        $second = jobs::draft([1 => $this->payload($category->id)], 10, 0, 'UTC');
        jobs::command($first, 'confirm');
        jobs::command($second, 'confirm');
        worker::run($first);
        worker::run($second);
        $this->assertSame('done', $this->row($first)->state, $this->row($first)->errorcode);
        $this->assertSame('duplicate', $this->row($second)->errorcode);
        $this->assertEquals(1, $DB->count_records('course', ['shortname' => 'NEW-COURSE']));
    }
}
