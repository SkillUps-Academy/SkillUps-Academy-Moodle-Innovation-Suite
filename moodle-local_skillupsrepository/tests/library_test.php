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

namespace local_skillupsrepository;

use advanced_testcase;
use local_skillupsrepository\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verify category isolation, immutable files, moderation and native course copies.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access::class)]
#[CoversClass(catalog::class)]
#[CoversClass(files::class)]
#[CoversClass(library::class)]
#[CoversClass(observer::class)]
#[CoversClass(provider::class)]
#[CoversClass(reuse::class)]
final class library_test extends advanced_testcase {
    /**
     * Prepare independent private resources and native Moodle contexts.
     *
     * @return array Owning and foreign categories
     */
    private function setup_library(): array {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        return [$this->getDataGenerator()->create_category(), $this->getDataGenerator()->create_category()];
    }

    /**
     * Build normal submission data for a resource link.
     *
     * @param string $title Searchable title
     * @param string $audience Audience
     * @return \stdClass Contribution data
     */
    private function link(string $title, string $audience = 'institution'): \stdClass {
        return (object)[
            'title' => $title, 'description' => 'Ancient source and teacher reading', 'credit' => 'Museum',
            'collection' => 'Humanities', 'tags' => 'history, reading', 'sourceurl' => 'https://example.invalid/resource',
            'license' => 'ccby', 'audience' => $audience, 'allowdownload' => 1, 'allowcopy' => 1,
        ];
    }

    /**
     * Store a real PNG draft in the current user's file area.
     *
     * @param string $filename Destination filename for validation checks
     * @param string|null $content Optional different binary
     * @return int Draft item ID
     */
    private function draft(string $filename = 'study.png', ?string $content = null): int {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');
        $id = file_get_unused_draft_itemid();
        $encoded = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j44YAAAAASUVORK5CYII=';
        $bytes = $content ?? base64_decode($encoded);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $id, 'filepath' => '/', 'filename' => $filename,
        ], $bytes);
        return $id;
    }

    /**
     * Submit and approve the current version.
     *
     * @param int $assetid Resource ID
     * @return \stdClass Published resource
     */
    private function publish(int $assetid): \stdClass {
        $asset = store::get(store::ASSET, $assetid);
        library::change($assetid, (int)$asset->generation, 'submit');
        library::change($assetid, (int)$asset->generation + 1, 'approve', 'Synthetic review');
        return store::get(store::ASSET, $assetid);
    }

    /**
     * Grant a viewer access to one category only.
     *
     * @param int $categoryid Moodle category ID
     * @param bool $download Grant independent download capability
     * @return \stdClass Viewer
     */
    private function reader(int $categoryid, bool $download = false): \stdClass {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $role = $gen->create_role();
        $context = \context_coursecat::instance($categoryid);
        assign_capability(access::CAP . 'view', CAP_ALLOW, $role, $context);
        if ($download) {
            assign_capability(access::CAP . 'download', CAP_ALLOW, $role, $context);
        }
        role_assign($role, $user->id, $context);
        return $user;
    }

    /**
     * Search and direct lookup both enforce category isolation and independent download rights.
     */
    public function test_category_isolation_and_download(): void {
        [$category, $other] = $this->setup_library();
        $first = $this->publish(library::save($category->id, 0, $this->link('Shared reading')));
        $foreign = $this->publish(library::save($other->id, 0, $this->link('Foreign reading')));
        $private = $this->publish(library::save($category->id, 0, $this->link('Private reading', 'private')));
        $reader = $this->reader($category->id);
        $this->setUser($reader);
        [$items, $total] = catalog::search(['q' => 'museum history', 'collection' => 'human', 'sort' => 'relevance'], 0);
        $this->assertSame(1, $total);
        $this->assertArrayHasKey($first->id, $items);
        $approved = store::get(store::REVISION, (int)$first->publishedid);
        $this->assertTrue(access::view($first, $approved));
        $this->assertFalse(access::download($first, $approved));
        $this->assertFalse(access::view($foreign, store::get(store::REVISION, (int)$foreign->publishedid)));
        $this->assertFalse(access::view($private, store::get(store::REVISION, (int)$private->publishedid)));
        $this->assertFalse(access::view($foreign, $approved));
    }

    /**
     * Approval preserves an immutable binary and hides the newest draft from readers.
     */
    public function test_moderation_and_immutable_file(): void {
        global $DB;
        [$category] = $this->setup_library();
        $data = $this->link('Illustration');
        $data->sourceurl = '';
        $data->payload = $this->draft();
        $id = library::save($category->id, 0, $data);
        $asset = $this->publish($id);
        $previous = store::get(store::REVISION, (int)$asset->publishedid);
        $data->generation = $asset->generation;
        $data->payload = 0;
        $data->title = 'Revised illustration';
        library::save($category->id, $id, $data);
        $asset = store::get(store::ASSET, $id);
        $new = store::get(store::REVISION, (int)$asset->currentid);
        $this->assertNotEquals($previous->id, $new->id);
        $this->assertSame(files::stored($asset, $previous)->get_contenthash(), files::stored($asset, $new)->get_contenthash());
        $this->assertSame('image/png', files::stored($asset, $new)->get_mimetype());
        $this->assertEquals(4, $DB->count_records(store::AUDIT, ['assetid' => $id]));
        $this->setUser($this->reader($category->id, true));
        $this->assertFalse(access::view($asset, $new));
        $this->assertTrue(access::download($asset, $previous));
        $this->expectExceptionMessage(ui::t('unavailable'));
        library::change($id, (int)$asset->generation, 'approve');
    }

    /**
     * Public listing and direct reads require publication and the site option.
     */
    public function test_publication_and_archive(): void {
        [$category] = $this->setup_library();
        set_config('forcelogin', 0);
        set_config('allowpublic', 1, access::COMPONENT);
        $asset = $this->publish(library::save($category->id, 0, $this->link('Public catalogue item', 'public')));
        $version = store::get(store::REVISION, $asset->publishedid);
        $this->setGuestUser();
        $this->assertTrue(access::view($asset, $version));
        $this->assertSame(1, catalog::search(['q' => 'catalogue'], 0)[1]);
        $this->setAdminUser();
        library::change($asset->id, $asset->generation, 'archive');
        $latest = store::get(store::ASSET, $asset->id);
        $this->setGuestUser();
        $this->assertFalse(access::download($latest, $version));
        $this->assertSame(0, catalog::search(['q' => 'catalogue'], 0)[1]);
        set_config('allowpublic', 0, access::COMPONENT);
        $this->assertFalse(access::view($asset, $version));
    }

    /**
     * Changed file extensions do not bypass content validation.
     */
    public function test_upload_validation_rolls_back(): void {
        global $DB;
        [$category] = $this->setup_library();
        $data = $this->link('Invalid file');
        $data->sourceurl = '';
        $data->payload = $this->draft('fake.png', '<html>Unexpected file</html>');
        try {
            library::save($category->id, 0, $data);
            $this->fail('File signature mismatch was accepted');
        } catch (\moodle_exception $error) {
            $this->assertSame('badfile', $error->errorcode);
        }
        $this->assertFalse($DB->record_exists(store::ASSET, []));
    }

    /**
     * Recognize a learning archive by its supported directory entries.
     */
    public function test_package_validation(): void {
        [$category] = $this->setup_library();
        $path = make_request_directory() . '/course.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE));
        $zip->addFromString('imsmanifest.xml', '<manifest/>');
        $zip->addFromString('lesson.html', '<h1>Lesson</h1>');
        $zip->close();
        $data = $this->link('Learning archive');
        $data->sourceurl = '';
        $data->payload = $this->draft('course.zip', file_get_contents($path));
        $id = library::save($category->id, 0, $data);
        $asset = store::get(store::ASSET, $id);
        $revision = store::get(store::REVISION, (int)$asset->currentid);
        $this->assertSame('package', $revision->kind);
        $this->assertFalse(files::previewable($revision));
        $this->assertSame(sha1_file($path), files::stored($asset, $revision)->get_contenthash());
    }

    /**
     * Reuse creates a native URL only once and retains its deleted activity record.
     */
    public function test_reuse_as_native_url(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        [$category] = $this->setup_library();
        $asset = $this->publish(library::save($category->id, 0, $this->link('Assigned reading')));
        $course = $this->getDataGenerator()->create_course();
        $section = get_fast_modinfo($course)->get_section_info(0);
        $key = bin2hex(random_bytes(16));
        $id = reuse::insert($asset->id, $asset->publishedid, $course->id, $section->id, $key);
        $this->assertSame($id, reuse::insert($asset->id, $asset->publishedid, $course->id, $section->id, $key));
        $cm = get_coursemodule_from_id('url', $id);
        $this->assertEquals(0, $cm->visible);
        $this->assertSame('https://example.invalid/resource', $DB->get_field('url', 'externalurl', ['id' => $cm->instance]));
        $this->assertEquals(1, store::get(store::ASSET, $asset->id)->uses);
        $actions = \core_courseformat\formatactions::cm($course);
        if (method_exists($actions, 'delete')) {
            $actions->delete($id);
        } else {
            course_delete_module($id);
        }
        $this->assertGreaterThan(0, $DB->get_field(store::USAGE, 'removed', ['cmid' => $id]));
    }

    /**
     * Reuse keeps the original library file and works with Moodle's File activity.
     */
    public function test_reuse_as_native_file(): void {
        [$category] = $this->setup_library();
        $data = $this->link('Shared image');
        $data->sourceurl = '';
        $data->payload = $this->draft();
        $asset = $this->publish(library::save($category->id, 0, $data));
        $course = $this->getDataGenerator()->create_course();
        $section = get_fast_modinfo($course)->get_section_info(0);
        $key = bin2hex(random_bytes(16));
        $cmid = reuse::insert($asset->id, $asset->publishedid, $course->id, $section->id, $key);
        $cm = get_coursemodule_from_id('resource', $cmid, $course->id, false, MUST_EXIST);
        $file = get_file_storage()->get_file(\context_module::instance($cmid)->id, 'mod_resource', 'content', 0, '/', 'study.png');
        $revision = store::get(store::REVISION, $asset->publishedid);
        $this->assertNotFalse($file);
        $this->assertSame(files::stored($asset, $revision)->get_contenthash(), $file->get_contenthash());
        $this->assertEquals(0, $cm->visible);
        $this->assertSame('cc-4.0', $file->get_license());
    }

    /**
     * Delete approved ownership data only in the requested Moodle category.
     */
    public function test_privacy_cleanup(): void {
        global $USER, $DB;
        [$category, $other] = $this->setup_library();
        $data = $this->link('Sensitive', 'private');
        $data->sourceurl = '';
        $data->payload = $this->draft();
        $first = library::save($category->id, 0, $data);
        $owned = store::get(store::ASSET, $first);
        $revision = store::get(store::REVISION, (int)$owned->currentid);
        $this->assertNotFalse(files::stored($owned, $revision));
        $second = library::save($other->id, 0, $this->link('Outside request'));
        $context = \context_coursecat::instance($category->id);
        $entry = provider::get_contexts_for_userid((int)$USER->id);
        $this->assertContains((int)$context->id, array_map('intval', $entry->get_contextids()));
        $request = new \core_privacy\local\request\approved_contextlist($USER, access::COMPONENT, [$context->id]);
        provider::delete_data_for_user($request);
        $this->assertFalse($DB->record_exists(store::ASSET, ['id' => $first]));
        $this->assertFalse(files::stored($owned, $revision));
        $this->assertTrue($DB->record_exists(store::ASSET, ['id' => $second]));
    }
}
