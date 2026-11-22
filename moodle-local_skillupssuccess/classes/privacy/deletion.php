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

namespace local_skillupssuccess\privacy;

use local_skillupssuccess\store;
use local_skillupssuccess\util;

/**
 * Scoped personal data deletion under the same lock as collection and intervention.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deletion {
    /**
     * Remove a case and its notes.
     */
    public static function case_record(int $id): void {
        global $DB;
        $tx = $DB->start_delegated_transaction();
        $DB->delete_records(store::ENTRY, ['caseid' => $id]);
        $DB->delete_records(store::CASES, ['id' => $id]);
        $tx->allow_commit();
    }

    /**
     * Purge all course data or remove one account and its authorship/ownership.
     */
    public static function by_course(int $courseid, ?int $userid = null): void {
        global $DB;
        $lock = util::lock($courseid, 30) ?? util::fail('busy');
        try {
            $base = ['courseid' => $courseid];
            $subject = $userid === null ? $base : $base + ['userid' => $userid];
            foreach ($DB->get_records(store::CASES, $subject, 'id ASC', 'id') as $case) {
                self::case_record($case->id);
            }
            $DB->delete_records(store::OBS, $subject);
            $DB->delete_records(store::SAMPLE, $subject);
            if ($userid === null) {
                $DB->delete_records(store::POLICY, $base);
            } else {
                $DB->set_field(store::CASES, 'tutorid', 0, $base + ['tutorid' => $userid]);
                $DB->set_field(store::SAMPLE, 'assessorid', 0, $base + ['assessorid' => $userid]);
                $cases = store::CASES;
                $entryfilter = "userid = :userid AND caseid IN (SELECT id FROM {{$cases}} WHERE courseid = :course)";
                $params = ['userid' => $userid, 'course' => $courseid];
                $DB->set_field_select(store::ENTRY, 'note', '', $entryfilter, $params);
                $DB->set_field_select(store::ENTRY, 'userid', 0, $entryfilter, $params);
                $tutorfilter = "tutorid = :userid AND caseid IN (SELECT id FROM {{$cases}} WHERE courseid = :course)";
                $DB->set_field_select(store::ENTRY, 'tutorid', 0, $tutorfilter, $params);
            }
        } finally {
            $lock->release();
        }
    }
}
