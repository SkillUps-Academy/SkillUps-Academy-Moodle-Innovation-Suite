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

use context_course;
use moodle_exception;
use stdClass;

/**
 * Verified mission, team and redemption operations.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class goals {
    /** @var array Visible goal and catalog types. */
    public const KINDS = ['mission', 'challenge', 'badge', 'event', 'reward'];

    /** @var array Supported scopes. */
    public const SCOPES = ['individual', 'team'];

    /** @var array Metrics derived from the verified ledger. */
    public const METRICS = ['points', 'xp', 'activities'];

    /** @var array Accessible curated badge icon options. */
    public const ICONS = ['star', 'flag', 'medal', 'target', 'gift'];

    /**
     * Read personal earned points and XP, then subtract committed redemption costs.
     *
     * @param int $courseid Course ID
     * @param int $userid Participant
     * @return array
     */
    public static function totals(int $courseid, int $userid): array {
        global $DB;
        $ledger = store::LEDGER;
        $grant = store::GRANT;
        $params = ['course' => $courseid, 'person' => $userid];
        $sum = $DB->get_record_sql(
            "SELECT COALESCE(SUM(points), 0) AS pointtotal, COALESCE(SUM(xp), 0) AS xptotal
               FROM {{$ledger}} WHERE courseid = :course AND userid = :person",
            $params
        );
        $spent = $DB->get_field_sql(
            "SELECT COALESCE(SUM(cost), 0) FROM {{$grant}} WHERE courseid = :course AND userid = :person",
            $params
        );
        return ['points' => (int)$sum->pointtotal, 'xp' => (int)$sum->xptotal, 'balance' => (int)$sum->pointtotal - (int)$spent];
    }

    /**
     * Return goal progress and claimant contribution with record-time team attribution.
     *
     * @param stdClass $goal Goal
     * @param int $userid Participant
     * @param int $teamid Current team
     * @return array Progress, own contributions, and contributing members
     */
    public static function progress(stdClass $goal, int $userid, int $teamid): array {
        global $DB;
        if ($goal->scope === 'team' && !$teamid) {
            return [0, 0, 0];
        }
        $table = store::LEDGER;
        $params = [
            'course' => $goal->courseid, 'start' => $goal->starts, 'end' => $goal->ends ?: PHP_INT_MAX,
            'owner' => $userid,
        ];
        $scope = $goal->scope === 'team' ? 'teamid = :team' : 'userid = :member';
        $params += $goal->scope === 'team' ? ['team' => $teamid] : ['member' => $userid];
        $metric = match ($goal->metric) {
            'xp' => 'SUM(xp)',
            'activities' => "SUM(CASE WHEN kind = 'activity' THEN 1 ELSE 0 END)",
            default => 'SUM(points)',
        };
        $filter = match ($goal->metric) {
            'xp' => 'xp > 0',
            'activities' => "kind = 'activity'",
            default => 'points > 0',
        };
        $row = $DB->get_record_sql(
            "SELECT COALESCE($metric, 0) AS score,
                    SUM(CASE WHEN userid = :owner THEN 1 ELSE 0 END) AS owncount,
                    COUNT(DISTINCT userid) AS members
               FROM {{$table}}
              WHERE courseid = :course AND $scope AND timeearned >= :start AND timeearned < :end AND $filter",
            $params
        );
        return [(int)$row->score, (int)$row->owncount, (int)$row->members];
    }

    /**
     * Determine whether the goal accepts claims now.
     *
     * @param stdClass $goal Goal
     * @param int $now Current instant
     * @return bool
     */
    public static function open(stdClass $goal, int $now): bool {
        return $goal->enabled && $now >= $goal->starts && (!$goal->ends || $now < $goal->ends);
    }

    /**
     * Award a verified achievement or reserve a reward once.
     *
     * @param int $courseid Course ID
     * @param int $goalid Goal or reward
     * @return int Grant ID
     */
    public static function claim(int $courseid, int $goalid): int {
        global $USER;
        $userid = (int)$USER->id;
        return store::change($courseid, static function () use ($courseid, $goalid, $userid): int {
            return self::claim_locked($courseid, $goalid, $userid);
        });
    }

    /**
     * Verify active eligibility, inventory and funds before inserting an immutable claim.
     *
     * @param int $courseid Course ID
     * @param int $goalid Goal
     * @param int $userid Authorized participant
     * @return int Grant ID
     */
    private static function claim_locked(int $courseid, int $goalid, int $userid): int {
        global $DB;
        $config = store::configuration($courseid);
        $identity = ['courseid' => $courseid, 'userid' => $userid, 'goalid' => $goalid];
        $goal = $DB->get_record(store::GOAL, ['id' => $goalid, 'courseid' => $courseid]);
        if (
            !$config->enabled || !access::participant(context_course::instance($courseid), $userid) ||
            store::player($courseid, $userid)->excluded || !$goal
        ) {
            access::deny();
        }
        $prior = $DB->get_field(store::GRANT, 'id', $identity);
        if ($prior) {
            return (int)$prior;
        }
        $now = time();
        if (!self::open($goal, $now)) {
            throw new moodle_exception('claimunavailable', access::C);
        }
        $teamid = $goal->scope === 'team' ? team::membership($config, $userid) : 0;
        $reward = $goal->kind === 'reward';
        if ($reward) {
            if (
                self::totals($courseid, $userid)['balance'] < $goal->cost ||
                ($goal->stock && $DB->count_records(store::GRANT, ['goalid' => $goalid]) >= $goal->stock)
            ) {
                throw new moodle_exception('claimunavailable', access::C);
            }
        } else {
            [$progress, $own, $members] = self::progress($goal, $userid, $teamid);
            if ($progress < $goal->target || ($goal->scope === 'team' && (!$own || $members < 2))) {
                throw new moodle_exception('claimunavailable', access::C);
            }
        }
        return (int)$DB->insert_record(store::GRANT, $identity + [
            'teamid' => $teamid, 'cost' => $reward ? $goal->cost : 0, 'state' => $reward ? 'pending' : 'earned',
            'timecreated' => $now,
        ]);
    }

    /**
     * Confirm delivery of a pending reward without changing its cost.
     *
     * @param int $courseid Course scope
     * @param int $id Grant ID
     */
    public static function fulfil(int $courseid, int $id): void {
        global $DB;
        access::manager($courseid);
        store::change($courseid, static function () use ($DB, $courseid, $id): void {
            $record = $DB->get_record(store::GRANT, ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
            if ($record->state === 'pending') {
                $DB->update_record(store::GRANT, ['id' => $id, 'state' => 'fulfilled', 'timefulfilled' => time()]);
                store::audit($courseid, 'fulfil', $id);
            }
        });
    }
}
