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

namespace report_skillupsinsight;

/**
 * Build immutable period generations and resolve consistent, published datasets.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class warehouse {
    /** Academic period catalog. */
    public const PERIOD = 'report_skillupsinsight_period';
    /** Published and building generations. */
    public const RUN = 'report_skillupsinsight_run';
    /** Aggregate observations per generation and course. */
    public const DATA = 'report_skillupsinsight_snapshot';

    /**
     * Make one bounded generation step. A published run never changes.
     */
    public static function step(int $periodid, int $batch = 30): void {
        global $DB;
        $lock = util::lock('period_' . $periodid);
        if (!$lock) {
            return;
        }
        try {
            $period = $DB->get_record(self::PERIOD, ['id' => $periodid], '*', MUST_EXIST);
            if ($period->frozen || $period->nextrefresh > time() || $period->begins > time()) {
                return;
            }
            if (!$period->building) {
                $max = (int)$DB->get_field_sql('SELECT MAX(id) FROM {course}');
                $period->building = $DB->insert_record(self::RUN, [
                    'periodid' => $periodid, 'lastcourseid' => 0, 'timecreated' => time(), 'maxcourse' => $max,
                ]);
                $DB->set_field(self::PERIOD, 'building', $period->building, ['id' => $periodid]);
            }
            $run = $DB->get_record(self::RUN, ['id' => $period->building, 'periodid' => $periodid], '*', MUST_EXIST);
            $courses = $DB->get_records_select(
                'course',
                'id > :cursor AND id <= :maxid AND id <> :site',
                ['cursor' => $run->lastcourseid, 'maxid' => $run->maxcourse, 'site' => SITEID],
                'id ASC',
                '*',
                0,
                $batch
            );
            foreach ($courses as $course) {
                $key = ['courseid' => $course->id, 'runid' => $run->id];
                if (!$DB->record_exists(self::DATA, $key)) {
                    $data = metrics::capture($course, $period->begins, min((int)$period->ends, (int)$run->timecreated + 1));
                    $DB->insert_record(self::DATA, $key + ['data' => util::json($data)]);
                }
                $DB->set_field(self::RUN, 'lastcourseid', $course->id, ['id' => $run->id]);
            }
            if (count($courses) < $batch) {
                self::publish($period, $run);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Swap the pointer only when all snapshots for this run have been written.
     */
    private static function publish(\stdClass $period, \stdClass $run): void {
        global $DB;
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field(self::RUN, 'completed', $now, ['id' => $run->id]);
        $DB->update_record(self::PERIOD, [
            'id' => $period->id, 'published' => $run->id, 'building' => 0, 'nextrefresh' => $now + DAYSECS,
        ]);
        $transaction->allow_commit();
    }

    /**
     * Return a committed generation belonging to the selected period.
     */
    public static function run(\stdClass $period, int $runid = 0): ?\stdClass {
        global $DB;
        $runid = $runid ?: (int)$period->published;
        if (!$runid) {
            return null;
        }
        $run = $DB->get_record_select(
            self::RUN,
            'id = :id AND periodid = :period AND completed > 0',
            ['id' => $runid, 'period' => $period->id]
        );
        if (!$run) {
            throw new \moodle_exception('invalidperiod', util::C);
        }
        return $run;
    }

    /**
     * Load cached raw rollups only after the caller provides a current authorized course set.
     */
    public static function read(int $runid, array $authorized): array {
        global $DB;
        $cache = \cache::make(util::C, 'rollups');
        $result = [];
        foreach (array_chunk(array_keys($authorized), 200) as $ids) {
            $keys = array_map(static fn($id) => "r{$runid}c{$id}", $ids);
            $cached = $cache->get_many($keys);
            $missing = [];
            foreach ($ids as $index => $id) {
                $key = $keys[$index];
                if (is_array($cached[$key] ?? null)) {
                    $result[$id] = $cached[$key];
                } else {
                    $missing[] = $id;
                }
            }
            if (!$missing) {
                continue;
            }
            [$sql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED, 'course');
            $rows = $DB->get_records_select(
                self::DATA,
                "runid = :run AND courseid $sql",
                $params + ['run' => $runid],
                '',
                'courseid,data'
            );
            foreach ($rows as $id => $row) {
                $result[$id] = json_decode($row->data, true, 4, JSON_THROW_ON_ERROR);
                $cache->set("r{$runid}c{$id}", $result[$id]);
            }
        }
        return $result;
    }

    /**
     * Purge obsolete generations in bounded batches, preserving current and reference runs.
     */
    public static function expire(): void {
        global $DB;
        $retention = max(7, min(365, (int)(get_config(util::C, 'retention') ?: 90)));
        $cutoff = time() - $retention * DAYSECS;
        $runs = $DB->get_records_sql(
            'SELECT r.id, r.periodid FROM {' . self::RUN . '} r
            JOIN {' . self::PERIOD . '} p ON p.id = r.periodid
            WHERE r.timecreated < :cutoff AND r.id <> p.published AND r.id <> p.building ORDER BY r.id',
            ['cutoff' => $cutoff],
            0,
            20
        );
        foreach ($runs as $run) {
            $lock = util::lock('period_' . $run->periodid);
            if (!$lock) {
                continue;
            }
            try {
                $transaction = $DB->start_delegated_transaction();
                $DB->delete_records(self::DATA, ['runid' => $run->id]);
                $DB->delete_records(self::RUN, ['id' => $run->id]);
                $transaction->allow_commit();
            } finally {
                $lock->release();
            }
        }
    }
}
