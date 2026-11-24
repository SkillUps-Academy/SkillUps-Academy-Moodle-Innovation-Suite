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

namespace local_skillupsexam;

use stdClass;

/**
 * Resolve the active native course roster and explicit exam registrations.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class roster {
    /**
     * Load the native enrollment capability and context for this activity.
     *
     * @param stdClass $exam Convocation
     * @param stdClass $course Moodle course
     * @return array
     */
    private static function enrollment(stdClass $exam, stdClass $course): array {
        $cm = $exam->cmid ? (get_fast_modinfo($course)->get_cms()[$exam->cmid] ?? null) : null;
        if ($cm && in_array($cm->modname, ['quiz', 'assign'], true)) {
            $capability = $cm->modname === 'quiz' ? 'mod/quiz:attempt' : 'mod/assign:submit';
            return [$cm->context, $capability, $cm];
        }
        return [\context_course::instance($course->id), 'moodle/course:isincompletionreports', null];
    }

    /**
     * Check a single user's enrollment, exam registration and optional operator scope.
     *
     * @param stdClass $exam Convocation
     * @param int $userid Learner
     * @param bool $operator Limit to the current user's groups
     * @param bool $registered Require registration in manual mode
     * @return bool
     */
    public static function eligible(stdClass $exam, int $userid, bool $operator = false, bool $registered = true): bool {
        global $DB;
        $course = get_course($exam->courseid);
        [$context, $capability, $cm] = self::enrollment($exam, $course);
        if (
            !is_enrolled($context, $userid, $capability, true) ||
                !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]) ||
                ($exam->groupid && !groups_is_member($exam->groupid, $userid))
        ) {
            return false;
        }
        $allowed = $operator ? scope::groups($course, $cm) : null;
        $grouping = $cm && $cm->groupingid && (int)groups_get_activity_groupmode($cm) !== NOGROUPS;
        if (($grouping || $allowed !== null) && !self::matches_groups($course, $cm, $userid, $allowed)) {
            return false;
        }
        return !$registered || $exam->regmode !== 'manual'
            || $DB->record_exists(store::SEAT, ['examid' => $exam->id ?? 0, 'userid' => $userid]);
    }

    /**
     * Stream active eligible learners with a deterministic cursor.
     *
     * @param stdClass $exam Convocation
     * @param bool $operator Limit to current group's scope
     * @param bool $registered Require manual registrations
     * @param int $after Last inspected user ID
     * @return \Generator
     */
    public static function users(stdClass $exam, bool $operator = true, bool $registered = true, int $after = 0): \Generator {
        global $DB;
        $course = get_course($exam->courseid);
        [$context, $capability, $cm] = self::enrollment($exam, $course);
        [$sql, $params] = get_enrolled_sql($context, $capability, (int)$exam->groupid, true);
        $join = '';
        if ($registered && $exam->regmode === 'manual') {
            $join = 'JOIN {' . store::SEAT . '} s ON s.userid = u.id AND s.examid = :examseat';
            $params['examseat'] = $exam->id ?? 0;
        }
        $allowed = $operator ? scope::groups($course, $cm) : null;
        $grouping = $cm && $cm->groupingid && (int)groups_get_activity_groupmode($cm) !== NOGROUPS;
        $records = $DB->get_recordset_sql(
            "SELECT u.* FROM {user} u JOIN ($sql) e ON e.id = u.id $join
              WHERE u.id > :examafter AND u.deleted = 0 AND u.suspended = 0 ORDER BY u.id",
            $params + ['examafter' => $after],
        );
        try {
            foreach ($records as $user) {
                if ((!$grouping && $allowed === null) || self::matches_groups($course, $cm, (int)$user->id, $allowed)) {
                    yield $user;
                }
            }
        } finally {
            $records->close();
        }
    }

    /**
     * Check native grouping and viewer membership for a learner.
     *
     * @param stdClass $course Moodle course
     * @param \cm_info|null $cm Linked activity
     * @param int $userid Learner
     * @param array|null $allowed Groups visible to the operator
     * @return bool
     */
    private static function matches_groups(stdClass $course, ?\cm_info $cm, int $userid, ?array $allowed): bool {
        $groups = groups_get_all_groups($course->id, $userid, $cm ? $cm->groupingid : 0);
        return $groups && ($allowed === null || array_intersect_key($allowed, $groups));
    }

    /**
     * Count eligible registrations, stopping when the supplied limit is exceeded.
     *
     * @param stdClass $exam Convocation
     * @param int $bound Optional stopping bound
     * @return int
     */
    public static function count(stdClass $exam, int $bound = PHP_INT_MAX): int {
        $count = 0;
        foreach (self::users($exam, false) as $ignored) {
            if (++$count > $bound) {
                break;
            }
        }
        return $count;
    }
}
