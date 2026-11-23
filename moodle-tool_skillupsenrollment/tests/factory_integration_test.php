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

namespace tool_skillupsenrollment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use tool_skillupscoursefactory\integration\course_lookup;
use tool_skillupscoursefactory\jobs as factory_jobs;
use tool_skillupscoursefactory\worker as factory_worker;

/**
 * Verify the optional read-only Course Factory connection.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(validator::class)]
#[Group('skillupsenrollment_integration')]
final class factory_integration_test extends \advanced_testcase {
    /**
     * Complete a factory row before converting it into a normal enrollment preview.
     */
    public function test_factory_contract_exports_only_a_completed_authorized_destination(): void {
        global $USER;
        if (!class_exists(course_lookup::class)) {
            $this->markTestSkipped('Course Factory optional contract is unavailable.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $operator = (int)$USER->id;
        $learner = $this->getDataGenerator()->create_user();
        $factoryid = factory_jobs::draft([
            7 => ['fullname' => 'Factory integration', 'shortname' => 'FACTORY-ENROLL-TEST', 'category' => $category->id],
        ], 10, 0, 'UTC');
        $args = ['action' => 'enrol', 'userid' => $learner->id, 'factory_job' => $factoryid, 'factory_row' => 7];
        $this->assertSame(0, course_lookup::completed_course($factoryid, 7, $operator));
        $this->assertSame('invalidfactory', validator::preview([1 => $args], $operator)[1]['errorcode']);
        factory_jobs::command($factoryid, 'confirm');
        factory_worker::run($factoryid);
        $course = course_lookup::completed_course($factoryid, 7, $operator);
        $this->assertGreaterThan(SITEID, $course);
        $this->assertSame(0, course_lookup::completed_course($factoryid, 7, (int)$learner->id));
        $job = jobs::draft([1 => $args], 100, 0);
        jobs::command($job, 'confirm');
        worker::run($job);
        $this->assertEquals(1, jobs::counts($job)['applied']);
        $this->assertTrue(is_enrolled(\context_course::instance($course), $learner, '', true));
    }
}
