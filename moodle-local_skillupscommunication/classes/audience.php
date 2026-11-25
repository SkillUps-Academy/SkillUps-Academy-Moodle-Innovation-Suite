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

namespace local_skillupscommunication;

/**
 * Resolve and revalidate authorized audience intersections using Moodle data.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audience {
    /** @var array Configurable filters in a stable order. */
    public const FILTERS = ['courseid', 'groupid', 'roleid', 'cohortid', 'programid', 'campusid', 'setid'];

    /**
     * Normalize request and stored selectors.
     *
     * @param array $data Filter values
     * @return array
     */
    public static function normalize(array $data): array {
        $selection = [];
        foreach (self::FILTERS as $key) {
            $selection[$key] = (int)($data[$key] ?? 0);
        }
        return $selection;
    }

    /**
     * Combine organizational mappings into the narrowest authorized category.
     *
     * @param \context $scope Campaign context
     * @param array $selector Normalized filters
     * @param int $actor Authorizing actor
     * @return \context
     */
    private static function anchor(\context $scope, array $selector, int $actor): \context {
        $anchor = $scope;
        foreach (['program', 'campus'] as $kind) {
            $id = $selector[$kind . 'id'];
            if (!$id) {
                continue;
            }
            $record = store::get(store::SEG, $id);
            $segment = access::scope((int)$record->scopeid);
            $target = access::scope((int)$record->targetcontext);
            if (
                $record->kind !== $kind || !access::contains($scope, $segment) || !access::contains($segment, $target) ||
                !access::can($segment, 'view', $actor) || !access::can($target, 'view', $actor)
            ) {
                access::fail();
            }
            if (access::contains($anchor, $target)) {
                $anchor = $target;
            } else if (!access::contains($target, $anchor)) {
                access::fail('incompatible');
            }
        }
        return $anchor;
    }

    /**
     * Build the eligibility query in the exact context of the issuing account.
     *
     * @param \context $scope Campaign context
     * @param array $input Normalized or raw filters
     * @param int $actor Authorizing account
     * @return array WHERE clause and parameters
     */
    private static function query(\context $scope, array $input, int $actor): array {
        global $DB;
        $selector = self::normalize($input);
        $anchor = self::anchor($scope, $selector, $actor);
        $params = ['guest' => 'guest', 'nologin' => 'nologin'];
        $conditions = [
            'u.id > 0', 'u.deleted = 0', 'u.suspended = 0', 'u.confirmed = 1', 'u.username <> :guest', 'u.auth <> :nologin',
        ];
        $role = $selector['roleid'];
        $courseid = $selector['courseid'];
        $group = $selector['groupid'];
        if (
            $role > 0 && !$DB->record_exists('role', ['id' => $role]) ||
            ($role < 0 && ($role !== -1 || $scope->contextlevel !== CONTEXT_SYSTEM || $courseid ||
                $anchor->contextlevel !== CONTEXT_SYSTEM))
        ) {
            access::fail();
        }
        if ($group && !$courseid) {
            access::fail('invalidgroup');
        }
        if ($courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (
                !$course || !$context || $course->id == SITEID || !access::contains($anchor, $context) ||
                !access::can($context, 'view', $actor) ||
                (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $actor))
            ) {
                access::fail();
            }
            if ($group && !$DB->record_exists('groups', ['id' => $group, 'courseid' => $courseid])) {
                access::fail('invalidgroup');
            }
            if (
                (int)$course->groupmode === SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context, $actor) &&
                (!$group || !groups_is_member($group, $actor))
            ) {
                access::fail('invalidgroup');
            }
            [$enrolled, $bindings] = get_enrolled_sql($context, '', $group, true);
            $conditions[] = "u.id IN ($enrolled)";
            $params += $bindings;
            if ($role > 0) {
                $contextids = $context->get_parent_context_ids(true);
                [$locations, $bindings] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'coursecontext');
                $conditions[] = "EXISTS (SELECT 1 FROM {role_assignments} r WHERE r.userid = u.id
                                       AND r.roleid = :course_role AND r.contextid $locations)";
                $params += $bindings + ['course_role' => $role];
            }
        } else if ($anchor->contextlevel !== CONTEXT_SYSTEM) {
            $params += ['root' => $anchor->path, 'subcat' => $anchor->path . '/%',
                'courselevel' => CONTEXT_COURSE, 'categorylevel' => CONTEXT_COURSECAT,
                'active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED, 'begin' => time(), 'end' => time()];
            $enrollees = "EXISTS (SELECT 1 FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid JOIN {context} c ON c.instanceid = e.courseid
                   JOIN {course} sc ON sc.id = e.courseid
                  WHERE ue.userid = u.id AND ue.status = :active AND e.status = :enabled
                    AND ue.timestart <= :begin AND (ue.timeend = 0 OR ue.timeend > :end)
                    AND c.contextlevel = :courselevel AND c.path LIKE :subcat";
            $assigned = "EXISTS (SELECT 1 FROM {role_assignments} a JOIN {context} ac ON ac.id = a.contextid
                          WHERE a.userid = u.id AND ac.contextlevel = :categorylevel
                            AND (ac.path = :root OR ac.path LIKE :delegated))";
            $params['delegated'] = $anchor->path . '/%';
            if (!has_capability('moodle/course:viewhiddencourses', $anchor, $actor)) {
                $enrollees .= ' AND sc.visible = 1';
            }
            $descendants = $DB->get_records_select(
                'context',
                'contextlevel = :level AND path LIKE :path',
                ['level' => CONTEXT_COURSECAT, 'path' => $anchor->path . '/%'],
                'depth ASC',
                'id,contextlevel,instanceid,path,depth,locked'
            );
            foreach ($descendants as $child) {
                $category = \context_coursecat::instance($child->instanceid, IGNORE_MISSING);
                if (!$category || !access::can($category, 'view', $actor)) {
                    $key = 'child' . $child->id;
                    $enrollees .= " AND c.path NOT LIKE :{$key}courses";
                    $assigned = substr($assigned, 0, -1)
                        . " AND ac.path <> :{$key}exact AND ac.path NOT LIKE :{$key}delegate)";
                    $params[$key . 'courses'] = $child->path . '/%';
                    $params[$key . 'exact'] = $child->path;
                    $params[$key . 'delegate'] = $child->path . '/%';
                }
            }
            if ($role > 0) {
                $coursepath = $DB->sql_concat('rc.path', "'/%'");
                $enrollees .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {context} rc ON rc.id = ra.contextid
                                            WHERE ra.userid = u.id AND ra.roleid = :enrolledrole
                                              AND (c.id = rc.id OR c.path LIKE $coursepath))";
                $assigned = substr($assigned, 0, -1) . ' AND a.roleid = :directrole)';
                $params += ['enrolledrole' => $role, 'directrole' => $role];
            }
            $conditions[] = "($enrollees) OR $assigned)";
        } else if ($role > 0) {
            $conditions[] = 'EXISTS (SELECT 1 FROM {role_assignments} r WHERE r.userid = u.id AND r.roleid = :globalrole)';
            $params['globalrole'] = $role;
        } else if ($role === -1) {
            [$admins, $bindings] = $DB->get_in_or_equal(array_keys(get_admins()), SQL_PARAMS_NAMED, 'admin');
            $conditions[] = "u.id $admins";
            $params += $bindings;
        }
        if ($selector['cohortid']) {
            $cohort = $DB->get_record('cohort', ['id' => $selector['cohortid']]);
            $context = $cohort ? \context::instance_by_id($cohort->contextid, IGNORE_MISSING) : null;
            if (
                !$context || !access::contains($scope, $context) ||
                !has_capability('moodle/cohort:view', $context, $actor) ||
                (!$cohort->visible && !has_capability('moodle/cohort:manage', $context, $actor))
            ) {
                access::fail();
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM {cohort_members} m WHERE m.userid = u.id AND m.cohortid = :cohort)';
            $params['cohort'] = $cohort->id;
        }
        if ($selector['setid']) {
            $set = store::get(store::SEG, $selector['setid']);
            if (
                $set->kind !== 'set' || !access::contains($scope, access::scope((int)$set->scopeid)) ||
                !access::can(access::scope((int)$set->scopeid), 'view', $actor)
            ) {
                access::fail();
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM {' . store::MEMBER . '} m WHERE m.userid = u.id AND m.segmentid = :set)';
            $params['set'] = $set->id;
        }
        return [implode(' AND ', $conditions), $params];
    }

    /**
     * Resolve the complete bounded audience in deterministic order.
     *
     * @param \context $scope Campaign context
     * @param array $filters Audience intersection
     * @param int $actor Authorizing account
     * @param array $restrict Optional known target IDs for revalidation
     * @return int[]
     */
    public static function ids(\context $scope, array $filters, int $actor, array $restrict = []): array {
        global $DB;
        [$where, $params] = self::query($scope, $filters, $actor);
        if ($restrict) {
            [$selected, $bindings] = $DB->get_in_or_equal($restrict, SQL_PARAMS_NAMED, 'selectedid');
            $where .= " AND u.id $selected";
            $params += $bindings;
        }
        $limit = $restrict ? count($restrict) : store::limit() + 1;
        $rows = $DB->get_fieldset_sql("SELECT u.id FROM {user} u WHERE $where ORDER BY u.id", $params, 0, $limit);
        if (!$restrict && count($rows) > store::limit()) {
            access::fail('audiencelimit');
        }
        return array_map('intval', $rows);
    }

    /**
     * Fingerprint exact account IDs alongside the submitted revision.
     *
     * @param array $ids Ordered account IDs
     * @param int $revision Campaign revision
     * @return string
     */
    public static function fingerprint(array $ids, int $revision): string {
        return hash('sha256', $revision . ':' . implode(',', $ids));
    }
}
