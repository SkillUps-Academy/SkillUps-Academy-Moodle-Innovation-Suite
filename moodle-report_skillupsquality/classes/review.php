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

namespace report_skillupsquality;

/**
 * Compare versions and track observed compliance independently of plan state.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review {
    /**
     * Return metadata for earlier completed revisions.
     */
    public static function earlier(\stdClass $run): array {
        global $DB;
        return $DB->get_records_select(
            store::RUN,
            'courseid = :course AND id < :current AND state = :state',
            ['course' => $run->courseid, 'current' => $run->id, 'state' => 'done'],
            'id DESC',
            'id,score,policy,timecreated',
            0,
            50,
        );
    }

    /**
     * Compute new, still present and absent findings for comparable policies.
     */
    public static function compare(\stdClass $current, \stdClass $previous): ?array {
        global $DB;
        $newpolicy = store::decode($current->policy);
        $oldpolicy = store::decode($previous->policy);
        if ($newpolicy['revision'] !== $oldpolicy['revision'] || $newpolicy['rules'] !== $oldpolicy['rules']) {
            return null;
        }
        $columns = 'fingerprint,rulecode,targettype,targetid,id';
        $before = $DB->get_records(store::FINDING, ['runid' => $previous->id], '', $columns);
        $after = $DB->get_records(store::FINDING, ['runid' => $current->id], '', $columns);
        return [
            'new' => array_diff_key($after, $before, array_fill_keys($oldpolicy['unverified'] ?? [], true)),
            'present' => array_intersect_key($after, $before),
            'absent' => array_diff_key($before, $after, array_fill_keys($newpolicy['unverified'] ?? [], true)),
        ];
    }

    /**
     * Read the last finished review visible to this course.
     */
    public static function latest(int $courseid): ?\stdClass {
        global $DB;
        $rows = $DB->get_records(store::RUN, ['courseid' => $courseid, 'state' => 'done'], 'id DESC', '*', 0, 1);
        return reset($rows) ?: null;
    }

    /**
     * Compare plans against the latest complete inspection of matching semantics.
     */
    public static function compliance(?\stdClass $latest, array $plans): array {
        global $DB;
        if (!$latest || !$plans) {
            return [];
        }
        $policy = store::decode($latest->policy);
        if ($policy['revision'] !== catalog::REVISION) {
            return [];
        }
        $present = $DB->get_records(store::FINDING, ['runid' => $latest->id], '', 'fingerprint,id');
        $unverified = array_fill_keys($policy['unverified'] ?? [], true);
        $states = [];
        foreach ($plans as $plan) {
            if (!isset($policy['rules'][$plan->rulecode]) || $latest->timemodified <= $plan->timemodified) {
                continue;
            }
            if (isset($present[$plan->fingerprint])) {
                $states[$plan->id] = 'present';
            } else if (!isset($unverified[$plan->fingerprint])) {
                $states[$plan->id] = 'absent';
            }
        }
        return $states;
    }

    /**
     * Stream only authored rule metadata and approved structured course evidence.
     */
    public static function export(\stdClass $run, string $format): void {
        global $DB;
        store::event('exported', (int)$run->courseid, (int)$run->id);
        $columns = array_map([scope::class, 'text'], [
            'courseid', 'auditid', 'requested', 'score', 'unverifiedcount', 'ruleid', 'rule', 'kind', 'severity', 'target',
            'evidence', 'recommendation',
        ]);
        $records = $DB->get_recordset(store::FINDING, ['runid' => $run->id], 'id ASC');
        $rows = (static function () use ($run, $records): \Generator {
            $unverified = count(store::decode($run->policy)['unverified'] ?? []);
            $base = [$run->courseid, $run->id, gmdate('c', $run->timecreated), $run->score < 0 ? '' : $run->score, $unverified];
            $found = false;
            try {
                foreach ($records as $item) {
                    $found = true;
                    $recommend = 'recommend_' . $item->rulecode;
                    yield array_merge($base, [
                        $item->rulecode, ui::rule_name($item->rulecode), scope::text('kind_' . $item->kind),
                        scope::text('severity_' . $item->severity),
                        scope::text('target_' . $item->targettype, $item->targetid),
                        json_encode(ui::evidence($item), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        get_string_manager()->string_exists($recommend, scope::COMPONENT) ? scope::text($recommend) : '',
                    ]);
                }
                if (!$found) {
                    yield array_merge($base, ['', '', '', '', '', '', '']);
                }
            } finally {
                $records->close();
            }
        })();
        \core\dataformat::download_data('skillups-course-quality-' . $run->id, $format, $columns, $rows);
    }
}
