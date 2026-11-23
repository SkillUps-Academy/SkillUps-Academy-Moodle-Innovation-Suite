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

namespace local_skillupshelpdesk\task;

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\storage;

/**
 * Expire old terminal tickets and associated records.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class purge extends \core\task\scheduled_task {
    /**
     * Localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('purgetask');
    }

    /**
     * Apply configured retention without deleting an active conversation.
     */
    public function execute(): void {
        global $DB;
        $days = (int)(get_config(access::COMPONENT, 'retention') ?: 365);
        $cutoff = time() - max(30, min(730, $days)) * DAYSECS;
        $select = 'resolvedat > 0 AND resolvedat < :cutoff AND timemodified < :activitycutoff';
        $params = ['cutoff' => $cutoff, 'activitycutoff' => $cutoff];
        $rows = $DB->get_records_select(storage::TICKET, $select, $params, 'resolvedat ASC', 'id', 0, 100);
        foreach ($rows as $row) {
            try {
                storage::locked('ticket:' . $row->id, static function () use ($DB, $row, $select, $params): void {
                    if ($DB->record_exists_select(storage::TICKET, "$select AND id = :id", $params + ['id' => $row->id])) {
                        storage::delete((int)$row->id);
                    }
                });
            } catch (\moodle_exception $exception) {
                if ($exception->errorcode !== 'busy') {
                    throw $exception;
                }
            }
        }
        $DB->delete_records_select(storage::AUDIT, 'ticketid = 0 AND timecreated < :cutoff', ['cutoff' => $cutoff]);
    }
}
