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
use report_skillupsaccessibility\store;

/**
 * Enqueue due audits only for a configured, currently authorized account.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schedule extends \core\task\scheduled_task {
    /**
     * Return the localized Moodle name.
     *
     * @return string
     */
    public function get_name(): string {
        return scope::text('taskschedule');
    }

    /**
     * Queue a bounded set of due courses.
     */
    public function execute(): void {
        global $DB, $USER;
        $id = (int)get_config(scope::C, 'scheduleuserid');
        if (!$id || !has_capability(scope::CAP . 'bulk', \context_system::instance(), $id)) {
            return;
        }
        $actor = $DB->get_record('user', ['id' => $id, 'deleted' => 0, 'suspended' => 0]);
        if (!$actor) {
            return;
        }
        $previous = $USER;
        $days = max(1, min(90, (int)get_config(scope::C, 'intervaldays')));
        $after = time() - $days * DAYSECS;
        \core\session\manager::set_user($actor);
        try {
            $count = 0;
            foreach (scope::courses(0, 'scan') as $course) {
                if (
                    $DB->record_exists_select(
                        store::JOB,
                        'courseid = :course AND created >= :after',
                        ['course' => $course->id, 'after' => $after]
                    )
                ) {
                    continue;
                }
                auditor::enqueue($course, 'scheduled');
                $count++;
                if ($count >= 12) {
                    break;
                }
            }
        } finally {
            \core\session\manager::set_user($previous);
        }
    }
}
