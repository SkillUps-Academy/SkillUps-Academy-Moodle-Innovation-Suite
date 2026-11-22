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

namespace local_skillupsquestionstudio;

use local_skillupsquestionstudio\bank\publisher;
use local_skillupsquestionstudio\extraction\document;
use local_skillupsquestionstudio\generation\local;
use local_skillupsquestionstudio\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Exercise the real Moodle bank importer and course authorization.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(studio::class)]
#[CoversClass(assessment::class)]
#[CoversClass(publisher::class)]
#[CoversClass(work::class)]
#[CoversClass(provider::class)]
#[CoversClass(document::class)]
#[CoversClass(local::class)]
final class studio_test extends \advanced_testcase {
    /**
     * Import all five types through qformat_xml and verify receipts and teacher review.
     */
    public function test_approved_variant_imports_native_bank_idempotently(): void {
        global $DB, $USER, $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($bank->cmid);
        $category = question_get_default_category($context->id, true);
        $project = access::project(studio::create($course->id, $USER->id, 'Bank verification'), $course->id);
        $shapes = [
            'multichoice' => ['answers' => ['Alpha', 'Beta', 'Gamma'], 'correct' => 1],
            'truefalse' => ['truth' => false],
            'match' => ['pairs' => [
                ['left' => 'Term A', 'right' => 'Meaning A'], ['left' => 'Term B', 'right' => 'Meaning B'],
                ['left' => 'Term C', 'right' => 'Meaning C'],
            ]],
            'shortanswer' => ['answers' => ['Alpha', 'Beta']],
            'essay' => ['rubric' => 'Check the explanation and the example.'],
        ];
        $ids = [];
        foreach ($shapes as $kind => $shape) {
            $itemid = studio::save($project, 0, 0, $shape + [
                'kind' => $kind, 'name' => "Item $kind", 'prompt' => "Question with & symbol for $kind",
                'bloom' => 'apply', 'level' => 'medium',
            ], $USER->id);
            $ids[] = studio::item($project->id, $itemid);
        }
        try {
            assessment::create($project, 'Unapproved', 5, $USER->id);
            $this->fail('Created an assessment without approval');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidvariant', $exception->errorcode);
        }
        foreach ($ids as $item) {
            studio::review($project, $item->id, $item->currentid, true, $USER->id);
        }
        $variant = assessment::create($project, 'Version A', 5, $USER->id);
        $this->assertEquals(5, publisher::publish($project, $variant, $category->id, $USER->id));
        $this->assertEquals(0, publisher::publish($project, $variant, $category->id, $USER->id));
        $receipts = $DB->get_records(access::C . '_pub', ['categoryid' => $category->id]);
        $this->assertCount(5, $receipts);
        $types = [];
        foreach ($receipts as $receipt) {
            $this->assertEquals($category->id, $DB->get_field(
                'question_bank_entries',
                'questioncategoryid',
                ['id' => $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $receipt->questionid])]
            ));
            $types[] = $DB->get_field('question', 'qtype', ['id' => $receipt->questionid]);
        }
        $this->assertEqualsCanonicalizing(question::TYPES, $types);
        studio::review($project, $ids[0]->id, $ids[0]->currentid, false, $USER->id);
        $this->expectExceptionMessage(get_string('stalevariant', access::C));
        assessment::questions($project, assessment::find($project->id, $variant));
    }

    /**
     * Bank category selection cannot cross the current course.
     */
    public function test_bank_rejects_category_from_other_course(): void {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $other = $this->getDataGenerator()->create_course();
        $course = $this->getDataGenerator()->create_course();
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $other->id]);
        $category = question_get_default_category(\context_module::instance($bank->cmid)->id, true);
        $this->expectExceptionMessage(get_string('invalidcategory', access::C));
        publisher::category($course->id, $category->id);
    }

    /**
     * Process source and local proposals, clean the inbox and deduplicate drafts.
     */
    public function test_job_lifecycle_and_privacy(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $project = access::project(studio::create($course->id, $USER->id, 'Source project'), $course->id);
        $source = work::upload($project, self::file('concepts.txt', self::concepts()), $USER->id);
        work::execute('source', $source);
        $context = access::context($course->id);
        $record = $DB->get_record(access::C . '_source', ['id' => $source], '*', MUST_EXIST);
        $this->assertSame('ready', $record->state, $record->errorcode ?? '');
        $this->assertCount(0, get_file_storage()->get_area_files($context->id, access::C, 'inbox', $source, 'id', false));
        $request = [
            'kind' => 'multichoice', 'provider' => 'local', 'bloom' => 'remember', 'level' => 'easy',
            'unit' => 'Practice', 'count' => 3, 'competency' => 0, 'consent' => false,
        ];
        for ($i = 0; $i < 2; $i++) {
            $job = work::request($project, $source, $request, $USER->id);
            work::execute('job', $job);
        }
        $this->assertEquals(3, $DB->count_records(access::C . '_item', ['projectid' => $project->id, 'approved' => 0]));
        $this->assertEquals(0, $DB->get_field(access::C . '_job', 'resultcount', ['id' => $job]));
        $this->assertEquals([$context->id], provider::get_contexts_for_userid($USER->id)->get_contextids());
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist($USER, access::C, [$context->id]));
        $this->assertSame('deleted', $DB->get_field(access::C . '_source', 'state', ['id' => $source]));
        $this->assertFalse($DB->record_exists(access::C . '_job', ['projectid' => $project->id]));
    }

    /**
     * Extract named text from Office parts without deploying the ZIP anywhere.
     */
    public function test_office_extraction_and_traversal_rejection(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $doc = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
            '<w:body><w:p><w:r><w:t>Safe DOCX content</w:t></w:r></w:p></w:body></w:document>';
        $slide = '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" ' .
            'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' .
            '<a:p><a:r><a:t>Safe PPTX content</a:t></a:r></a:p></p:sld>';
        $this->assertSame('Safe DOCX content', document::extract(self::office('sample.docx', ['word/document.xml' => $doc])));
        $this->assertSame('Safe PPTX content', document::extract(self::office('sample.pptx', ['ppt/slides/slide1.xml' => $slide])));
        $this->expectExceptionMessage(get_string('invaliddocument', access::C));
        document::extract(self::office('invalid.docx', ['word/document.xml' => $doc, '../outside.txt' => 'invalid']));
    }

    /**
     * Exercise deterministic output for all five types without an external provider.
     */
    public function test_local_generator_returns_valid_drafts(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = access::context($this->getDataGenerator()->create_course()->id);
        foreach (question::TYPES as $kind) {
            $rows = (new local())->generate(self::concepts(), [
                'kind' => $kind, 'bloom' => 'remember', 'level' => 'medium', 'competency' => 0,
                'unit' => 'Core concepts', 'count' => 1, 'lang' => 'en',
            ], $context, $USER->id);
            $this->assertCount(1, $rows);
            $this->assertSame($kind, question::validate($rows[0])['kind']);
        }
    }

    /**
     * A synthetic, non-personal glossary.
     */
    private static function concepts(): string {
        return "Scaffolding: Temporary guidance for a learner to complete a task independently.\n" .
            "Feedback: Timely comments that identify an observable next step.\n" .
            "Retrieval: Remembering a concept without looking at the notes.\n" .
            "Transfer: Using a previously learned skill in a new situation.";
    }

    /**
     * Write a fake draft file to Moodle's isolated test file pool.
     */
    private static function file(string $name, string $text): \stored_file {
        global $USER;
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => random_int(1, 2000000000), 'filepath' => '/', 'filename' => $name,
        ], $text);
    }

    /**
     * Build a tiny OOXML fixture with only the parts needed by the parser.
     */
    private static function office(string $name, array $files): \stored_file {
        $path = make_request_directory() . '/' . $name;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($files + ['[Content_Types].xml' => '<Types/>'] as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();
        return self::file($name, file_get_contents($path));
    }
}
