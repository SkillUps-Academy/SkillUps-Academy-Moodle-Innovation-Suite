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

namespace tool_skillupscoursefactory\integration;

use tool_skillupscoursefactory\jobs;
use tool_skillupscoursefactory\support;

/**
 * Optional, read-only contract for a completed Course Factory row.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_lookup {
    /** Public contract version. */
    public const VERSION = 1;

    /**
     * Return a completed destination ID, or zero when the result is unavailable.
     */
    public static function completed_course(int $jobid, int $position, int $actor): int {
        global $DB;
        if (!support::can_operate($actor) || $jobid <= 0 || $position <= 0) {
            return 0;
        }
        $job = $DB->get_record(jobs::JOB, ['id' => $jobid], 'id,userid');
        $canmanage = has_capability('tool/skillupscoursefactory:manage', \context_system::instance(), $actor);
        if (!$job || ((int)$job->userid !== $actor && !$canmanage)) {
            return 0;
        }
        $row = $DB->get_record(jobs::ROW, ['jobid' => $jobid, 'position' => $position, 'state' => 'done'], 'id,courseid');
        if (!$row || $row->courseid <= SITEID) {
            return 0;
        }
        $course = $DB->get_record('course', ['id' => $row->courseid], 'id,visible,category');
        return $course && \core_course_category::can_view_course_info($course, $actor) ? (int)$course->id : 0;
    }
}
