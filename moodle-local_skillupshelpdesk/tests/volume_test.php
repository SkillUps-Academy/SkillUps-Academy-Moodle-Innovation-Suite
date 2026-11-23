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

namespace local_skillupshelpdesk;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * High-volume pagination and aggregate reconciliation.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queries::class)]
final class volume_test extends \advanced_testcase {
    /**
     * Aggregate and paginate one thousand synthetic tickets without returning their contents.
     */
    public function test_report_reconciles_one_thousand_tickets(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $state = (int)array_key_first(catalog::options('status'));
        $now = time();
        $shared = [
            'requesterid' => $USER->id, 'statusid' => $state, 'courseid' => 0, 'firstdue' => $now + 3600,
            'resolvedue' => $now + 7200, 'title' => 'Synthetic scale sample', 'timecreated' => $now, 'timemodified' => $now,
        ];
        foreach (['category', 'campus', 'priority', 'severity'] as $kind) {
            $shared[$kind . 'id'] = (int)array_key_first(catalog::options($kind));
        }
        $rows = [];
        for ($index = 0; $index < 1000; $index++) {
            $rows[] = $shared + ['resolvedat' => $index % 2 === 0 ? $now : 0];
        }
        $DB->insert_records(storage::TICKET, $rows);
        [$counts, $queue] = queries::queue([], 39);
        $this->assertSame(1000, (int)$counts->totalcount);
        $this->assertSame(500, (int)$counts->closedcount);
        $this->assertCount(25, $queue);
        $result = queries::report('campus', []);
        $report = array_values(iterator_to_array($result));
        $result->close();
        $this->assertCount(1, $report);
        $this->assertSame(1000, (int)$report[0]->totalcount);
        $this->assertSame(500, (int)$report[0]->opencount);
    }
}
