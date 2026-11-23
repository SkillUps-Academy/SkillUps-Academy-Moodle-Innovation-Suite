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
use local_skillupshelpdesk\notification;
use local_skillupshelpdesk\storage;
use local_skillupshelpdesk\tickets;

/**
 * Detect overdue milestones and dispatch generic Moodle messages.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class process extends \core\task\scheduled_task {
    /**
     * Localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('processtask');
    }

    /**
     * Process one bounded SLA batch and one notice batch.
     */
    public function execute(): void {
        global $DB;
        $now = time();
        foreach (['response', 'resolve'] as $type) {
            $sql = $type === 'response'
                ? 'resolvedat = 0 AND firstresponse = 0 AND responsebreach = 0 AND firstdue < :now'
                : 'resolvedat = 0 AND resolvebreach = 0 AND resolvedue < :now';
            $rows = $DB->get_records_select(storage::TICKET, $sql, ['now' => $now], 'id ASC', 'id', 0, 100);
            foreach ($rows as $row) {
                try {
                    storage::locked('ticket:' . $row->id, static function () use ($DB, $row, $type, $now): void {
                        $ticket = $DB->get_record(storage::TICKET, ['id' => $row->id]);
                        if (!$ticket || $ticket->resolvedat || ($type === 'response' && $ticket->firstresponse)) {
                            return;
                        }
                        storage::transaction(static function () use ($DB, $ticket, $type, $now): void {
                            $updates = ['id' => $ticket->id];
                            tickets::mark_breach($ticket, $type, $now, $updates);
                            if (count($updates) > 1) {
                                $DB->update_record(storage::TICKET, $updates);
                            }
                        });
                    });
                } catch (\moodle_exception $exception) {
                    if ($exception->errorcode !== 'busy') {
                        throw $exception;
                    }
                }
            }
        }
        notification::deliver();
    }
}
