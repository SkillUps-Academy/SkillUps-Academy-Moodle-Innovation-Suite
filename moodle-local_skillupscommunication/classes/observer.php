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

namespace local_skillupscommunication;

/**
 * Clean up plugin-owned state within a removed institutional scope.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Remove data for the exact deleted Moodle context.
     *
     * @param \core\event\course_category_deleted $event Moodle category event
     */
    public static function deleted(\core\event\course_category_deleted $event): void {
        self::purge((int)$event->contextid);
    }

    /**
     * Remove scoped campaigns, recipient queues, catalogue entries and audit rows.
     *
     * @param int $scopeid Moodle context ID
     */
    public static function purge(int $scopeid): void {
        global $DB;
        $params = ['scope' => $scopeid];
        $ids = $DB->get_fieldset_select(store::CAMP, 'id', 'scopeid = :scope', $params);
        foreach ($ids as $id) {
            store::locked('campaign:' . $id, static function () use ($DB, $id): void {
                store::transaction(static function () use ($DB, $id): void {
                    $condition = ['campaignid' => $id];
                    $DB->delete_records(store::REC, $condition);
                    $DB->delete_records(store::LOG, $condition);
                    $DB->delete_records(store::CAMP, ['id' => $id]);
                });
            });
        }
        store::locked('catalog:' . $scopeid, static function () use ($DB, $scopeid, $params): void {
            store::transaction(static function () use ($DB, $scopeid, $params): void {
                $owned = 'segmentid IN (SELECT id FROM {' . store::SEG . '} WHERE scopeid = :scope)';
                $DB->delete_records_select(store::MEMBER, $owned, $params);
                $DB->delete_records(store::SEG, ['scopeid' => $scopeid]);
                $DB->delete_records(store::TEMPLATE, ['scopeid' => $scopeid]);
            });
        });
    }
}
