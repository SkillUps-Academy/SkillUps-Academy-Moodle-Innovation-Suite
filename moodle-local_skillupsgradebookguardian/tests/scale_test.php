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

namespace local_skillupsgradebookguardian;

/**
 * A large course with grade items and a deep calculation dependency chain.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsgradebookguardian\snapshot::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_skillupsgradebookguardian\rules::class)]
final class scale_test extends \advanced_testcase {
    /**
     * A calculation cycle can be reported on a course with 1,000 grade items.
     */
    public function test_large_course_dependency_scan(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = 0;
        $previous = 0;
        for ($index = 0; $index < 1000; $index++) {
            $item = new \grade_item([
                'courseid' => $course->id,
                'itemtype' => 'manual',
                'itemname' => "Synthetic $index",
                'calculation' => $previous ? '=##gi' . $previous . '##+1' : null,
            ], false);
            $item->insert();
            $first = $first ?: (int)$item->id;
            $previous = (int)$item->id;
        }
        $head = \grade_item::fetch(['id' => $first]);
        $head->set_calculation('=##gi' . $previous . '##+1');
        $diagnosis = rules::diagnose(new snapshot($course));
        $this->assertSame(1001, $diagnosis['counts']['items']);
        $codes = array_column($diagnosis['findings'], 'rule');
        $this->assertGreaterThanOrEqual(1000, count(array_keys($codes, 'dependency', true)));
    }
}
