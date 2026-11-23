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

/**
 * Institutional business hours with holidays and timezone transitions.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class calendar {
    /** @var string Baseline ISO weekdays and shifts. */
    public const DEFAULT_HOURS = "1=09:00-17:00\n2=09:00-17:00\n3=09:00-17:00\n4=09:00-17:00\n5=09:00-17:00";

    /**
     * Store the parsed work calendar.
     *
     * @param DateTimeZone $zone Institutional timezone
     * @param array $shifts Local minute ranges by weekday
     * @param array $holidays Local dates
     */
    public function __construct(
        /** @var DateTimeZone Institutional timezone. */
        private readonly DateTimeZone $zone,
        /** @var array Local minute ranges by weekday. */
        private readonly array $shifts,
        /** @var array Excluded local dates. */
        private readonly array $holidays
    ) {
    }

    /**
     * Parse installed configuration.
     *
     * @return self
     */
    public static function configured(): self {
        $config = get_config(access::COMPONENT);
        return new self(
            new DateTimeZone($config->timezone ?? 'UTC'),
            self::parse_hours($config->hours ?? self::DEFAULT_HOURS),
            self::parse_holidays($config->holidays ?? '')
        );
    }

    /**
     * Parse weekday and shift definitions with a minimum working week.
     *
     * @param string $value One line per weekday, with one or more shifts
     * @return array
     */
    public static function parse_hours(string $value): array {
        $work = [];
        $minutes = 0;
        foreach (preg_split('/\R/', trim($value)) as $line) {
            if (!preg_match('/^([1-7])\s*=\s*(.+)$/D', trim($line), $match) || isset($work[(int)$match[1]])) {
                throw new InvalidArgumentException('hours');
            }
            $day = (int)$match[1];
            $previous = 0;
            $work[$day] = [];
            foreach (explode(',', $match[2]) as $period) {
                $pattern = '/^([0-2]\d):([0-5]\d)-([0-2]\d):([0-5]\d)$/D';
                if (!preg_match($pattern, trim($period), $range) || $range[1] > 23 || $range[3] > 24) {
                    throw new InvalidArgumentException('hours');
                }
                $start = 60 * (int)$range[1] + (int)$range[2];
                $end = 60 * (int)$range[3] + (int)$range[4];
                if ($start < $previous || $end <= $start || $end > 1440) {
                    throw new InvalidArgumentException('hours');
                }
                $work[$day][] = [$start, $end];
                $minutes += $end - $start;
                $previous = $end;
            }
        }
        if ($minutes < 420) {
            throw new InvalidArgumentException('hours');
        }
        return $work;
    }

    /**
     * Parse explicit local holidays using the ISO calendar format.
     *
     * @param string $value Dates separated by line breaks or commas
     * @return array
     */
    public static function parse_holidays(string $value): array {
        $holidays = [];
        $dates = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        if (count($dates) > 200) {
            throw new InvalidArgumentException('holidays');
        }
        foreach ($dates as $date) {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $match) || !checkdate($match[2], $match[3], $match[1])) {
                throw new InvalidArgumentException('holidays');
            }
            $holidays[$date] = true;
        }
        return $holidays;
    }

    /**
     * Add business minutes using real elapsed time within each local interval.
     *
     * @param int $timestamp UTC Unix time
     * @param int $minutes Time budget
     * @return int UTC Unix deadline
     */
    public function after(int $timestamp, int $minutes): int {
        if ($minutes < 1 || $minutes > 10080) {
            throw new InvalidArgumentException('minutes');
        }
        $remaining = $minutes * MINSECS;
        $day = (new DateTimeImmutable('@' . $timestamp))->setTimezone($this->zone)->setTime(0, 0);
        for ($index = 0; $index < 3660; $index++, $day = $day->modify('+1 day')->setTime(0, 0)) {
            if (isset($this->holidays[$day->format('Y-m-d')])) {
                continue;
            }
            foreach ($this->shifts[(int)$day->format('N')] ?? [] as [$start, $end]) {
                $from = max($timestamp, $day->setTime(intdiv($start, 60), $start % 60)->getTimestamp());
                $until = $day->setTime(intdiv($end, 60), $end % 60)->getTimestamp();
                $available = $until - $from;
                if ($available <= 0) {
                    continue;
                }
                if ($remaining <= $available) {
                    return $from + $remaining;
                }
                $remaining -= $available;
            }
        }
        throw new InvalidArgumentException('calendar');
    }
}
