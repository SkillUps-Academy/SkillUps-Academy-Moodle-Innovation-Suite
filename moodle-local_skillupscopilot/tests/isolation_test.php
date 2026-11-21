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

namespace local_skillupscopilot;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy and authorization boundaries using actual Moodle enrolments and file areas.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access::class)]
#[CoversClass(chat::class)]
#[CoversClass(sources::class)]
final class isolation_test extends \advanced_testcase {
    /**
     * Concurrent course membership never bypasses source group, availability or owner checks.
     */
    public function test_course_group_module_and_conversation_isolation(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $user = $generator->create_user();
        $peer = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $generator->enrol_user($user->id, $other->id);
        $generator->enrol_user($peer->id, $course->id);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $peer->id]);
        $module = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $this->setUser($user);

        $public = self::source($course->id, 'hemisphere public reference');
        self::source($course->id, 'hemisphere restricted group', $group->id);
        self::source($course->id, 'hemisphere hidden activity', 0, $module->cmid);
        self::source($other->id, 'hemisphere different course');
        $matches = sources::find($course->id, $user, 'HEMISPHERE');
        $this->assertSame([$public->id], array_column($matches, 'id'));
        $this->assertCount(2, sources::find($course->id, $peer, 'hemisphere'));
        $this->assertFalse(access::allowed($other->id, $peer));
        $this->assertNotContains('activity', access::modes($course->id, $user));
        $this->assertFalse(access::allowed(0, $user));
        $this->assertNull(sources::titles(json_encode([$public->id => 'wrong-revision']), $course->id, $user));

        $threadid = $DB->insert_record('local_skillupscopilot_thread', (object)[
            'userid' => $user->id, 'courseid' => $course->id, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertEquals($threadid, chat::thread($threadid, $course->id, $user->id)->id);
        foreach ([[$course->id, $peer->id], [$other->id, $user->id]] as [$courseid, $userid]) {
            try {
                chat::thread($threadid, $courseid, $userid);
                $this->fail('Unscoped conversation was returned');
            } catch (\dml_missing_record_exception $exception) {
                $this->assertSame('invalidrecord', $exception->errorcode);
            }
        }
    }

    /**
     * Verify revocation and bounded retrieval with a full source collection.
     */
    public function test_source_revocation_and_retrieval_bounds(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $this->setUser($user);
        $source = self::source($course->id, str_repeat('algorithms module reference ', 2000));
        $manifest = json_encode([$source->id => $source->revision]);
        $this->assertNotNull(sources::titles($manifest, $course->id, $user));
        for ($i = 1; $i < sources::MAX_DOCUMENTS; $i++) {
            self::source($course->id, str_repeat('algorithms unit practice ', 2000));
        }
        $matches = sources::find($course->id, $user, 'algorithms practice');
        $this->assertCount(4, $matches);
        foreach ($matches as $match) {
            $this->assertLessThanOrEqual(1000, \core_text::strlen($match['text']));
        }
        $DB->delete_records('local_skillupscopilot_source', ['id' => $source->id]);
        $this->assertNull(sources::titles($manifest, $course->id, $user));
    }

    /**
     * Store a test fixture through Moodle's File API.
     */
    private static function source(int $courseid, string $body, int $groupid = 0, int $cmid = 0): \stdClass {
        global $DB;
        $source = (object)[
            'courseid' => $courseid, 'title' => 'Reference', 'revision' => bin2hex(random_bytes(16)),
            'groupid' => $groupid, 'cmid' => $cmid, 'timecreated' => time(),
        ];
        $source->id = (int)$DB->insert_record('local_skillupscopilot_source', $source);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($courseid)->id, 'component' => 'local_skillupscopilot',
            'filearea' => 'source', 'itemid' => $source->id, 'filepath' => '/', 'filename' => 'document.txt',
        ], $body);
        return $source;
    }
}
