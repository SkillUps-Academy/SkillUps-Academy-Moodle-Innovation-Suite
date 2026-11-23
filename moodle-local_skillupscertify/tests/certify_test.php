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

namespace local_skillupscertify;

use local_skillupscertify\credential\design;
use local_skillupscertify\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verify integrity, privacy, authorization and durable issuance.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access::class)]
#[CoversClass(batches::class)]
#[CoversClass(design::class)]
#[CoversClass(issuer::class)]
#[CoversClass(provider::class)]
#[CoversClass(store::class)]
#[CoversClass(verification::class)]
final class certify_test extends \advanced_testcase {
    /**
     * Preserve the issued file through template edits and revoke or replace the public credential.
     */
    public function test_archived_pdf_and_replacement(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('publicverification', 1, access::C);
        $data = $this->getDataGenerator();
        $course = $data->create_course(['fullname' => 'Original course']);
        $learner = $data->create_user(['firstname' => 'Privada', 'lastname' => 'Valdés']);
        $data->enrol_user($learner->id, $course->id, 'student');
        $template = store::save_template($course, 0, self::input());
        [$first, $created] = issuer::issue($course, $template, $learner->id);
        $this->assertTrue($created);
        $original = issuer::file($first)->get_content();
        $this->assertStringStartsWith('%PDF-', $original);
        $this->assertSame($first->pdfhash, hash('sha256', $original));
        $code = self::code($original);
        $public = verification::lookup($code);
        $this->assertSame(['status', 'fingerprint', 'reference'], array_keys($public));
        $this->assertSame('valid', $public['status']);
        $this->assertNull(verification::lookup(str_repeat('f', 48)));
        [$repeat, $created] = issuer::issue($course, $template, $learner->id);
        $this->assertFalse($created);
        $this->assertEquals($first->id, $repeat->id);
        store::save_template($course, $template, self::input(['title' => 'Updated title', 'revision' => 1, 'publicmeta' => 1]));
        $this->assertSame($original, issuer::file($first)->get_content());
        issuer::revoke($course, $first->id, 'Wrong edition');
        $this->assertSame('revoked', verification::lookup($code)['status']);
        $this->assertNull(issuer::issue($course, $template, $learner->id));
        [$second, $created] = issuer::issue($course, $template, $learner->id, previousid: $first->id);
        $this->assertTrue($created);
        $secondcode = self::code(issuer::file($second)->get_content());
        $this->assertNotSame($code, $secondcode);
        $view = verification::lookup($secondcode);
        $this->assertSame('Updated title', $view['title']);
        $this->assertArrayNotHasKey('recipient', $view);
        $this->assertArrayNotHasKey('reason', $view);
        $this->assertSame($original, issuer::file($first)->get_content());
        [$third] = issuer::issue($course, $template, $learner->id, previousid: $second->id);
        $this->assertSame('replaced', verification::lookup($secondcode)['status']);
        $this->assertEquals($second->id, $third->supersedes);
        $this->assertEquals(3, $DB->count_records(store::ISSUE, ['courseid' => $course->id]));
        store::save_template($course, $template, self::input(['title' => 'Metadata withdrawn', 'revision' => 2]));
        $this->assertArrayNotHasKey('title', verification::lookup($secondcode));
        set_config('publicverification', 0, access::C);
        $this->assertNull(verification::lookup($code));
    }

    /**
     * Require actual native completion, including during repeated and partial batch runs.
     */
    public function test_batch_does_not_issue_without_native_completion(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/completionlib.php');
        $data = $this->getDataGenerator();
        $course = $data->create_course(['enablecompletion' => 1]);
        $done = $data->create_user();
        $waiting = $data->create_user();
        $data->enrol_user($done->id, $course->id, 'student');
        $data->enrol_user($waiting->id, $course->id, 'student');
        $template = store::save_template($course, 0, self::input(['source' => 'course']));
        $this->assertNull(issuer::issue($course, $template, $done->id));
        (new \completion_completion(['course' => $course->id, 'userid' => $done->id]))->mark_complete(time() - 60);
        $batch = batches::queue($course, $template, 0);
        $revision = store::scoped(store::BATCH, $course->id, $batch)->revisionid;
        store::save_template($course, $template, self::input(['source' => 'manual', 'revision' => 1]));
        batches::process($batch);
        batches::process($batch);
        $this->assertSame('finished', $DB->get_field(store::BATCH, 'state', ['id' => $batch]));
        $this->assertSame('issued', $DB->get_field(store::ITEM, 'state', ['batchid' => $batch, 'userid' => $done->id]));
        $this->assertSame('skipped', $DB->get_field(store::ITEM, 'state', ['batchid' => $batch, 'userid' => $waiting->id]));
        $issued = $DB->get_record(store::ISSUE, ['courseid' => $course->id, 'userid' => $done->id], '*', MUST_EXIST);
        $this->assertEquals($revision, $issued->revisionid);
        $this->assertSame('course', store::snapshot($issued)['evidence']['kind']);
        $this->assertSame(1, $DB->count_records(store::ISSUE, ['courseid' => $course->id]));
    }

    /**
     * Reject ineligible roles, foreign groups and cross-course template references.
     */
    public function test_course_and_group_boundary(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/group/lib.php');
        $data = $this->getDataGenerator();
        $course = $data->create_course(['groupmode' => SEPARATEGROUPS]);
        $othercourse = $data->create_course();
        $template = store::save_template($course, 0, self::input());
        $teacher = $data->create_user();
        $learner = $data->create_user();
        $data->enrol_user($teacher->id, $course->id, 'editingteacher');
        $data->enrol_user($learner->id, $course->id, 'student');
        $own = $data->create_group(['courseid' => $course->id]);
        $foreign = $data->create_group(['courseid' => $course->id]);
        groups_add_member($own, $teacher);
        groups_add_member($foreign, $learner);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, \context_course::instance($course->id));
        $this->setUser($teacher);
        $this->assertFalse(access::sees($course, $learner->id));
        $this->assertSame((int)$own->id, access::group($course, 0));
        try {
            issuer::issue($course, $template, $learner->id);
            $this->fail('Out-of-group issue was accepted');
        } catch (\moodle_exception $failure) {
            $this->assertSame('unavailable', $failure->errorcode);
        }
        $this->setAdminUser();
        $this->expectExceptionMessage(access::text('unavailable'));
        issuer::issue($othercourse, $template, $learner->id);
    }

    /**
     * Stop serving tampered PDF bytes and erase only one authorized subject.
     */
    public function test_tampering_and_scoped_privacy_erasure(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('publicverification', 1, access::C);
        $data = $this->getDataGenerator();
        $course = $data->create_course();
        $learner = $data->create_user();
        $other = $data->create_user();
        $data->enrol_user($learner->id, $course->id, 'student');
        $data->enrol_user($other->id, $course->id, 'student');
        $template = store::save_template($course, 0, self::input());
        [$first] = issuer::issue($course, $template, $learner->id);
        [$second] = issuer::issue($course, $template, $other->id);
        $original = issuer::file($first);
        $code = self::code($original->get_content());
        $context = \context_course::instance($course->id);
        $original->delete();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => access::C, 'filearea' => issuer::AREA,
            'itemid' => $first->id, 'filepath' => '/', 'filename' => issuer::FILENAME,
        ], '%PDF- fake');
        try {
            issuer::file($first);
            $this->fail('Altered PDF was served');
        } catch (\moodle_exception $failure) {
            $this->assertSame('pdfinvalid', $failure->errorcode);
        }
        $this->assertEquals([$context->id], provider::get_contexts_for_userid($learner->id)->get_contextids());
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist($learner, access::C, [$context->id]));
        $this->assertFalse($DB->record_exists(store::ISSUE, ['id' => $first->id]));
        $this->assertNull(verification::lookup($code));
        $this->assertTrue($DB->record_exists(store::ISSUE, ['id' => $second->id]));
        $remaining = get_file_storage()->get_file($context->id, access::C, issuer::AREA, $first->id, '/', issuer::FILENAME);
        $this->assertFalse($remaining);
    }

    /**
     * Reject fields outside the allowlist.
     */
    public function test_unknown_dynamic_fields_are_rejected(): void {
        $this->expectExceptionMessage(access::text('invaliddesign'));
        design::normalize(self::input(['body' => 'Credential for {email}']));
    }

    /**
     * Read the linked URI included with the QR.
     *
     * @param string $pdf PDF bytes
     * @return string
     */
    private static function code(string $pdf): string {
        preg_match('~verify\.php\?code=([a-f0-9]{48})~', $pdf, $matches);
        self::assertArrayHasKey(1, $matches);
        return $matches[1];
    }

    /**
     * Build standard designer input.
     *
     * @param array $changes Overrides
     * @return \stdClass
     */
    private static function input(array $changes = []): \stdClass {
        return (object)($changes + [
            'label' => 'Course award', 'title' => 'Course completion', 'body' => 'Completed {course} on {completiondate}.',
            'issuer' => 'SkillUps Test', 'signer' => '', 'orientation' => 'L', 'color' => 'navy',
            'source' => 'manual', 'pathid' => 0, 'enabled' => 1, 'publicmeta' => 0, 'revision' => 0,
        ]);
    }
}
