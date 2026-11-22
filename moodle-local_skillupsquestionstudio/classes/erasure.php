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

namespace local_skillupsquestionstudio;

/**
 * Deletion of plugin-owned data only. Published Moodle questions are managed by their bank.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure {
    /**
     * Delete one project and its temporary files under the worker lock.
     */
    public static function project(int $id, int $courseid, bool $audit = false): void {
        global $DB;
        $lock = access::lock($id, 30);
        try {
            $tx = $DB->start_delegated_transaction();
            try {
                if ($audit) {
                    studio::audit($id, 'projectdeleted');
                }
                foreach ($DB->get_records(access::C . '_source', ['projectid' => $id], 'id', 'id') as $source) {
                    work::delete_upload($courseid, $source->id);
                }
                $revisions = 'SELECT r.id FROM {local_skillupsquestionstudio_rev} r
                    JOIN {local_skillupsquestionstudio_item} i ON i.id = r.itemid WHERE i.projectid = ?';
                $DB->delete_records_select(access::C . '_pub', "revisionid IN ($revisions)", [$id]);
                $DB->delete_records_select(
                    access::C . '_rev',
                    'itemid IN (SELECT id FROM {local_skillupsquestionstudio_item} WHERE projectid = ?)',
                    [$id]
                );
                foreach (['item', 'variant', 'job', 'source'] as $table) {
                    $DB->delete_records(access::C . '_' . $table, ['projectid' => $id]);
                }
                $DB->delete_records(access::C . '_project', ['id' => $id, 'courseid' => $courseid]);
                $tx->allow_commit();
            } catch (\Throwable $exception) {
                $tx->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }
}
