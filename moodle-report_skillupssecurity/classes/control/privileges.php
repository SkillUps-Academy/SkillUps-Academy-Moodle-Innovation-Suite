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

namespace report_skillupssecurity\control;

use core\context;
use report_skillupssecurity\access;

/**
 * Collect explicit administrative role evidence and site administrators.
 *
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privileges implements rule {
    /** @var array Selected high-impact Moodle capabilities. */
    public const CAPS = [
        'moodle/site:config', 'moodle/role:manage', 'moodle/role:assign', 'moodle/role:override',
        'moodle/user:loginas', 'moodle/user:delete', 'moodle/webservice:createtoken', 'moodle/course:delete',
    ];

    /**
     * Collect bounded site, category and course evidence.
     *
     * @param int $now Snapshot timestamp
     * @return array
     */
    public function evaluate(int $now): array {
        global $DB, $CFG;
        $system = \context_system::instance();
        $findings = [];
        $inventory = [];
        $admins = [];
        foreach (get_admins() as $person) {
            if (!$person->deleted) {
                $admins[$person->id] = true;
                $inventory[] = self::row($person, $system->id, 0, ['siteadmin']);
                $this->signals($findings, $person, $system->id, $now);
            }
        }
        foreach (['defaultuserroleid', 'guestroleid', 'defaultfrontpageroleid'] as $setting) {
            $roleid = (int)($CFG->$setting ?? 0);
            $scope = $setting === 'defaultfrontpageroleid' ? \context_course::instance(SITEID) : $system;
            $caps = $roleid ? self::allows($roleid, $scope) : [];
            if ($caps) {
                $findings[] = observation::make('defaultrole', 'risk', 'high', 0, $scope->id, [
                    'roleid' => $roleid, 'setting' => $setting, 'capabilities' => $caps,
                ], $setting);
            }
        }
        $limit = max(100, min(10000, (int)(get_config(access::C, 'assignmentlimit') ?: 2000)));
        $candidates = [];
        foreach (self::CAPS as $capability) {
            $candidates += get_roles_with_capability($capability, CAP_ALLOW);
        }
        $condition = 'ctx.contextlevel = :systemonly';
        $parameters = [
            'sys' => CONTEXT_SYSTEM, 'category' => CONTEXT_COURSECAT, 'course' => CONTEXT_COURSE,
            'guest' => 'guest', 'systemonly' => CONTEXT_SYSTEM,
        ];
        if ($candidates) {
            [$in, $rolescope] = $DB->get_in_or_equal(array_keys($candidates), SQL_PARAMS_NAMED, 'candidate');
            $condition .= " OR ra.roleid $in";
            $parameters += $rolescope;
        }
        $rows = $DB->get_records_sql(
            "SELECT ra.id, ra.roleid, ra.userid, ra.contextid, ctx.contextlevel, r.archetype,
                    u.suspended, u.lastaccess, u.timecreated
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
               JOIN {role} r ON r.id = ra.roleid
               JOIN {user} u ON u.id = ra.userid
              WHERE ctx.contextlevel IN (:sys, :category, :course) AND u.deleted = 0 AND u.username <> :guest
                AND ($condition)
           ORDER BY ra.id ASC",
            $parameters,
            0,
            $limit + 1
        );
        $complete = count($rows) <= $limit;
        $roles = [];
        $effective = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            $scope = (int)$row->contextid;
            $role = (int)$row->roleid;
            $user = (int)$row->userid;
            if (
                $row->contextlevel == CONTEXT_SYSTEM &&
                in_array($row->archetype, ['student', 'teacher', 'editingteacher', 'guest', 'frontpage'], true)
            ) {
                $findings[] = observation::make(
                    'unusualscope',
                    'risk',
                    'medium',
                    $user,
                    $scope,
                    ['roleid' => $role],
                    (string)$role
                );
            }
            if (isset($admins[$user])) {
                continue;
            }
            $context = context::instance_by_id($scope, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            $candidates = $roles[$scope][$role] ??= self::allows($role, $context);
            if (!$candidates) {
                continue;
            }
            $actual = $effective[$scope][$user] ??= array_filter(self::CAPS, static fn(string $cap): bool =>
                has_capability($cap, $context, $user, false));
            $grants = $row->suspended ? $candidates : array_values(array_intersect($candidates, $actual));
            if ($grants) {
                $person = (object)['id' => $user, 'suspended' => $row->suspended,
                    'lastaccess' => $row->lastaccess, 'timecreated' => $row->timecreated];
                $inventory["$scope:$role:$user"] = self::row($person, $scope, $role, $grants);
                $this->signals($findings, $person, $scope, $now);
            }
        }
        return ['findings' => $findings, 'inventory' => array_values($inventory), 'coverage' => [
            'defaultrole' => true, 'unusualscope' => $complete,
            'privilegedinactive' => $complete, 'suspendedprivilege' => $complete,
        ]];
    }

    /**
     * Read selected capabilities allowed by one role at a context.
     *
     * @param int $roleid Role
     * @param context $context Scope
     * @return array
     */
    public static function allows(int $roleid, context $context): array {
        $permissions = role_context_capabilities($roleid, $context);
        return array_values(array_filter(self::CAPS, static fn(string $cap): bool =>
            (int)($permissions[$cap] ?? CAP_INHERIT) === CAP_ALLOW));
    }

    /**
     * Add inactivity and suspended-account evidence.
     *
     * @param array $findings Observations
     * @param \stdClass $person User
     * @param int $scope Context
     * @param int $now Time
     */
    private function signals(array &$findings, \stdClass $person, int $scope, int $now): void {
        $last = $person->lastaccess ?: $person->timecreated;
        if ($person->suspended) {
            $findings[] = observation::make('suspendedprivilege', 'confirmed', 'medium', $person->id, $scope);
        } else if ($last > 0 && $last <= $now - accounts::days() * DAYSECS) {
            $findings[] = observation::make('privilegedinactive', 'risk', 'high', $person->id, $scope, [
                'lastaccess' => (int)$person->lastaccess, 'timecreated' => (int)$person->timecreated,
                'threshold' => accounts::days(),
            ]);
        }
    }

    /**
     * Build a privilege snapshot row.
     *
     * @param \stdClass $person User
     * @param int $context Context
     * @param int $role Role
     * @param array $caps Matched grants
     * @return array
     */
    private static function row(\stdClass $person, int $context, int $role, array $caps): array {
        return [
            'userid' => $person->id, 'contextid' => $context, 'roleid' => $role, 'suspended' => $person->suspended,
            'lastaccess' => $person->lastaccess, 'capabilities' => json_encode($caps, JSON_THROW_ON_ERROR),
        ];
    }
}
