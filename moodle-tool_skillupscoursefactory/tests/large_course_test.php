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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verify a larger backup and restoration with multiple sections and files.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provisioner::class)]
#[CoversClass(worker::class)]
#[Group('skillupscoursefactory_scale')]
final class large_course_test extends \advanced_testcase {
    /**
     * Queue the maximum batch and prove the worker only provisions a bounded slice.
     */
    public function test_five_hundred_row_draft_uses_slices(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('maxbatch', 500, support::C);
        $category = $this->getDataGenerator()->create_category();
        $rows = [];
        for ($index = 1; $index <= 500; $index++) {
            $rows[$index] = [
                'fullname' => 'Institutional course ' . $index, 'shortname' => 'BATCH-' . $index, 'category' => $category->id,
            ];
        }
        $job = jobs::draft($rows, 500, 0, 'UTC');
        $this->assertEquals(500, jobs::counts($job)['pending']);
        jobs::command($job, 'confirm');
        worker::run($job);
        $counts = jobs::counts($job);
        $this->assertEquals(10, $counts['done']);
        $this->assertEquals(490, $counts['pending']);
        $this->assertSame('queued', jobs::visible($job)->state);
    }

    /**
     * Restore one hundred activities over twelve sections and verify twenty files.
     */
    public function test_large_course_with_attachments(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $source = $this->getDataGenerator()->create_course(['numsections' => 12]);
        for ($index = 1; $index <= 100; $index++) {
            $name = 'file-' . $index . '.txt';
            $content = $index % 5 === 0
                ? '<p><a href="@@PLUGINFILE@@/' . $name . '">Resource</a></p>'
                : '<p>Resource fixture</p>';
            $page = $this->getDataGenerator()->create_module('page', [
                'course' => $source->id, 'section' => ($index % 12) + 1, 'name' => 'Study page ' . $index,
                'contentformat' => FORMAT_HTML, 'content' => $content,
            ]);
            if ($index % 5 === 0) {
                get_file_storage()->create_file_from_string([
                    'contextid' => \context_module::instance($page->cmid)->id, 'component' => 'mod_page',
                    'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => $name,
                ], str_repeat(chr(65 + ($index % 26)), 131072));
            }
        }
        $job = jobs::draft([1 => [
            'shortname' => 'LARGE-COURSE-COPY', 'fullname' => 'Large course copy',
            'category' => $category->id, 'source' => $source->id,
        ]], 10, 0, 'UTC');
        jobs::command($job, 'confirm');
        worker::run($job);
        $row = $DB->get_record(jobs::ROW, ['jobid' => $job], '*', MUST_EXIST);
        $this->assertSame('done', $row->state, $row->errorcode);
        $activities = get_fast_modinfo((int)$row->courseid)->get_instances_of('page');
        $this->assertCount(100, $activities);
        $files = 0;
        foreach ($activities as $cm) {
            $attachments = get_file_storage()->get_area_files($cm->context->id, 'mod_page', 'content', 0, 'id', false);
            foreach ($attachments as $file) {
                if (preg_match('/^file-(\d+)\.txt$/D', $file->get_filename(), $parts)) {
                    $this->assertSame(sha1(str_repeat(chr(65 + ((int)$parts[1] % 26)), 131072)), $file->get_contenthash());
                    $files++;
                }
            }
        }
        $this->assertEquals(20, $files);
    }
}
