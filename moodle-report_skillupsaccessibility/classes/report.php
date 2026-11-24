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

namespace report_skillupsaccessibility;

use stdClass;

/**
 * Read authorized snapshot metrics and current remediation status.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {
    /**
     * Select the latest complete audit in the specified period.
     *
     * @param int $courseid Moodle course
     * @param int $from Inclusive lower bound
     * @param int $to Exclusive upper bound
     * @return stdClass|null
     */
    public static function latest(int $courseid, int $from = 0, int $to = PHP_INT_MAX): ?stdClass {
        global $DB;
        $rows = $DB->get_records_select(
            store::JOB,
            'courseid = :course AND status = :status AND finished >= :fromtime AND finished < :totime',
            ['course' => $courseid, 'status' => 'complete', 'fromtime' => $from, 'totime' => $to],
            'finished DESC, id DESC',
            '*',
            0,
            1,
        );
        return reset($rows) ?: null;
    }

    /**
     * Stream snapshot rows after rechecking authorization for each Moodle source.
     *
     * @param stdClass $course Moodle course
     * @param stdClass $job Completed job
     * @return \Generator
     */
    public static function rows(stdClass $course, stdClass $job): \Generator {
        global $DB;
        if ($course->id != $job->courseid || $job->status !== 'complete' || !scope::allowed($course)) {
            scope::fail();
        }
        $assets = store::ASSET;
        $hits = store::HIT;
        $revisions = store::REV;
        $rows = $DB->get_recordset_sql(
            "SELECT a.*, r.checked, r.reason, r.issues FROM {{$hits}} h
               JOIN {{$assets}} a ON a.id = h.assetid JOIN {{$revisions}} r ON r.id = h.revid
              WHERE h.jobid = :job AND a.courseid = :course ORDER BY a.id",
            ['job' => $job->id, 'course' => $course->id],
        );
        try {
            foreach ($rows as $source) {
                if (scope::asset($course, $source)) {
                    yield $source;
                }
            }
        } finally {
            $rows->close();
        }
    }

    /**
     * Summarize coverage and observed rule counts without converting them into a conformance grade.
     *
     * @param iterable $rows Authorized sources
     * @return array
     */
    public static function metrics(iterable $rows): array {
        $counts = ['inventoried' => 0, 'checked' => 0, 'clear' => 0, 'confirmed' => 0, 'review' => 0];
        $frequency = [];
        $reasons = [];
        foreach ($rows as $source) {
            $counts['inventoried']++;
            $haserror = false;
            foreach (json_decode($source->issues, true, 10, JSON_THROW_ON_ERROR) as $issue) {
                $kind = $issue['classification'] ?? (rules::CATALOG[$issue['ruleid']][1] ?? 'review');
                $counts[$kind]++;
                $haserror = $haserror || $kind === 'confirmed';
                $frequency[$issue['ruleid']] = ($frequency[$issue['ruleid']] ?? 0) + 1;
            }
            if ($source->checked) {
                $counts['checked']++;
                $counts['clear'] += $haserror ? 0 : 1;
            } else if ($source->reason) {
                $reasons[$source->reason] = ($reasons[$source->reason] ?? 0) + 1;
            }
        }
        arsort($frequency);
        $counts['index'] = $counts['checked'] ? round(100 * $counts['clear'] / $counts['checked'], 1) : null;
        $counts['frequency'] = $frequency;
        $counts['reasons'] = $reasons;
        return $counts;
    }

    /**
     * Return the current workflow matches for observations made by the selected audit.
     *
     * @param iterable $rows Snapshot source rows
     * @return \Generator
     */
    public static function observations(iterable $rows): \Generator {
        global $DB;
        foreach ($rows as $source) {
            $workflow = array_column($DB->get_records(store::FINDING, ['assetid' => $source->id]), null, 'signature');
            foreach (json_decode($source->issues, true, 10, JSON_THROW_ON_ERROR) as $issue) {
                $rule = rules::CATALOG[$issue['ruleid']] ?? null;
                if (!$rule) {
                    continue;
                }
                $entry = $workflow[auditor::signature($issue)] ?? null;
                yield [
                    'id' => $entry->id ?? 0, 'label' => $source->label, 'sourcekind' => $source->kind,
                    'ruleid' => $issue['ruleid'], 'location' => $issue['location'], 'fact' => $issue['fact'],
                    'severity' => $issue['severity'] ?? $rule[0], 'classification' => $issue['classification'] ?? $rule[1],
                    'criterion' => $issue['criterion'] ?? $rule[2], 'state' => $entry->state ?? 'pending',
                ];
            }
        }
    }

    /**
     * Read a date filter in the Moodle display zone.
     *
     * @param string $date Calendar day
     * @param bool $end Use midnight of the following day
     * @return int
     */
    public static function date(string $date, bool $end = false): int {
        if ($date === '') {
            return $end ? PHP_INT_MAX : 0;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, \core_date::get_user_timezone_object());
        if (!$day || $day->format('Y-m-d') !== $date) {
            scope::fail('badperiod');
        }
        return ($end ? $day->modify('+1 day') : $day)->getTimestamp();
    }

    /**
     * Neutralize spreadsheet formula prefixes on exported labels.
     *
     * @param string $value Output value
     * @return string
     */
    public static function safe_cell(string $value): string {
        return preg_match('/^\s*[=+\-@]|^[\t\r\n]/u', $value) ? "'" . $value : $value;
    }
}
