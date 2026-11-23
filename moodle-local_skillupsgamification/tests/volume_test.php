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

namespace local_skillupsgamification;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Point, XP and goal reconciliation on a large synthetic ledger.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(goals::class)]
final class volume_test extends \advanced_testcase {
    /**
     * Reconcile thousand-row totals and scoped counts without reading unrelated records.
     */
    public function test_thousand_ledger_entries_reconcile(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $differentcourse = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $now = time();
        $shared = [
            'courseid' => $course->id, 'userid' => $user->id, 'ruleid' => 0, 'kind' => 'activity',
            'points' => 7, 'xp' => 13, 'timeearned' => $now,
        ];
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = $shared + ['sourceid' => $i];
        }
        $DB->insert_records(store::LEDGER, $rows);
        $DB->insert_record(store::LEDGER, ['courseid' => $differentcourse->id] + $shared + ['sourceid' => 1]);
        $this->assertSame(['points' => 7000, 'xp' => 13000, 'balance' => 7000], goals::totals($course->id, $user->id));
        $example = (object)[
            'courseid' => $course->id, 'scope' => 'individual', 'metric' => 'activities', 'starts' => $now, 'ends' => $now + 1,
        ];
        $this->assertSame([1000, 1000, 1], goals::progress($example, $user->id, 0));
        $example->ends = $now;
        $this->assertSame([0, 0, 0], goals::progress($example, $user->id, 0));
    }
}
