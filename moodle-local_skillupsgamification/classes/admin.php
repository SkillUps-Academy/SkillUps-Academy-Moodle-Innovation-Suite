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

use moodle_exception;
use stdClass;

/**
 * Validate and save course configuration, rules and reward catalog.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin {
    /**
     * Save privacy, limits and team settings for a course.
     *
     * @param int $courseid Course ID
     * @param stdClass $data Form values
     */
    public static function course(int $courseid, stdClass $data): void {
        access::manager($courseid);
        $grouping = (int)($data->groupingid ?? 0);
        if (
            ($grouping && !isset(team::groupings($courseid)[$grouping])) ||
            $data->xplevel < 1 || $data->xplevel > 100000 || $data->dailycap < 0 || $data->dailycap > 100000
        ) {
            self::invalid();
        }
        store::change($courseid, static function () use ($courseid, $data, $grouping): void {
            $config = store::configuration($courseid);
            $enabled = (int)!empty($data->enabled);
            if (!$config->enabled && $enabled) {
                $config->timeenabled = time();
            }
            $config->enabled = $enabled;
            $config->rankingenabled = (int)!empty($data->rankingenabled);
            $config->pathenabled = (int)!empty($data->pathenabled);
            $config->xplevel = (int)$data->xplevel;
            $config->dailycap = (int)$data->dailycap;
            $config->groupingid = $grouping;
            $id = store::save(store::COURSE, $config);
            store::audit($courseid, 'course', $id);
        });
    }

    /**
     * Validate rule attributes and scope, then persist the rule.
     *
     * @param int $courseid Course ID
     * @param int $id Existing rule, or zero
     * @param stdClass $data Form values
     */
    public static function rule(int $courseid, int $id, stdClass $data): void {
        global $DB, $CFG;
        access::manager($courseid);
        require_once($CFG->libdir . '/completionlib.php');
        $source = (int)$data->sourceid;
        $kind = $data->kind;
        $cm = $kind === 'activity' && $source ? (get_fast_modinfo($courseid)->get_cms()[$source] ?? null) : null;
        if (
            !in_array($kind, engine::KINDS, true) || $source < 0 ||
            ($kind === 'course' && $source) || ($kind === 'path' && !$source) ||
            ($kind === 'activity' && $source && (!$cm || (int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC)) ||
            $data->points < 0 || $data->points > 100000 || $data->xp < 0 || $data->xp > 100000 ||
            ($data->points === 0 && $data->xp === 0) || $data->maxdaily < 0 || $data->maxdaily > 10000 ||
            $data->maxawards < 0 || $data->maxawards > 100000 || !self::window((int)$data->starts, (int)$data->ends)
        ) {
            self::invalid();
        }
        $record = (object)[
            'courseid' => $courseid, 'name' => self::name($data->name), 'kind' => $kind, 'sourceid' => $source,
            'enabled' => (int)!empty($data->enabled), 'points' => (int)$data->points, 'xp' => (int)$data->xp,
            'maxdaily' => (int)$data->maxdaily, 'maxawards' => (int)$data->maxawards,
            'starts' => (int)$data->starts, 'ends' => (int)$data->ends,
        ];
        self::save($courseid, $id, store::RULE, $record, 'rule');
    }

    /**
     * Save a mission, challenge, badge, timed event or reward.
     *
     * @param int $courseid Course ID
     * @param int $id Existing goal, or zero
     * @param stdClass $data Form values
     */
    public static function goal(int $courseid, int $id, stdClass $data): void {
        access::manager($courseid);
        $kind = $data->kind;
        $scope = $data->scope;
        $metric = $data->metric;
        $starts = (int)$data->starts;
        $ends = (int)$data->ends;
        $description = trim($data->description);
        if (
            !in_array($kind, goals::KINDS, true) || !in_array($scope, goals::SCOPES, true) ||
            !in_array($metric, goals::METRICS, true) || !in_array($data->icon, goals::ICONS, true) ||
            ($scope === 'team' && !in_array($kind, ['challenge', 'event'], true)) ||
            ($kind === 'event' && (!$starts || !$ends)) || !self::window($starts, $ends) ||
            ($kind !== 'reward' && ($data->target < 1 || $data->target > 1000000)) ||
            ($kind === 'reward' && $data->cost < 1) || $data->cost > 1000000 || $data->stock < 0 || $data->stock > 100000 ||
            \core_text::strlen($description) > 5000
        ) {
            self::invalid();
        }
        $record = (object)[
            'courseid' => $courseid, 'name' => self::name($data->name), 'description' => $description, 'kind' => $kind,
            'enabled' => (int)!empty($data->enabled), 'icon' => $data->icon, 'scope' => $scope, 'metric' => $metric,
            'target' => $kind === 'reward' ? 0 : (int)$data->target, 'cost' => $kind === 'reward' ? (int)$data->cost : 0,
            'stock' => $kind === 'reward' ? (int)$data->stock : 0, 'starts' => $starts, 'ends' => $ends,
        ];
        self::save($courseid, $id, store::GOAL, $record, 'goal');
    }

    /**
     * Change the course-level award exclusion of an enrolled participant.
     *
     * @param int $courseid Course ID
     * @param int $userid Participant
     * @param bool $excluded Desired exclusion
     */
    public static function exclude(int $courseid, int $userid, bool $excluded): void {
        access::manager($courseid);
        store::change($courseid, static function () use ($courseid, $userid, $excluded): void {
            $test = store::player($courseid, $userid);
            if (!access::participant(\context_course::instance($courseid), $userid) && (empty($test->id) || $excluded)) {
                access::deny();
            }
            $test->excluded = (int)$excluded;
            if ($excluded) {
                $test->consent = 0;
                $test->alias = '';
            }
            $id = store::save(store::PLAYER, $test);
            store::audit($courseid, 'exclusion', $id);
        });
    }

    /**
     * Save or update a scoped rule or goal and audit it.
     *
     * @param int $courseid Course ID
     * @param int $id Existing row
     * @param string $table Plugin-owned table
     * @param stdClass $record New values
     * @param string $action Audit action name
     */
    private static function save(int $courseid, int $id, string $table, stdClass $record, string $action): void {
        global $DB;
        store::change($courseid, static function () use ($DB, $courseid, $id, $table, $record, $action): void {
            if ($id) {
                $previous = $DB->get_record($table, ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
                if ($previous->kind !== $record->kind) {
                    self::invalid();
                }
                $record->id = $id;
            } else if ($DB->count_records($table, ['courseid' => $courseid]) >= 200) {
                throw new moodle_exception('cataloglimit', access::C);
            }
            $saved = store::save($table, $record);
            store::audit($courseid, $action, $saved);
        });
    }

    /**
     * Normalize a plain catalog name.
     *
     * @param string $name Input
     * @return string
     */
    private static function name(string $name): string {
        $name = trim($name);
        if (!$name || \core_text::strlen($name) > 180) {
            self::invalid();
        }
        return $name;
    }

    /**
     * Check an inclusive start and exclusive end.
     *
     * @param int $start Start
     * @param int $end End
     * @return bool
     */
    private static function window(int $start, int $end): bool {
        return $start >= 0 && $end >= 0 && (!$end || $end > $start);
    }

    /**
     * Reject an invalid configuration.
     */
    private static function invalid(): never {
        throw new moodle_exception('invalidconfiguration', access::C);
    }
}
