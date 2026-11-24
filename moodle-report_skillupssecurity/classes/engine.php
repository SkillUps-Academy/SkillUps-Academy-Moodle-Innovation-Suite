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

namespace report_skillupssecurity;

use report_skillupssecurity\control\accounts;
use report_skillupssecurity\control\configuration;
use report_skillupssecurity\control\privileges;

/**
 * Persist findings and lifecycle changes from consistent read-only controls.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /** @var array Severity order used by the site policy. */
    public const SEVERITIES = ['high', 'medium', 'low'];

    /** @var array Documented evidence classes. */
    public const KINDS = ['confirmed', 'risk', 'guide'];

    /**
     * Run controls under a shared lock and commit one complete snapshot.
     *
     * @return int Scan ID
     */
    public static function scan(): int {
        return store::atomic(static function (): int {
            global $DB;
            $now = time();
            $findings = [];
            $coverage = [];
            $inventory = [];
            foreach ([new configuration(), new accounts(), new privileges()] as $rule) {
                $result = $rule->evaluate($now);
                $coverage += $result['coverage'];
                $findings += array_column($result['findings'], null, 'fingerprint');
                $inventory = array_merge($inventory, $result['inventory']);
            }
            $id = (int)$DB->insert_record(store::SCAN, [
                'timecreated' => $now, 'coverage' => json_encode($coverage, JSON_THROW_ON_ERROR),
                'total' => count($findings), 'inventory' => count($inventory),
            ]);
            foreach (array_chunk($inventory, 200) as $group) {
                $DB->insert_records(store::PRIVILEGE, array_map(static fn(array $row): array => $row + ['scanid' => $id], $group));
            }
            $new = self::upsert($id, $now, $findings);
            $complete = array_keys(array_filter($coverage));
            if ($complete) {
                [$query, $params] = $DB->get_in_or_equal($complete, SQL_PARAMS_NAMED, 'rules');
                $where = "present = 1 AND lastscan <> :scanid AND ruleid $query";
                $params['scanid'] = $id;
                $stale = $DB->get_fieldset_select(store::FINDING, 'id', $where, $params);
                $DB->set_field_select(store::FINDING, 'present', 0, $where, $params);
                foreach ($stale as $obsolete) {
                    store::transition($obsolete, 'resolved');
                }
            }
            alerts::plan($id, $now, $new);
            return $id;
        });
    }

    /**
     * Apply observations and preserve prior reviews while each condition persists.
     *
     * @param int $scan Scan ID
     * @param int $now Scan time
     * @param array $findings Current observations keyed by fingerprint
     * @return array New or recurrent findings
     */
    private static function upsert(int $scan, int $now, array $findings): array {
        global $DB;
        $new = [];
        foreach (array_chunk($findings, 200, true) as $group) {
            $records = $DB->get_records_list(store::FINDING, 'fingerprint', array_keys($group));
            $previous = array_column($records, null, 'fingerprint');
            foreach ($group as $key => $data) {
                $data += ['lastscan' => $scan, 'lastseen' => $now, 'present' => 1];
                $old = $previous[$key] ?? null;
                if (!$old) {
                    $id = $DB->insert_record(store::FINDING, $data + ['firstseen' => $now, 'reviewstate' => 'new']);
                    store::transition($id, 'detected');
                    $new[] = $data;
                } else {
                    if (!$old->present) {
                        $data['reviewstate'] = 'new';
                        store::transition($old->id, 'recurred');
                        $new[] = $data;
                    }
                    $DB->update_record(store::FINDING, $data + ['id' => $old->id]);
                }
            }
        }
        return $new;
    }
}
