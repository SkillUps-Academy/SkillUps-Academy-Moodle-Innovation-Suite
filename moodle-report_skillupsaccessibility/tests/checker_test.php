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

namespace report_skillupsaccessibility;

use core_privacy\local\request\approved_contextlist;
use report_skillupsaccessibility\privacy\provider;

/**
 * Test static rules, document boundaries, native queue workflows and privacy.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checker_test extends \advanced_testcase {
    /**
     * Verify representative confirmed and manual findings on authored HTML.
     */
    public function test_detects_static_barriers_and_classifies_warnings(): void {
        $html = <<<'HTML'
            <h2>Heading</h2><h4>Skipped</h4>
            <img src="x.png">
            <a href="/destination"><span aria-hidden="true">x</span></a><a href="/more">Click here</a>
            <table><tr><td>A</td><td>B</td></tr><tr><td>C</td><td>D</td></tr></table>
            <input type="text"><input placeholder="Search">
            <iframe src="https://example.invalid/lesson"></iframe>
            <video src="film.mp4"></video><div><audio src="clip.ogg"></audio></div>
            <span tabindex="3" style="color:#aaa;background-color:#fff">Gray text</span>
            <marquee>Moving information</marquee>
            HTML;
        $result = rules::html($html);
        $this->assertTrue($result['checked']);
        $this->assertEqualsCanonicalizing([
            'imgalt', 'emptylink', 'genericlink', 'headskip', 'tableheaders', 'formlabel', 'placeholder',
            'frametitle', 'videocaptions', 'audiotranscript', 'contrast', 'positivetab', 'movingmarkup',
        ], array_column($result['issues'], 'ruleid'));
        $this->assertSame('confirmed', rules::CATALOG['imgalt'][1]);
        $this->assertSame('review', rules::CATALOG['contrast'][1]);
        $where = array_column($result['issues'], 'location', 'ruleid');
        $this->assertSame('img[1]', $where['imgalt']);
        $this->assertStringNotContainsString('example.invalid', json_encode($result));
    }

    /**
     * Verify accessible controls and alternatives avoid these specific rules.
     */
    public function test_does_not_flag_valid_alternatives_and_labels(): void {
        $html = <<<'HTML'
            <h2>Course</h2><h3>Unit</h3>
            <img src="decor.png" alt=""><img src="diagram.png" alt="Two nodes joined by a line">
            <img src="internal.png" hidden><img src="bound.png" aria-label="Chart of unit completion">
            <a href="/course"><img src="icon.png" alt="Course outline"></a>
            <table><tr><th scope="col">Name</th><th scope="col">Task</th></tr><tr><td>Ada</td><td>Plan</td></tr></table>
            <table role="presentation"><tr><td>A</td><td>B</td></tr><tr><td>C</td><td>D</td></tr></table>
            <label for="x">Search catalog</label><input id="x"><label>Comment <textarea></textarea></label>
            <button type="submit">Save</button><input type="hidden">
            <iframe title="Course glossary" src="/glossary"></iframe>
            <video><track kind="captions" src="captions.vtt"></video>
            <p><audio></audio> <a href="/transcript">Transcript of the audio</a></p>
            <p style="color:rgb(0,0,0);background-color:#fff">Text</p>
            <span style="color:var(--theme);background-color:#fff">Unresolved theme token</span>
            HTML;
        $this->assertSame([], rules::html($html)['issues']);
        $this->assertEqualsWithDelta(1, rules::luminance('#fff'), .000001);
        $this->assertNull(rules::luminance('rgb(800,0,0)'));
        $this->assertFalse(rules::html(str_repeat('x', 2097153))['checked']);
        $plain = sources::analyze(['kind' => 'page', 'format' => FORMAT_PLAIN, 'body' => '<img src="printed-example">']);
        $this->assertFalse($plain['checked']);
        $this->assertSame('formatunsupported', $plain['reason']);
    }

    /**
     * Verify Word metadata signals and conservative PDF results.
     */
    public function test_document_checks_and_invalid_packages(): void {
        $dir = make_request_directory();
        $path = $dir . '/sample.docx';
        $xml = <<<'XML'
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
              xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">
              <w:body>
                <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr></w:p>
                <w:p><w:pPr><w:pStyle w:val="Heading3"/></w:pPr></w:p>
                <wp:docPr id="1" name="Missing"/>
                <wp:docPr id="2" name="Labeled" descr="A plot of course participation"/>
              </w:body>
            </w:document>
            XML;
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE));
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
        $result = documents::docx($path);
        $this->assertTrue($result['checked']);
        $this->assertEqualsCanonicalizing(['docxalt', 'docxheading'], array_column($result['issues'], 'ruleid'));
        $zip->open($path);
        $zip->addFromString('word/document.xml', '<!DOCTYPE x [<!ENTITY e SYSTEM "https://example.invalid/test">]><x>&e;</x>');
        $zip->close();
        $this->assertSame('unreadable', documents::docx($path)['reason']);
        $this->assertSame('unreadable', documents::pdf('not a pdf')['reason']);
        $pdf = documents::pdf('%PDF-1.7 /MarkInfo << /Marked false >>');
        $this->assertFalse($pdf['checked']);
        $this->assertSame('markedfalse', $pdf['issues'][0]['fact']);
    }

    /**
     * Create a native Moodle course with an author and a non-author.
     *
     * @return array
     */
    private function fixture(): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/filelib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course([
            'category' => $category->id, 'fullname' => 'Accessible course',
            'summary' => '<p><img src="/example.png"></p>', 'summaryformat' => FORMAT_HTML,
        ]);
        $author = $generator->create_and_enrol($course, 'editingteacher');
        $learner = $generator->create_and_enrol($course, 'student');
        return compact('category', 'course', 'author', 'learner');
    }

    /**
     * Verify queued jobs, caching, corrections and historical metric preservation.
     */
    public function test_incremental_jobs_and_snapshot_metrics(): void {
        global $DB;
        $f = $this->fixture();
        $course = $f['course'];
        $this->setUser($f['author']);
        $first = auditor::enqueue($course);
        $this->assertSame($first, auditor::enqueue($course));
        $task = new \report_skillupsaccessibility\task\scan();
        $task->set_custom_data(['jobid' => $first]);
        $task->execute();
        $a = store::get(store::JOB, $first);
        $this->assertSame('complete', $a->status);
        $this->assertEquals(1, $a->changed);
        $m = report::metrics(report::rows($course, $a));
        $this->assertSame(1, $m['confirmed']);
        $this->assertEquals(0, $m['index']);
        $second = auditor::enqueue($course);
        auditor::run($second);
        $b = store::get(store::JOB, $second);
        $this->assertEquals(0, $b->changed);
        $this->assertEquals(1, $b->reused);
        $this->assertSame(1, $DB->count_records(store::REV));
        $this->setAdminUser();
        update_course((object)[
            'id' => $course->id, 'summary' => '<img src="/example.png" alt="Example">', 'summaryformat' => FORMAT_HTML,
        ]);
        $course = get_course($course->id);
        $third = auditor::enqueue($course);
        auditor::run($third);
        $c = store::get(store::JOB, $third);
        $this->assertEquals(100, report::metrics(report::rows($course, $c))['index']);
        $this->assertEquals(0, report::metrics(report::rows($course, $a))['index']);
        $this->assertTrue($DB->record_exists(store::FINDING, ['state' => 'corrected', 'present' => 0]));
        $this->assertNull(report::metrics([])['index']);
        $this->assertSame("'=1+2", report::safe_cell('=1+2'));
        $now = time();
        $DB->set_field(store::JOB, 'finished', $now - DAYSECS, ['id' => $first]);
        $this->assertEquals($first, report::latest((int)$course->id, $now - 2 * DAYSECS, $now - HOURSECS)->id);
        $this->assertEquals($third, report::latest((int)$course->id, $now - HOURSECS)->id);
    }

    /**
     * Verify that an audit never expands a restricted user's visibility.
     */
    public function test_hidden_sources_and_revoked_audit_actor(): void {
        global $DB;
        $f = $this->fixture();
        $course = $f['course'];
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'name' => 'Hidden training draft', 'content' => '<img src="/secret.png">',
            'contentformat' => FORMAT_HTML,
        ], ['visible' => 0]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $context = \context_course::instance($course->id);
        assign_capability('moodle/course:viewhiddenactivities', CAP_PROHIBIT, $roleid, $context->id);
        $jobid = auditor::enqueue($course);
        auditor::run($jobid);
        $job = store::get(store::JOB, $jobid);
        $this->assertTrue($DB->record_exists(store::ASSET, ['cmid' => $page->cmid, 'kind' => 'page']));
        $this->setUser($f['author']);
        $this->assertNotContains($page->cmid, array_column(iterator_to_array(report::rows($course, $job)), 'cmid'));
        $revoked = auditor::enqueue($course);
        $this->setAdminUser();
        assign_capability(scope::CAP . 'scan', CAP_PROHIBIT, $roleid, $context->id);
        auditor::run($revoked);
        $this->assertSame('revoked', store::get(store::JOB, $revoked)->errorcode);
        $this->assertFalse($DB->record_exists(store::HIT, ['jobid' => $revoked]));
        $this->setUser($f['learner']);
        $this->assertFalse(scope::allowed($course));
    }

    /**
     * Verify category queue boundaries and an authorized scheduled audit account.
     */
    public function test_category_queue_and_scheduled_account(): void {
        global $DB, $USER;
        $f = $this->fixture();
        $outside = $this->getDataGenerator()->create_course();
        $bulk = new \report_skillupsaccessibility\task\bulk();
        $bulk->set_custom_data(['categoryid' => $f['category']->id, 'actorid' => $USER->id, 'after' => 0]);
        $bulk->execute();
        $this->assertTrue($DB->record_exists(store::JOB, ['courseid' => $f['course']->id, 'origin' => 'bulk']));
        $this->assertFalse($DB->record_exists(store::JOB, ['courseid' => $outside->id]));
        set_config('scheduleuserid', $f['learner']->id, scope::C);
        set_config('intervaldays', 7, scope::C);
        $scheduled = new \report_skillupsaccessibility\task\schedule();
        $scheduled->execute();
        $this->assertFalse($DB->record_exists(store::JOB, ['courseid' => $outside->id]));
        set_config('scheduleuserid', $USER->id, scope::C);
        $scheduled->execute();
        $this->assertTrue($DB->record_exists(store::JOB, ['courseid' => $outside->id, 'origin' => 'scheduled']));
        $this->assertSame(1, $DB->count_records(store::JOB, ['courseid' => $f['course']->id]));
    }

    /**
     * Verify native File Browser author access and cleanup for private review evidence.
     */
    public function test_resource_file_and_privacy_erasure(): void {
        global $DB;
        $f = $this->fixture();
        $course = $f['course'];
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $filecontext = \context_module::instance($resource->cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $filecontext->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'accessible-test.html',
        ], '<img src="/sample.png">');
        $id = auditor::enqueue($course);
        auditor::run($id);
        $this->assertTrue($DB->record_exists(store::ASSET, [
            'cmid' => $resource->cmid, 'kind' => 'file', 'label' => 'accessible-test.html',
        ]));
        $finding = $DB->get_record(store::FINDING, ['ruleid' => 'imgalt'], '*', IGNORE_MULTIPLE);
        $user = $f['author'];
        $this->setUser($user);
        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($user->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draft, 'filepath' => '/', 'filename' => 'evidence.txt',
        ], 'Synthetic evidence');
        $entry = review::save((int)$finding->id, (object)[
            'notes' => 'Editor reviewed the finding.', 'assignee' => $user->id, 'state' => 'dismissed', 'evidence' => $draft,
        ]);
        $context = \context_course::instance($course->id);
        $this->assertCount(1, get_file_storage()->get_area_files($context->id, scope::C, review::AREA, $entry, 'id', false));
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int)$user->id)->get_contextids());
        provider::delete_data_for_user(new approved_contextlist($user, scope::C, [$context->id]));
        $this->assertEquals(0, store::get(store::REVIEW, $entry)->authorid);
        $this->assertNull(store::get(store::REVIEW, $entry)->notes);
        $this->assertEquals(0, store::get(store::FINDING, (int)$finding->id)->assignee);
        $this->assertCount(0, get_file_storage()->get_area_files($context->id, scope::C, review::AREA, $entry, 'id', false));
    }
}
