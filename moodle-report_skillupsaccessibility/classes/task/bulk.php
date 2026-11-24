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

namespace report_skillupsaccessibility\task;

use report_skillupsaccessibility\auditor;
use report_skillupsaccessibility\scope;

/**
 * Expand a course category or institution request into bounded jobs.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk extends \core\task\adhoc_task {
    /**
     * Enqueue an authorized bulk cursor.
     *
     * @param int $categoryid Category or zero for site
     * @param int $actorid Authorized operator
     * @param int $after Last processed course ID
     */
    public static function enqueue(int $categoryid, int $actorid, int $after = 0): void {
        $task = new self();
        $task->set_custom_data(['categoryid' => $categoryid, 'actorid' => $actorid, 'after' => $after]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Process a bounded cursor under the original actor's current capabilities.
     */
    public function execute(): void {
        global $DB, $USER;
        $data = $this->get_custom_data();
        $actor = $DB->get_record('user', ['id' => $data->actorid, 'deleted' => 0, 'suspended' => 0]);
        $context = $data->categoryid ? \context_coursecat::instance($data->categoryid, IGNORE_MISSING) :
            \context_system::instance();
        if (!$actor || !$context || !has_capability(scope::CAP . 'bulk', $context, $actor->id)) {
            return;
        }
        $previous = $USER;
        \core\session\manager::set_user($actor);
        try {
            $counter = 0;
            $last = (int)$data->after;
            foreach (scope::courses((int)$data->categoryid, 'scan') as $course) {
                if ($course->id <= $last) {
                    continue;
                }
                if ($counter >= 60) {
                    self::enqueue((int)$data->categoryid, (int)$actor->id, $last);
                    break;
                }
                auditor::enqueue($course, 'bulk');
                $last = (int)$course->id;
                $counter++;
            }
        } finally {
            \core\session\manager::set_user($previous);
        }
    }
}
