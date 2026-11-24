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

namespace mod_skillupsattendance;

/**
 * Publish an explicit attendance snapshot using the native Gradebook API.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grades {
    /**
     * Check whether the current operator can publish an activity-wide snapshot.
     *
     * @param activity $activity Module
     * @return bool
     */
    public static function allowed(activity $activity): bool {
        $coursecontext = \context_course::instance($activity->course->id);
        return $activity->record->grades && $activity->can('publish') && $activity->can('report') &&
            $activity->staff_group(0) && has_capability('moodle/grade:edit', $coursecontext);
    }

    /**
     * Explicitly update the grade item without changing locked or overridden grades.
     *
     * @param activity $activity Module
     * @return array Published and skipped count
     */
    public static function publish(activity $activity): array {
        global $CFG;
        if (!self::allowed($activity)) {
            activity::fail('unavailable');
        }
        require_once($CFG->libdir . '/gradelib.php');
        return store::atomic($activity->record->id, static function () use ($activity): array {
            $item = \grade_item::fetch([
                'courseid' => $activity->course->id, 'itemtype' => 'mod', 'itemmodule' => 'skillupsattendance',
                'iteminstance' => $activity->record->id, 'itemnumber' => 0,
            ]);
            if ($item && $item->is_locked()) {
                activity::fail('gradelocked');
            }
            $data = [];
            $skipped = 0;
            foreach (report::overview($activity) as $uid => $row) {
                if ($row['percent'] === null || !$activity->eligible($uid, 0)) {
                    continue;
                }
                $current = $item ? \grade_grade::fetch(['itemid' => $item->id, 'userid' => $uid]) : null;
                if ($current && ($current->is_locked() || $current->is_overridden())) {
                    $skipped++;
                } else {
                    $data[$uid] = (object)['userid' => $uid, 'rawgrade' => $row['percent']];
                }
            }
            if (!$data) {
                activity::fail('nogrades');
            }
            $status = grade_update(
                activity::C,
                $activity->course->id,
                'mod',
                'skillupsattendance',
                $activity->record->id,
                0,
                $data,
                ['itemname' => $activity->record->name, 'gradetype' => GRADE_TYPE_VALUE, 'grademin' => 0, 'grademax' => 100],
            );
            if ($status !== GRADE_UPDATE_OK) {
                activity::fail('gradelocked');
            }
            store::audit($activity, 'published', after: count($data) . '/' . $skipped);
            return ['published' => count($data), 'skipped' => $skipped];
        });
    }
}
