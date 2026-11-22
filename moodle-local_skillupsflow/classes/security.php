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

namespace local_skillupsflow;

/**
 * Actor, context and object authorization for delayed executions.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class security {
    /**
     * Read a current execution account with a freshly enforced site capability.
     */
    public static function actor(int $id): \stdClass {
        $actor = \core_user::get_user($id);
        if (
            !$actor || !empty($actor->deleted) || !empty($actor->suspended) || empty($actor->confirmed) ||
                isguestuser($actor) || !has_capability('local/skillupsflow:manage', \context_system::instance(), $actor)
        ) {
            throw new \moodle_exception('actorunavailable', 'local_skillupsflow');
        }
        return $actor;
    }

    /**
     * Resolve optional trigger and node targets.
     */
    public static function targets(array $node, \stdClass $run): array {
        return [(int)(($node['userid'] ?? 0) ?: $run->userid), (int)(($node['courseid'] ?? 0) ?: $run->courseid)];
    }

    /**
     * Require a non-site course and the requested course permission.
     */
    public static function course(int $id, \stdClass $actor, string $capability): \context_course {
        if ($id <= 0 || $id === SITEID) {
            throw new \moodle_exception('targetunavailable', 'local_skillupsflow');
        }
        $context = \context_course::instance($id);
        require_capability($capability, $context, $actor);
        return $context;
    }

    /**
     * Resolve a real non-guest account, rejecting deleted and unconfirmed users.
     */
    public static function user(int $id): \stdClass {
        $user = $id ? \core_user::get_user($id) : null;
        if (!$user || !empty($user->deleted) || empty($user->confirmed) || isguestuser($user)) {
            throw new \moodle_exception('targetunavailable', 'local_skillupsflow');
        }
        return $user;
    }
}
