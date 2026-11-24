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
 * Build gap reports from scoped native competency ratings.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reports {
    /**
     * Stream eligible learners visible to the current operator.
     *
     * @param stdClass $course Moodle course record
     * @param int $groupid Group filter or zero
     * @return \Generator
     */
    public static function learners(stdClass $course, int $groupid = 0): \Generator {
        global $DB;
        if ($groupid && !$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $course->id])) {
            access::fail();
        }
        $context = \context_course::instance($course->id);
        [$enrolled, $params] = get_enrolled_sql($context, 'moodle/competency:coursecompetencygradable', $groupid, true);
        $records = $DB->get_recordset_sql(
            "SELECT u.* FROM {user} u JOIN ($enrolled) e ON e.id = u.id
              WHERE u.deleted = 0 AND u.suspended = 0 ORDER BY u.lastname, u.firstname, u.id",
            $params,
        );
        try {
            foreach ($records as $user) {
                if (access::can_see_subject($course, (int)$user->id)) {
                    yield $user;
                }
            }
        } finally {
            $records->close();
        }
    }

    /**
     * Compare current and expected ordinal scale positions.
     *
     * @param stdClass $goal Goal
     * @param array $levels Native scale labels indexed by level
     * @param int $scaleid Moodle scale ID
     * @param int $current Current native level or zero
     * @return array
     */
    public static function target(stdClass $goal, array $levels, int $scaleid, int $current): array {
        $target = $goal->scaleid == $scaleid && isset($levels[$goal->expected]) ? (int)$goal->expected : null;
        $now = isset($levels[$current]) ? $current : null;
        return [
            'expected' => $target ? $levels[$target] : access::text('scalechanged'),
            'current' => $now ? $levels[$now] : access::text('unrated'),
            'gap' => $target && $now ? max(0, $target - $now) : null,
            'state' => !$target ? 'scalechanged' : (!$now ? 'unrated' : ($now < $target ? 'gap' : 'met')),
        ];
    }

    /**
     * Stream gap results within the authorized program, course and group.
     *
     * @param stdClass $program Institutional program
     * @param stdClass $term Academic term
     * @param int $courseid Moodle course ID
     * @param int $groupid Group filter or zero
     * @return \Generator
     */
    public static function matrix(stdClass $program, stdClass $term, int $courseid = 0, int $groupid = 0): \Generator {
        global $DB;
        $context = program::context($program);
        require_capability(access::CAP . 'reports', $context);
        if ($term->programid != $program->id || ($groupid && !$courseid)) {
            access::fail();
        }
        $goals = $DB->get_records(store::GOAL, ['termid' => $term->id], 'id');
        foreach (program::courses($program) as $course) {
            $coursecontext = \context_course::instance($course->id);
            if (($courseid && $course->id != $courseid) || !has_capability(access::CAP . 'reports', $coursecontext)) {
                continue;
            }
            $links = [];
            foreach (access::links($course) as $link) {
                if ($link->get_framework()->get('visible') && access::within($coursecontext, $link->get_context())) {
                    $links[(int)$link->get('id')] = $link;
                }
            }
            foreach (self::learners($course, $groupid) as $user) {
                foreach ($goals as $goal) {
                    $item = $links[$goal->competencyid] ?? null;
                    if (!$item) {
                        continue;
                    }
                    $current = assessment::current((int)$course->id, (int)$user->id, (int)$goal->competencyid);
                    $data = self::target($goal, catalog::levels($item), (int)$item->get_scale()->id, $current);
                    yield $data + [
                        'course' => format_string($course->fullname),
                        'student' => fullname($user), 'competency' => format_string($item->get('shortname')),
                        'code' => $item->get('idnumber'),
                    ];
                }
            }
        }
    }

    /**
     * Neutralize leading characters treated as spreadsheet formulas.
     *
     * @param string $value Value to format
     * @return string
     */
    public static function cell(string $value): string {
        return preg_match('/^\s*[=+\-@]|^[\t\r\n]/u', $value) ? "'" . $value : $value;
    }
}
