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

namespace tool_skillupsmigration;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Exercise transport policy, archive compatibility and native Backup/Restore.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(client::class)]
#[CoversClass(source::class)]
#[CoversClass(inventory::class)]
#[CoversClass(archive::class)]
#[CoversClass(destination::class)]
#[CoversClass(processor::class)]
#[CoversClass(jobs::class)]
final class migration_test extends advanced_testcase {
    /**
     * Create a course with two native activities and a group.
     *
     * @return array Synthetic course, activity, category and active student
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $course = $gen->create_course([
            'category' => $category->id, 'numsections' => 2, 'startdate' => 1760000000, 'enddate' => 1762000000,
        ]);
        $file = $gen->create_module('resource', ['course' => $course->id, 'name' => 'Source notes']);
        $gen->create_module('page', ['course' => $course->id, 'name' => 'Excluded page', 'content' => '<p>Notes</p>']);
        $gen->create_group(['courseid' => $course->id, 'name' => 'Study group']);
        $student = $gen->create_and_enrol($course, 'student');
        return [$course, $file, $category, $student];
    }

    /**
     * Create a course archive through the same native API as the source task.
     *
     * @param \stdClass $course Course
     * @param array $options Native plan flags
     * @return array Source artifact, inventory and normalized options
     */
    private function artifact(\stdClass $course, array $options): array {
        global $USER;
        $options = inventory::options($options);
        $read = inventory::read($course->id, $USER->id, $options);
        $request = [
            'course' => (int)$course->id, 'options' => $options, 'key' => bin2hex(random_bytes(24)),
            'fingerprint' => hash('sha256', support::encode($read)),
        ];
        $first = source::prepare($USER->id, $request);
        $second = source::prepare($USER->id, $request);
        $this->assertSame($first['id'], $second['id']);
        source::build($first['id']);
        $result = source::read($USER->id, $first['id'], null);
        $this->assertSame('ready', $result['state'], $result['reason']);
        return [$result, $read, $options];
    }

    /**
     * Accept only HTTPS pinned addresses and explicitly opted-in private networks.
     */
    public function test_endpoint_policy(): void {
        $public = ['url' => 'https://origin.example.test/moodle', 'ip' => '8.8.8.8'];
        $this->assertSame(['https://origin.example.test/moodle', 'origin.example.test', 443, '8.8.8.8'], client::endpoint($public));
        $private = ['url' => 'https://origin.example.test', 'ip' => '10.23.45.67', 'allowinternal' => true];
        $this->assertSame('10.23.45.67', client::endpoint($private)[3]);
        $invalid = [
            ['url' => 'http://origin.example.test', 'ip' => '8.8.8.8'],
            ['url' => 'https://user@origin.example.test', 'ip' => '8.8.8.8'],
            ['url' => 'https://origin.example.test', 'ip' => '127.0.0.1', 'allowinternal' => true],
            ['url' => 'https://origin.example.test', 'ip' => '169.254.169.254', 'allowinternal' => true],
            ['url' => 'https://origin.example.test', 'ip' => '10.23.45.67'],
        ];
        foreach ($invalid as $input) {
            try {
                client::endpoint($input);
                $this->fail('Unsafe endpoint was accepted');
            } catch (\moodle_exception $error) {
                $this->assertSame('endpoint', $error->errorcode);
            }
        }
    }

    /**
     * Refuse to inventory a course using an unprivileged token account.
     */
    public function test_source_permissions(): void {
        [$course, , , $student] = $this->fixture();
        $this->expectExceptionMessage(ui::t('unavailable'));
        inventory::read($course->id, $student->id, inventory::options([]));
    }

    /**
     * Show version/plugin blockers and reject an invalid requested activity rather than drop it.
     */
    public function test_compatibility_and_explicit_selection(): void {
        global $CFG, $USER;
        [$course] = $this->fixture();
        $opts = inventory::options([]);
        $data = inventory::read($course->id, $USER->id, $opts);
        $data['version'] = $CFG->version + 1;
        $data['modules'][] = ['type' => 'unknownfixture', 'id' => 1, 'backup' => true, 'version' => 0];
        $codes = array_column(archive::compatibility($data, $opts), 'code');
        $this->assertContains('newersource', $codes);
        $this->assertContains('missingplugin', $codes);
        $this->expectExceptionMessage(ui::t('selection'));
        inventory::read($course->id, $USER->id, inventory::options(['activities' => [99999999]]));
    }

    /**
     * Restore a selected File activity and group with a native private archive.
     */
    public function test_native_filtered_backup_and_restore(): void {
        global $USER;
        [$course, $resource, $category] = $this->fixture();
        [$export, $inventory, $options] = $this->artifact($course, [
            'activities' => [$resource->cmid], 'files' => 1, 'groups' => 1, 'dates' => 1,
        ]);
        $payload = source::read($USER->id, $export['id'], 0);
        $bytes = base64_decode($payload['data'], true);
        $this->assertSame($export['sha256'], hash('sha256', $bytes));
        $path = make_request_directory() . '/native.mbz';
        file_put_contents($path, $bytes);
        $report = archive::inspect($path, $inventory, $options);
        $this->assertSame([], $report['issues'], support::encode($report));
        $this->assertSame(1, $report['artifacts']['activities']);
        $this->assertGreaterThan(0, $report['artifacts']['files']);
        [$job, $item] = $this->restore_archive($category, $inventory, $options, $report, $export['sha256'], $path);
        $verification = destination::verify($job, $item);
        $this->assertTrue($verification['match'], support::encode($verification));
        $this->assertEquals(0, get_course($item->courseid)->visible);
        $this->assertEquals($item->courseid, destination::allocate($job, $item)->id);
        $cms = get_fast_modinfo($item->courseid)->get_cms();
        $this->assertCount(1, $cms);
        $this->assertSame('Source notes', reset($cms)->name);
    }

    /**
     * Archive and restore a Quiz with a supported native question type.
     */
    public function test_native_quiz_question_restore(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        [$course, , $category] = $this->fixture();
        $generator = $this->getDataGenerator();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $questions = $generator->get_plugin_generator('core_question');
        $questioncategory = $questions->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $questions->create_question('truefalse', null, ['category' => $questioncategory->id]);
        quiz_add_quiz_question($question->id, $quiz, 0, 1.0);
        [$export, $inventory, $options] = $this->artifact($course, ['files' => 1]);
        $bytes = '';
        for ($offset = 0; $offset < $export['bytes']; $offset += support::CHUNK) {
            $payload = source::read($USER->id, $export['id'], $offset);
            $bytes .= base64_decode($payload['data'], true);
        }
        $path = make_request_directory() . '/quiz.mbz';
        file_put_contents($path, $bytes);
        $report = archive::inspect($path, $inventory, $options);
        $this->assertSame([], $report['issues'], support::encode($report));
        $this->assertContains('truefalse', $report['artifacts']['questiontypes']);
        [$job, $item] = $this->restore_archive($category, $inventory, $options, $report, $export['sha256'], $path);
        $result = destination::verify($job, $item);
        $this->assertTrue($result['match'], support::encode($result));
        $targetquiz = $DB->get_field('quiz', 'id', ['course' => $item->courseid], MUST_EXIST);
        $this->assertEquals(1, $DB->count_records('quiz_slots', ['quizid' => $targetquiz]));
    }

    /**
     * Persist a migration fixture and execute the native restore boundary.
     *
     * @param \stdClass $category Destination category
     * @param array $inventory Source inventory
     * @param array $options Backup options
     * @param array $report Archive inspection result
     * @param string $digest SHA-256 digest
     * @param string $path Local archive path
     * @return array Migration job and item
     */
    private function restore_archive($category, $inventory, $options, $report, $digest, $path): array {
        global $DB, $USER;
        $job = (object)[
            'actor' => $USER->id, 'endpoint' => 'fixture', 'categoryid' => $category->id,
            'originhash' => str_repeat('0', 64), 'state' => 'running', 'created' => time(), 'updated' => time(),
        ];
        $job->id = $DB->insert_record(support::JOB, $job);
        $item = (object)[
            'jobid' => $job->id, 'sourceid' => $inventory['id'], 'courseid' => 0, 'shortname' => 'unique-migration-fixture',
            'requestkey' => bin2hex(random_bytes(24)), 'state' => 'ready', 'inventory' => support::encode($inventory),
            'options' => support::encode($options), 'report' => support::encode($report), 'digest' => $digest,
        ];
        $item->id = $DB->insert_record(support::ITEM, $item);
        destination::restore($job, $item, $path);
        $this->assertSame('verify', support::get(support::ITEM, $item->id)->state);
        return [$job, $item];
    }

    /**
     * A different source account cannot fetch another account's artifact.
     */
    public function test_artifact_owner_isolation(): void {
        [$course, , , $student] = $this->fixture();
        [$export] = $this->artifact($course, ['files' => 1]);
        $this->expectExceptionMessage(ui::t('unavailable'));
        source::read($student->id, $export['id'], 0);
    }

    /**
     * An interrupted restore is classified for review and cannot be replayed by a worker.
     */
    public function test_interrupted_restore_is_not_retried(): void {
        global $DB, $USER;
        [$course, , $category] = $this->fixture();
        $job = (object)[
            'actor' => $USER->id, 'endpoint' => 'fixture', 'categoryid' => $category->id, 'wave' => 1,
            'originhash' => str_repeat('0', 64), 'state' => 'running', 'created' => time(), 'updated' => time(),
        ];
        $job->id = $DB->insert_record(support::JOB, $job);
        $item = (object)[
            'jobid' => $job->id, 'sourceid' => $course->id, 'courseid' => $course->id, 'shortname' => $course->shortname,
            'requestkey' => bin2hex(random_bytes(24)), 'state' => 'restoring', 'digest' => '',
        ];
        $id = $DB->insert_record(support::ITEM, $item);
        processor::advance($job->id, 1);
        processor::advance($job->id, 1);
        $this->assertSame('uncertain', support::get(support::ITEM, $id)->state);
        $this->assertSame('complete', support::get(support::JOB, $job->id)->state);
        $this->assertEquals(1, $DB->count_records(support::AUDIT, ['jobid' => $job->id, 'action' => 'uncertain']));
    }
}
