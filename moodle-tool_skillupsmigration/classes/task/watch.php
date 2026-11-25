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

namespace tool_skillupsmigration\task;

use tool_skillupsmigration\processor;
use tool_skillupsmigration\source;
use tool_skillupsmigration\support;

/**
 * Recover due work and expire locally controlled backup material.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class watch extends \core\task\scheduled_task {
    /**
     * Name this Moodle cron task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskwatch', support::C);
    }

    /**
     * Redispatch due tasks and clean expired data without deleting courses.
     */
    public function execute(): void {
        global $DB;
        foreach ($DB->get_records_select(support::JOB, "state IN ('preflight','running')", [], 'updated ASC', '*', 0, 30) as $job) {
            processor::queue($job);
        }
        $query = "state = 'pending' OR (state = 'building' AND updated < :stale)";
        $pending = $DB->get_records_select(support::ARTIFACT, $query, ['stale' => time() - HOURSECS], 'updated ASC', '*', 0, 30);
        foreach ($pending as $row) {
            source::queue((int)$row->id, (int)$row->actor);
        }
        $limit = time() - 7 * DAYSECS;
        foreach (
            $DB->get_records_select(
                support::ARTIFACT,
                "state IN ('ready','failed') AND updated < :old",
                ['old' => $limit],
                'updated ASC',
                '*',
                0,
                30
            ) as $row
        ) {
            support::lock('build:' . $row->id, static function () use ($DB, $row): void {
                get_file_storage()->delete_area_files(\context_system::instance()->id, support::C, 'outgoing', $row->id);
                $DB->delete_records(support::ARTIFACT, ['id' => $row->id]);
            }, 0);
        }
        $cleaned = 0;
        foreach (new \FilesystemIterator(support::staging()) as $file) {
            if ($cleaned >= 100) {
                break;
            }
            if (
                !$file->isFile() || $file->isLink() || $file->getMTime() >= $limit ||
                !preg_match('/^([0-9a-f]{48})\.mbz$/D', $file->getFilename(), $parts)
            ) {
                continue;
            }
            $row = $DB->get_record(support::ITEM, ['requestkey' => $parts[1]]);
            if (!$row) {
                unlink($file->getPathname());
                $cleaned++;
                continue;
            }
            support::lock('job:' . $row->jobid, static function () use ($DB, $row, &$cleaned): void {
                $current = $DB->get_record(support::ITEM, ['id' => $row->id]);
                if (!$current || $current->requestkey !== $row->requestkey) {
                    return;
                }
                if (in_array($current->state, ['done', 'blocked', 'cancelled', 'uncertain', 'ready'], true)) {
                    processor::clean($current);
                    $cleaned++;
                }
                if ($current->state === 'ready') {
                    $report = support::decode($current->report);
                    $report['issues'][] = ['code' => 'archiveexpired', 'detail' => ''];
                    $current->report = support::encode($report);
                    $current->state = 'blocked';
                    $current->updated = time();
                    $DB->update_record(support::ITEM, $current);
                    support::audit((int)$current->jobid, (int)$current->id, 0, 'blocked');
                }
            }, 0);
        }
    }
}
