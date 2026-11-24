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

namespace local_skillupscompetency;

use stdClass;

/**
 * Maintain programs, course membership, terms and competency targets.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class program {
    /**
     * Resolve the program category context.
     *
     * @param stdClass $program Institutional program
     * @return \context_coursecat
     */
    public static function context(stdClass $program): \context_coursecat {
        $context = \context_coursecat::instance($program->categoryid, IGNORE_MISSING);
        if (!$context) {
            access::fail();
        }
        return $context;
    }

    /**
     * Persist a validated matrix change in its institutional scope.
     *
     * @param string $type Type
     * @param stdClass $data Validated proposal or matrix values
     * @return int
     */
    public static function save(string $type, stdClass $data): int {
        global $DB;
        return store::write(static function () use ($DB, $type, $data): int {
            if ($type === 'program') {
                $existing = !empty($data->id) ? store::get(store::PROG, (int)$data->id) : null;
                $categoryid = $existing ? $existing->categoryid : (int)$data->categoryid;
                $context = \context_coursecat::instance($categoryid);
                require_capability(access::CAP . 'manage', $context);
                $code = strtoupper(trim($data->code));
                $name = trim($data->name);
                $summary = trim($data->summary ?? '');
                if (
                    !preg_match('/^[A-Z0-9][A-Z0-9._-]{0,39}$/D', $code) || $name === '' ||
                    \core_text::strlen($name) > 120 || \core_text::strlen($summary) > 4000
                ) {
                    access::fail('invalidinput');
                }
                return self::upsert(store::PROG, ['categoryid' => $categoryid, 'code' => $code], [
                    'name' => $name, 'summary' => $summary,
                ], $existing?->id);
            }
            $program = store::get(store::PROG, (int)$data->programid);
            $context = self::context($program);
            require_capability(access::CAP . 'manage', $context);
            switch ($type) {
                case 'term':
                    $existing = !empty($data->id) ? store::get(store::TERM, (int)$data->id) : null;
                    $name = trim($data->name);
                    if (
                        ($existing && $existing->programid != $program->id) || $name === '' || \core_text::strlen($name) > 80 ||
                        !self::is_date($data->firstday) || !self::is_date($data->lastday) || $data->firstday > $data->lastday
                    ) {
                        access::fail('invalidinput');
                    }
                    return self::upsert(store::TERM, ['programid' => $program->id, 'name' => $name], [
                        'firstday' => $data->firstday, 'lastday' => $data->lastday,
                    ], $existing?->id);
                case 'course':
                    $course = get_course((int)$data->courseid);
                    if (!access::course_visible($course) || !access::within(\context_course::instance($course->id), $context)) {
                        access::fail();
                    }
                    $key = ['programid' => $program->id, 'courseid' => $course->id];
                    if (!empty($data->unlink)) {
                        $DB->delete_records(store::MAP, $key);
                        return 0;
                    }
                    return self::upsert(store::MAP, $key, []);
                case 'goal':
                    $term = store::get(store::TERM, (int)$data->termid);
                    if ($term->programid != $program->id) {
                        access::fail();
                    }
                    $item = access::competency((int)$data->competencyid, $context);
                    $expected = (int)$data->expected;
                    $key = ['termid' => $term->id, 'competencyid' => $item->get('id')];
                    if ($expected === 0) {
                        $DB->delete_records(store::GOAL, $key);
                        return 0;
                    }
                    if (!isset(catalog::levels($item)[$expected])) {
                        access::fail('invalidlevel');
                    }
                    return self::upsert(store::GOAL, $key, [
                        'scaleid' => $item->get_scale()->id, 'expected' => $expected, 'crosscut' => empty($data->crosscut) ? 0 : 1,
                    ]);
            }
            access::fail();
        });
    }

    /**
     * Read the native courses linked to the program.
     *
     * @param stdClass $program Institutional program
     * @return array
     */
    public static function courses(stdClass $program): array {
        global $DB;
        $scope = self::context($program);
        $table = store::MAP;
        $results = $DB->get_records_sql("SELECT c.* FROM {course} c JOIN {{$table}} m ON m.courseid = c.id
            WHERE m.programid = :program ORDER BY c.fullname, c.id", ['program' => $program->id]);
        return array_filter($results, static fn($course) => access::course_visible($course) &&
            access::within(\context_course::instance($course->id), $scope));
    }

    /**
     * Read the expected levels for currently active terms.
     *
     * @param int $courseid Moodle course ID
     * @return array
     */
    public static function goals_for_course(int $courseid): array {
        global $DB;
        $table = store::MAP;
        $programs = store::PROG;
        $terms = store::TERM;
        $goals = store::GOAL;
        $day = userdate(time(), '%Y-%m-%d', 'UTC');
        $rows = $DB->get_records_sql(
            "SELECT g.*, p.name AS programname, p.categoryid, t.name AS termname FROM {{$goals}} g
               JOIN {{$terms}} t ON t.id = g.termid JOIN {{$programs}} p ON p.id = t.programid
               JOIN {{$table}} m ON m.programid = p.id
              WHERE m.courseid = :course AND t.firstday <= :startday AND t.lastday >= :endday
           ORDER BY t.firstday DESC, g.id DESC",
            ['course' => $courseid, 'startday' => $day, 'endday' => $day],
        );
        $context = \context_course::instance($courseid);
        return array_filter($rows, static function ($row) use ($context): bool {
            $category = \context_coursecat::instance($row->categoryid, IGNORE_MISSING);
            return $category && access::within($context, $category);
        });
    }

    /**
     * Validate an inclusive calendar day.
     *
     * @param string $value Value to format
     * @return bool
     */
    private static function is_date(string $value): bool {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d') === $value;
    }

    /**
     * Insert or update a row using its approved unique key.
     *
     * @param string $table Plugin table name
     * @param array $key Language key or unique record fields
     * @param array $data Validated proposal or matrix values
     * @param int|null $id Record ID
     * @return int
     */
    private static function upsert(string $table, array $key, array $data, ?int $id = null): int {
        global $DB;
        $existing = $DB->get_field($table, 'id', $key);
        if ($id && $existing && $id != $existing) {
            access::fail('duplicatecode');
        }
        if ($id || $existing) {
            $id = $id ?: (int)$existing;
            $DB->update_record($table, (object)(['id' => $id] + $key + $data));
            return $id;
        }
        return (int)$DB->insert_record($table, (object)($key + $data));
    }
}
