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
use stdClass;

/**
 * Course-scoped, opt-in pseudonymous rankings.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ranking {
    /**
     * Change the signed-in participant's ranking preference.
     *
     * @param int $courseid Course ID
     * @param bool $consent Consent is explicit and can be withdrawn
     */
    public static function consent(int $courseid, bool $consent): void {
        global $USER;
        $userid = (int)$USER->id;
        store::change($courseid, static function () use ($courseid, $userid, $consent): void {
            $config = store::configuration($courseid);
            $player = store::player($courseid, $userid);
            if (
                !access::participant(context_course::instance($courseid), $userid) ||
                ($consent && (!$config->enabled || !$config->rankingenabled || $player->excluded))
            ) {
                access::deny();
            }
            $player->consent = (int)$consent;
            $player->alias = $consent ? ($player->alias ?: bin2hex(random_bytes(6))) : '';
            store::save(store::PLAYER, $player);
        });
    }

    /**
     * Return visible course ranking rows, or an empty result when unavailable.
     *
     * @param stdClass $course Course
     * @return array Individual and team rows
     */
    public static function read(stdClass $course): array {
        global $CFG, $USER;
        $courseid = (int)$course->id;
        $context = context_course::instance($courseid);
        $config = store::configuration($courseid);
        $userid = (int)$USER->id;
        $player = store::player($courseid, $userid);
        if (
            !$config->enabled || !$config->rankingenabled || !$player->consent || $player->excluded ||
            !access::participant($context, $userid)
        ) {
            return [[], []];
        }
        require_once($CFG->libdir . '/grouplib.php');
        $groupid = 0;
        $separate = (int)groups_get_course_groupmode($course) === SEPARATEGROUPS;
        if ($separate && !has_capability('moodle/site:accessallgroups', $context)) {
            $groupid = team::membership($config, $userid);
            if (!$groupid) {
                return [[], []];
            }
        }
        [$enrolled, $params] = get_enrolled_sql($context, access::CAP . 'earn', $groupid, true);
        return [self::individual($courseid, $enrolled, $params), self::teams($config, $groupid, $enrolled, $params)];
    }

    /**
     * Rank only currently eligible consenting learners with points or XP.
     *
     * @param int $courseid Course ID
     * @param string $enrolled Moodle-generated enrolled SQL
     * @param array $params Moodle-generated bindings
     * @return array
     */
    private static function individual(int $courseid, string $enrolled, array $params): array {
        global $DB;
        $ledger = store::LEDGER;
        $participants = store::PLAYER;
        $rows = $DB->get_records_sql(
            "SELECT p.id, p.alias, SUM(l.points) AS points, SUM(l.xp) AS xp
               FROM {{$ledger}} l
               JOIN {{$participants}} p ON p.userid = l.userid AND p.courseid = l.courseid
               JOIN ($enrolled) e ON e.id = p.userid
               JOIN {user} u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE l.courseid = :rankingcourse AND p.consent = 1 AND p.excluded = 0
           GROUP BY p.id, p.alias ORDER BY points DESC, xp DESC, p.id ASC",
            $params + ['rankingcourse' => $courseid],
            0,
            100
        );
        return array_map(static fn($row): array => [
            'label' => access::text('playeralias', strtoupper($row->alias)),
            'points' => (int)$row->points,
            'xp' => (int)$row->xp,
        ], array_values($rows));
    }

    /**
     * Publish team rows from current consenting members, with at least three distinct scorers.
     *
     * @param stdClass $config Course configuration
     * @param int $groupid Viewer team in separate-groups mode
     * @param string $enrolled Moodle-generated enrolled SQL
     * @param array $params Moodle-generated bindings
     * @return array
     */
    private static function teams(stdClass $config, int $groupid, string $enrolled, array $params): array {
        global $DB;
        if (!$config->groupingid) {
            return [];
        }
        $visible = groups_get_all_groups($config->courseid, 0, $config->groupingid);
        if ($groupid) {
            $visible = array_intersect_key($visible, [$groupid => true]);
        }
        if (!$visible) {
            return [];
        }
        $ledger = store::LEDGER;
        $participants = store::PLAYER;
        $rows = $DB->get_records_sql(
            "SELECT l.teamid, SUM(l.points) AS points, SUM(l.xp) AS xp
               FROM {{$ledger}} l
               JOIN {{$participants}} p ON p.userid = l.userid AND p.courseid = l.courseid
               JOIN {groups_members} gm ON gm.groupid = l.teamid AND gm.userid = l.userid
               JOIN {groupings_groups} gg ON gg.groupid = l.teamid AND gg.groupingid = :rankinggroup
               JOIN ($enrolled) e ON e.id = p.userid
               JOIN {user} u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE l.courseid = :rankingcourse AND l.teamid > 0 AND p.consent = 1 AND p.excluded = 0
           GROUP BY l.teamid HAVING COUNT(DISTINCT l.userid) >= 3
           ORDER BY points DESC, xp DESC, l.teamid ASC",
            $params + ['rankingcourse' => $config->courseid, 'rankinggroup' => $config->groupingid]
        );
        $result = [];
        foreach ($rows as $row) {
            if (isset($visible[$row->teamid])) {
                $result[] = [
                    'label' => $visible[$row->teamid]->name,
                    'points' => (int)$row->points,
                    'xp' => (int)$row->xp,
                ];
            }
        }
        return array_slice($result, 0, 100);
    }
}
