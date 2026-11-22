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

use report_skillupsinsight\report;
use report_skillupsinsight\scheduler;
use report_skillupsinsight\util;

/**
 * Evaluate due reports and indicator thresholds.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deliver extends \core\task\scheduled_task {
    /**
     * Localized name.
     */
    public function get_name(): string {
        return util::str('taskdeliver');
    }

    /**
     * Claim and evaluate a bounded set per cron tick.
     */
    public function execute(): void {
        global $DB;
        $rows = $DB->get_records_select(
            report::WATCH,
            'enabled = 1 AND nextrun <= :now',
            ['now' => time()],
            'nextrun ASC, id ASC',
            'id',
            0,
            40
        );
        foreach ($rows as $row) {
            scheduler::run($row->id);
        }
    }
}
