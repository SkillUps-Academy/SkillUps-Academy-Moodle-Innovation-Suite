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

/**
 * Exercise a thousand unique user-course operations with bounded workers.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(jobs::class)]
#[CoversClass(worker::class)]
#[CoversClass(mutation::class)]
#[Group('skillupsenrollment_scale')]
final class scale_test extends \advanced_testcase {
    /**
     * Process the whole simulated batch in at most fifty rows per worker invocation.
     */
    public function test_one_thousand_intents(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('maxbatch', 1000, support::C);
        $users = [];
        for ($i = 0; $i < 25; $i++) {
            $users[] = $this->getDataGenerator()->create_user();
        }
        $batch = [];
        for ($i = 0; $i < 40; $i++) {
            $course = $this->getDataGenerator()->create_course();
            foreach ($users as $user) {
                $batch[] = ['action' => 'enrol', 'courseid' => $course->id, 'userid' => $user->id, 'role' => 'student'];
            }
        }
        $batch = array_combine(range(1, count($batch)), $batch);
        $initial = $DB->count_records('user_enrolments');
        $job = jobs::draft($batch, 1000, 0);
        $this->assertEquals($initial, $DB->count_records('user_enrolments'));
        $this->assertEquals(1000, jobs::counts($job)['pending']);
        jobs::command($job, 'confirm');
        worker::run($job);
        $count = jobs::counts($job);
        $this->assertGreaterThan(0, $count['applied']);
        $this->assertLessThanOrEqual(50, $count['applied']);
        for ($i = 0; $i < 35 && jobs::visible($job)->state === 'queued'; $i++) {
            worker::run($job);
        }
        $this->assertSame('done', jobs::visible($job)->state);
        $this->assertEquals(1000, jobs::counts($job)['applied']);
        $this->assertEquals($initial + 1000, $DB->count_records('user_enrolments'));
        worker::run($job);
        $this->assertEquals($initial + 1000, $DB->count_records('user_enrolments'));
    }
}
