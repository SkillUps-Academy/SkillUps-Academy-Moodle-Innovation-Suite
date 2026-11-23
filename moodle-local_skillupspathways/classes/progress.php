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

namespace local_skillupspathways;

use stdClass;

/**
 * Evaluate prerequisites and persist scoped learner state transitions.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress {
    /**
     * Evaluate an immutable version with current Moodle evidence.
     *
     * @param stdClass $version Published graph
     * @param int $userid Learner
     * @return array States, required completion counts and next steps
     */
    public static function evaluate(stdClass $version, int $userid): array {
        $graph = graph::parse($version->graph);
        $reader = new evidence($userid);
        $states = [];
        $rows = [];
        $passed = 0;
        $required = 0;
        foreach (graph::order($graph) as $node) {
            $parentstates = array_map(static fn(string $key): string => $states[$key], $node['parents']);
            $completedparents = count(array_keys($parentstates, 'complete', true));
            $available = !$parentstates || ($node['join'] === 'all'
                ? $completedparents === count($parentstates)
                : $completedparents > 0);
            $destination = $reader->destination($node);
            if (!$destination) {
                $state = 'unavailable';
            } else if (!$available) {
                $state = 'blocked';
            } else {
                $state = $reader->completed($node, $destination) ? 'complete' : 'ready';
            }
            $states[$node['key']] = $state;
            if ($node['required']) {
                $required++;
                $passed += (int)($state === 'complete');
            }
            $rows[] = [
                'key' => $node['key'], 'title' => $destination ? $node['title'] : access::text('restrictedstep'),
                'state' => $state, 'required' => $node['required'],
                'url' => $destination && $state !== 'blocked' ? $destination[2]->out(false) : '',
                'join' => $node['join'], 'parents' => $node['parents'], 'lane' => $node['lane'], 'row' => $node['row'],
                'condition' => $destination ? self::conditions($node) : '',
            ];
        }
        return ['states' => $states, 'rows' => $rows, 'passed' => $passed, 'required' => $required];
    }

    /**
     * Read the participant's pinned version, if any.
     *
     * @param int $pathid Path ID
     * @param int $userid Participant ID
     * @return stdClass|null
     */
    public static function get(int $pathid, int $userid): ?stdClass {
        global $DB;
        return $DB->get_record(store::PROGRESS, ['pathid' => $pathid, 'userid' => $userid]) ?: null;
    }

    /**
     * Start or check the active student's own itinerary.
     *
     * @param stdClass $path Scoped path
     * @param stdClass $course Entry course
     */
    public static function sync(stdClass $path, stdClass $course): void {
        global $USER;
        if (!access::enrolled($course, (int)$USER->id, true)) {
            access::deny();
        }
        self::refresh((int)$path->id, (int)$course->id, (int)$USER->id, true);
    }

    /**
     * Check authoritative results for a pinned learner without creating new enrollments.
     *
     * @param int $pathid Path ID
     * @param int $courseid Anchor course
     * @param int $userid Learner
     * @param bool $start Allow initial pinning from explicit learner action
     */
    public static function refresh(int $pathid, int $courseid, int $userid, bool $start = false): void {
        global $DB;
        store::atomic("path:$pathid", static function () use ($DB, $pathid, $courseid, $userid, $start): void {
            $path = $DB->get_record(store::PATH, ['id' => $pathid, 'courseid' => $courseid, 'enabled' => 1]);
            if (!$path || !$path->activeversion) {
                return;
            }
            $existing = self::get($pathid, $userid);
            if (!$existing && !$start) {
                return;
            }
            $version = store::version($pathid, (int)($existing->versionid ?? $path->activeversion));
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course || !access::enrolled($course, $userid, true)) {
                if ($existing) {
                    if ($existing->completedat) {
                        self::transition($pathid, $userid, (int)$version->id, '_path', 'complete', 'unavailable');
                    }
                    $DB->update_record(store::PROGRESS, ['id' => $existing->id, 'timechecked' => time(), 'completedat' => 0]);
                }
                return;
            }
            self::persist($pathid, $userid, $version, $existing);
        });
    }

    /**
     * Explicitly pin a scoped participant to the latest version, preserving the old timeline.
     *
     * @param stdClass $course Entry course
     * @param int $pathid Path ID
     * @param int $userid Learner ID
     * @param int $expected Latest version displayed to the manager
     * @param int $groupid Report group selected
     */
    public static function migrate(stdClass $course, int $pathid, int $userid, int $expected, int $groupid): void {
        access::manage((int)$course->id);
        $allowed = access::report_group($course, $groupid);
        if ($allowed && !groups_is_member($allowed, $userid)) {
            access::deny();
        }
        if (!access::enrolled($course, $userid, true)) {
            access::deny();
        }
        store::atomic("path:$pathid", static function () use ($course, $pathid, $userid, $expected): void {
            $path = store::path((int)$course->id, $pathid);
            $existing = self::get($pathid, $userid);
            if (!$path->enabled || !$expected || $path->activeversion != $expected || !$existing) {
                access::deny();
            }
            if ($existing->versionid == $expected) {
                return;
            }
            $oldversion = (int)$existing->versionid;
            $version = store::version($pathid, $expected);
            self::transition($pathid, $userid, $oldversion, '_version', 'pinned', 'migrated');
            $existing->states = '{}';
            $existing->completedat = 0;
            self::persist($pathid, $userid, $version, $existing);
            store::audit((int)$course->id, $pathid, 'migrated');
        });
    }

    /**
     * Append only changed results and atomically save the latest summary.
     *
     * @param int $pathid Path ID
     * @param int $userid Learner
     * @param stdClass $version Published version
     * @param stdClass|null $old Previous state
     */
    private static function persist(int $pathid, int $userid, stdClass $version, ?stdClass $old): void {
        global $DB;
        $result = self::evaluate($version, $userid);
        $previous = $old ? json_decode($old->states, true, 12, JSON_THROW_ON_ERROR) : [];
        foreach ($result['states'] as $node => $state) {
            $before = $previous[$node] ?? 'initial';
            if ($before !== $state) {
                self::transition($pathid, $userid, (int)$version->id, $node, $before, $state);
            }
        }
        $complete = $result['required'] > 0 && $result['passed'] === $result['required'];
        $oldtime = (int)($old->completedat ?? 0);
        $record = [
            'pathid' => $pathid, 'userid' => $userid, 'versionid' => $version->id,
            'states' => json_encode($result['states'], JSON_THROW_ON_ERROR),
            'passed' => $result['passed'], 'requiredcount' => $result['required'], 'timechecked' => time(),
            'completedat' => $complete ? ($oldtime ?: time()) : 0,
        ];
        if ($complete !== (bool)$oldtime) {
            self::transition(
                $pathid,
                $userid,
                (int)$version->id,
                '_path',
                $oldtime ? 'complete' : 'ready',
                $complete ? 'complete' : 'ready'
            );
        }
        if ($old) {
            $DB->update_record(store::PROGRESS, $record + ['id' => $old->id]);
        } else {
            $DB->insert_record(store::PROGRESS, $record);
        }
    }

    /**
     * Record a transition without saving native grades or competency assessments.
     *
     * @param int $pathid Path ID
     * @param int $userid Learner
     * @param int $versionid Version
     * @param string $node Node key
     * @param string $old Before
     * @param string $new After
     */
    private static function transition(int $pathid, int $userid, int $versionid, string $node, string $old, string $new): void {
        global $DB;
        $DB->insert_record(store::HISTORY, [
            'pathid' => $pathid, 'userid' => $userid, 'versionid' => $versionid, 'nodekey' => $node,
            'oldstate' => $old, 'newstate' => $new, 'timecreated' => time(),
        ]);
    }

    /**
     * Describe configured conditions without displaying any actual grades.
     *
     * @param array $node Graph node
     * @return string
     */
    private static function conditions(array $node): string {
        $parts = [];
        if ($node['completion']) {
            $parts[] = access::text('nativecompletion');
        }
        if ($node['grade'] >= 0) {
            $parts[] = access::text('minpercent', $node['grade']);
        }
        if ($node['competency']) {
            $parts[] = access::text('competencyrequired');
        }
        return implode(' · ', $parts);
    }
}
