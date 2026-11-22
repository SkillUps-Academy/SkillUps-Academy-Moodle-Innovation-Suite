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

namespace local_skillupsaigrading;

/**
 * Shared limits, authorization failures and durable workflow locks.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** Component and table prefix. */
    public const C = 'local_skillupsaigrading';
    /** Capability prefix. */
    public const CAP = 'local/skillupsaigrading';

    /**
     * Use a localized error without exposing submitted content.
     */
    public static function fail(string $code): never {
        throw new \moodle_exception($code, self::C);
    }

    /**
     * Lock activity state during short writes or a worker transition.
     */
    public static function lock(int $id, int $seconds = 5): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory(self::C);
        if (!$lock = $factory->get_lock('assignment_' . $id, $seconds)) {
            self::fail('busy');
        }
        return $lock;
    }

    /**
     * Resolve an active teacher account before transferring source evidence.
     */
    public static function active_user(int $id): \stdClass {
        $user = \core_user::get_user($id);
        if (!$user || $user->deleted || $user->suspended || !$user->confirmed || isguestuser($user)) {
            self::fail('accessdenied');
        }
        return $user;
    }
}
