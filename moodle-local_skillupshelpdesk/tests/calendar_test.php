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

namespace local_skillupshelpdesk;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Regression tests for business calendar boundaries.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(calendar::class)]
final class calendar_test extends \advanced_testcase {
    /**
     * Friday's incomplete hour carries over a weekend and a Monday holiday.
     */
    public function test_weekend_and_holiday(): void {
        $zone = new DateTimeZone('America/Santo_Domingo');
        $calendar = new calendar($zone, calendar::parse_hours(calendar::DEFAULT_HOURS), calendar::parse_holidays('2026-11-23'));
        $start = (new DateTimeImmutable('2026-11-20 16:00', $zone))->getTimestamp();
        $expected = (new DateTimeImmutable('2026-11-24 10:00', $zone))->getTimestamp();
        $this->assertSame($expected, $calendar->after($start, 120));
    }

    /**
     * Daylight saving spring-forward removes one elapsed hour from a shift.
     */
    public function test_daylight_saving_elapsed_time(): void {
        $zone = new DateTimeZone('America/New_York');
        $calendar = new calendar($zone, calendar::parse_hours("7=01:00-04:00\n1=09:00-17:00"), []);
        $start = (new DateTimeImmutable('2026-03-08 01:00', $zone))->getTimestamp();
        $expected = (new DateTimeImmutable('2026-03-08 04:00', $zone))->getTimestamp();
        $this->assertSame($expected, $calendar->after($start, 120));
    }

    /**
     * Calendar validation refuses overlapping work intervals.
     */
    public function test_overlapping_intervals_are_invalid(): void {
        $this->expectException(InvalidArgumentException::class);
        calendar::parse_hours('1=07:00-15:00,14:00-17:00');
    }

    /**
     * Calendar validation refuses nonexistent dates.
     */
    public function test_invalid_holiday(): void {
        $this->expectException(InvalidArgumentException::class);
        calendar::parse_holidays('2026-02-30');
    }
}
