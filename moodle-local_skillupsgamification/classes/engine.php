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

namespace local_skillupsgamification;

use completion_completion;
use completion_info;
use context_course;
use local_skillupsgamification\integration\learning_path;
use stdClass;

/**
 * Verify native evidence and apply configured point and XP rules.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /** @var string[] Supported immutable achievement sources. */
    public const KINDS = ['activity', 'course', 'path'];

    /**
     * Revalidate native state and try to award a completion once.
     *
     * @param int $courseid Course ID
     * @param int $userid Participant
     * @param string $kind Native source type
     * @param int $sourceid Stable activity, course or milestone ID
     * @param int $recordid Native completion record ID
     * @param int $occurred Authoritative evidence timestamp
     * @param int $taskid Native job ID, zero for direct authenticated processing
     * @return bool True only when a ledger row is created
     */
    public static function award(
        int $courseid,
        int $userid,
        string $kind,
        int $sourceid,
        int $recordid,
        int $occurred,
        int $taskid = 0
    ): bool {
        global $DB;
        if (
            !in_array($kind, self::KINDS, true) || $sourceid < 1 || $occurred < 1 || $occurred > time() ||
            !store::configuration($courseid)->enabled || !$DB->record_exists('course', ['id' => $courseid])
        ) {
            return false;
        }
        return store::change($courseid, static function () use (
            $DB,
            $courseid,
            $userid,
            $kind,
            $sourceid,
            $recordid,
            $occurred,
            $taskid
        ): bool {
            if ($taskid && !$DB->record_exists('task_adhoc', ['id' => $taskid, 'userid' => $userid])) {
                return false;
            }
            return self::persist($courseid, $userid, $kind, $sourceid, $recordid, $occurred);
        });
    }

    /**
     * Apply one award inside the per-course transaction.
     *
     * @param int $courseid Course ID
     * @param int $userid Participant
     * @param string $kind Source type
     * @param int $sourceid Business identity
     * @param int $recordid Native evidence
     * @param int $occurred Evidence timestamp
     * @return bool
     */
    private static function persist(int $courseid, int $userid, string $kind, int $sourceid, int $recordid, int $occurred): bool {
        global $DB;
        $config = store::configuration($courseid);
        $identity = ['courseid' => $courseid, 'userid' => $userid, 'kind' => $kind, 'sourceid' => $sourceid];
        if (
            !$config->enabled || $occurred < $config->timeenabled ||
            !access::participant(context_course::instance($courseid), $userid) ||
            store::player($courseid, $userid)->excluded || $DB->record_exists(store::LEDGER, $identity)
        ) {
            return false;
        }
        $rule = self::rule($courseid, $kind, $sourceid, $occurred);
        if (!$rule || !self::verify($courseid, $userid, $kind, $sourceid, $recordid, $occurred)) {
            return false;
        }
        if (!self::within_limits($config, $rule, $userid, $occurred)) {
            return false;
        }
        $DB->insert_record(store::LEDGER, $identity + [
            'ruleid' => $rule->id, 'teamid' => team::membership($config, $userid), 'points' => $rule->points,
            'xp' => $rule->xp, 'timeearned' => $occurred,
        ]);
        return true;
    }

    /**
     * Pick one matching rule. Exact activity IDs take precedence over a general rule.
     *
     * @param int $courseid Course ID
     * @param string $kind Evidence kind
     * @param int $sourceid Stable source ID
     * @param int $occurred Timestamp
     * @return stdClass|false
     */
    private static function rule(int $courseid, string $kind, int $sourceid, int $occurred): stdClass|false {
        global $DB;
        $table = store::RULE;
        $params = [
            'course' => $courseid, 'kind' => $kind, 'source' => $sourceid, 'opens' => $occurred, 'closes' => $occurred,
        ];
        $rules = $DB->get_records_sql(
            "SELECT r.* FROM {{$table}} r
              WHERE r.courseid = :course AND r.kind = :kind AND r.enabled = 1
                AND (r.sourceid = :source OR r.sourceid = 0) AND r.starts <= :opens
                AND (r.ends = 0 OR r.ends > :closes)
           ORDER BY r.sourceid DESC, r.id ASC",
            $params,
            0,
            1
        );
        return reset($rules);
    }

    /**
     * Check rule counts and course award points for the original UTC day.
     *
     * @param stdClass $config Course settings
     * @param stdClass $rule Applied rule
     * @param int $userid Participant
     * @param int $occurred Evidence timestamp
     * @return bool
     */
    private static function within_limits(stdClass $config, stdClass $rule, int $userid, int $occurred): bool {
        global $DB;
        $start = intdiv($occurred, DAYSECS) * DAYSECS;
        $range = 'timeearned >= :start AND timeearned < :end';
        $day = ['start' => $start, 'end' => $start + DAYSECS, 'person' => $userid];
        $personalrule = ['ruleid' => $rule->id, 'userid' => $userid];
        if ($rule->maxawards && $DB->count_records(store::LEDGER, $personalrule) >= $rule->maxawards) {
            return false;
        }
        if (
            $rule->maxdaily && $DB->count_records_select(
                store::LEDGER,
                "ruleid = :rule AND userid = :person AND $range",
                $day + ['rule' => $rule->id]
            ) >= $rule->maxdaily
        ) {
            return false;
        }
        if (!$config->dailycap) {
            return true;
        }
        $table = store::LEDGER;
        $points = $DB->get_field_sql(
            "SELECT COALESCE(SUM(points), 0) FROM {{$table}} WHERE courseid = :course AND userid = :person AND $range",
            $day + ['course' => $config->courseid]
        );
        return (int)$points + (int)$rule->points <= (int)$config->dailycap;
    }

    /**
     * Validate the exact evidence with Moodle core or the installed Learning Path provider.
     *
     * @param int $courseid Course ID
     * @param int $userid Participant
     * @param string $kind Source type
     * @param int $sourceid Business identity
     * @param int $recordid Native evidence
     * @param int $occurred Evidence time
     * @return bool
     */
    private static function verify(int $courseid, int $userid, string $kind, int $sourceid, int $recordid, int $occurred): bool {
        global $CFG;
        if ($kind === 'path') {
            $config = store::configuration($courseid);
            return $config->pathenabled && learning_path::provider()?->completed_at($courseid, $userid, $sourceid) === $occurred;
        }
        require_once($CFG->libdir . '/completionlib.php');
        $course = get_course($courseid);
        $completion = new completion_info($course);
        if (!$completion->is_enabled() || !$recordid) {
            return false;
        }
        if ($kind === 'course') {
            $data = new completion_completion(['userid' => $userid, 'course' => $courseid]);
            return $sourceid === $courseid && (int)$data->id === $recordid && (int)$data->timecompleted === $occurred;
        }
        $cm = get_fast_modinfo($course, $userid)->get_cms()[$sourceid] ?? null;
        if (
            !$cm || $cm->deletioninprogress || !$cm->uservisible ||
            (int)$completion->is_enabled($cm) !== COMPLETION_TRACKING_AUTOMATIC
        ) {
            return false;
        }
        $data = $completion->get_data($cm, false, $userid);
        return (int)$data->id === $recordid && (int)$data->timemodified === $occurred && empty($data->overrideby) &&
            in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }

    /**
     * Read all explicitly configured Learning Path milestones for the current participant.
     *
     * @param int $courseid Course ID
     * @param int $userid Participant
     * @return int New awards
     */
    public static function sync_path(int $courseid, int $userid): int {
        global $DB, $USER;
        $config = store::configuration($courseid);
        $provider = $config->pathenabled ? learning_path::provider() : null;
        $context = context_course::instance($courseid);
        if (!$config->enabled || !$provider || $USER->id != $userid || !access::participant($context, $userid)) {
            access::deny();
        }
        $milestones = [];
        $total = 0;
        foreach ($DB->get_records(store::RULE, ['courseid' => $courseid, 'kind' => 'path', 'enabled' => 1]) as $rule) {
            $milestones[(int)$rule->sourceid] = true;
        }
        foreach (array_keys($milestones) as $milestone) {
            $completed = $provider->completed_at($courseid, $userid, $milestone);
            if ($completed && self::award($courseid, $userid, 'path', $milestone, 0, $completed)) {
                $total++;
            }
        }
        return $total;
    }
}
