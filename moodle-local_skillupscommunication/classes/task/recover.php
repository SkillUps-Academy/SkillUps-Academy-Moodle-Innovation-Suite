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

namespace local_skillupscommunication\task;

use local_skillupscommunication\access;
use local_skillupscommunication\dispatch;
use local_skillupscommunication\store;

/**
 * Restore pending campaign scheduling after cron interruption.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recover extends \core\task\scheduled_task {
    /**
     * Return the localized cron task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrecover', access::C);
    }

    /**
     * Queue due campaigns whose current wave may need to resume.
     */
    public function execute(): void {
        global $DB;
        $items = $DB->get_records_select(
            store::CAMP,
            "state IN ('queued','running') AND sendat <= :now",
            ['now' => time()],
            'updated ASC, id ASC',
            '*',
            0,
            100
        );
        foreach ($items as $job) {
            dispatch::queue($job, max((int)$job->sendat, dispatch::next((int)$job->id) ?? 0));
        }
    }
}
