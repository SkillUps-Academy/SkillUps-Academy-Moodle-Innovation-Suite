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

namespace report_skillupsinsight\task;

use report_skillupsinsight\util;
use report_skillupsinsight\warehouse;

/**
 * Work on the next due academic periods.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class aggregate extends \core\task\scheduled_task {
    /**
     * Task name.
     */
    public function get_name(): string {
        return util::str('taskaggregate');
    }

    /**
     * Run bounded site-wide aggregation.
     */
    public function execute(): void {
        global $DB;
        $periods = $DB->get_records_select(
            warehouse::PERIOD,
            'frozen = 0 AND nextrefresh <= :due AND begins <= :begins',
            ['due' => time(), 'begins' => time()],
            'nextrefresh ASC, id ASC',
            'id',
            0,
            3
        );
        foreach ($periods as $period) {
            warehouse::step((int)$period->id);
        }
        warehouse::expire();
    }
}
