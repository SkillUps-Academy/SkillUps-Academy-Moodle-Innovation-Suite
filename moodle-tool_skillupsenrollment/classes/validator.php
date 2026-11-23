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

namespace tool_skillupsenrollment;

/**
 * Resolve every imported enrollment intent and validate its current native scope.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validator {
    /** Supported operation names. */
    public const ACTIONS = ['enrol', 'role', 'suspend', 'reactivate'];

    /**
     * Validate rows one by one without writing any native Moodle data.
     */
    public static function preview(array $inputs, int $actor): array {
        $results = [];
        $seen = [];
        foreach ($inputs as $position => $input) {
            try {
                $row = self::resolve($input, $actor);
                $observed = self::observe($row, $actor);
                $identity = implode(':', [$row['courseid'], $row['instanceid'], $row['userid']]);
                if (isset($seen[$identity])) {
                    throw new \moodle_exception('duplicate', support::C);
                }
                $seen[$identity] = true;
                $results[$position] = $row + ['state' => 'pending', 'errorcode' => '',
                    'payload' => support::json(['before' => $observed['before'], 'method' => $observed['instance']->enrol])];
            } catch (\moodle_exception $error) {
                // A rejected row stores its position and a reason without retaining submitted personal fields.
                $results[$position] = [
                    'action' => 'invalid', 'state' => 'invalid', 'payload' => '{}', 'errorcode' => $error->errorcode,
                ];
            }
        }
        return $results;
    }

    /**
     * Resolve immutable identifiers and select the intended enabled enrolment instance.
     */
    private static function resolve(array $input, int $actor): array {
        global $DB, $CFG;
        $action = strtolower(trim((string)($input['action'] ?? '')));
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \moodle_exception('invalidrow', support::C);
        }
        $courseid = self::course($input, $actor);
        if (!support::can_report($courseid, $actor)) {
            throw new \moodle_exception('denied', support::C);
        }
        $id = self::id($input['userid'] ?? '');
        $name = trim($input['username'] ?? '');
        if ((bool)$id === ($name !== '') || strlen($name) > 100) {
            throw new \moodle_exception('invaliduser', support::C);
        }
        if (!$id) {
            $id = (int)$DB->get_field('user', 'id', ['username' => $name, 'mnethostid' => $CFG->mnet_localhost_id]);
        }
        if (!$id) {
            throw new \moodle_exception('invaliduser', support::C);
        }
        $instanceid = self::id($input['instanceid'] ?? '');
        $method = strtolower(trim($input['method'] ?? ''));
        if ($method !== '' && !in_array($method, support::METHODS, true)) {
            throw new \moodle_exception('invalidmethod', support::C);
        }
        $instance = self::select_instance($courseid, $method, $instanceid);
        if (in_array($action, ['enrol', 'role'], true) && $instance->enrol !== 'manual') {
            throw new \moodle_exception('invalidmethod', support::C);
        }
        $role = trim($input['role'] ?? '');
        $group = self::id($input['groupid'] ?? '');
        $roleid = 0;
        if ($action === 'enrol' || $action === 'role') {
            if ($role === '' && $action === 'enrol') {
                $roleid = (int)$instance->roleid;
            } else if ($role !== '' && strlen($role) <= 100) {
                $criterion = ctype_digit($role) ? ['id' => self::id($role)] : ['shortname' => $role];
                $roleid = (int)$DB->get_field('role', 'id', $criterion);
            }
            if (!$roleid) {
                throw new \moodle_exception('invalidrole', support::C);
            }
        } else if ($role !== '' || $group) {
            throw new \moodle_exception('invalidrow', support::C);
        }
        return ['action' => $action, 'courseid' => $courseid, 'userid' => $id, 'instanceid' => (int)$instance->id,
            'roleid' => $roleid, 'groupid' => $group];
    }

    /**
     * Resolve exactly one native course reference or authorized Course Factory result.
     */
    private static function course(array $input, int $actor): int {
        global $DB;
        $id = self::id($input['courseid'] ?? '');
        $short = trim($input['shortname'] ?? '');
        $job = self::id($input['factory_job'] ?? '');
        $line = self::id($input['factory_row'] ?? '');
        if (($id ? 1 : 0) + ($short !== '' ? 1 : 0) + ($job || $line ? 1 : 0) !== 1 || strlen($short) > 100) {
            throw new \moodle_exception('invalidcourse', support::C);
        }
        if ($job || $line) {
            if (
                !$job || !$line || !class_exists(\tool_skillupscoursefactory\integration\course_lookup::class)
                    || \tool_skillupscoursefactory\integration\course_lookup::VERSION !== 1
            ) {
                throw new \moodle_exception('invalidfactory', support::C);
            }
            $id = \tool_skillupscoursefactory\integration\course_lookup::completed_course($job, $line, $actor);
            if (!$id) {
                throw new \moodle_exception('invalidfactory', support::C);
            }
        } else if (!$id) {
            $id = (int)$DB->get_field('course', 'id', ['shortname' => $short]);
        }
        if ($id <= SITEID) {
            throw new \moodle_exception('invalidcourse', support::C);
        }
        return $id;
    }

    /**
     * Resolve an enabled allow-listed method instance without guessing an ambiguous match.
     */
    private static function select_instance(int $course, string $method, int $id = 0): \stdClass {
        $instances = array_filter(
            enrol_get_instances($course, true),
            static fn($item) => in_array($item->enrol, support::METHODS, true)
                && ($id ? ((int)$item->id === $id && ($method === '' || $item->enrol === $method))
                    : $item->enrol === ($method ?: 'manual'))
        );
        if (count($instances) !== 1) {
            throw new \moodle_exception('invalidmethod', support::C);
        }
        return reset($instances);
    }

    /**
     * Verify access and capture only the native state relevant to this operation.
     */
    public static function observe(array $row, int $actor, string $method = ''): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        if (!support::can_operate($actor) || !support::can_report($row['courseid'], $actor)) {
            throw new \moodle_exception('denied', support::C);
        }
        $course = get_course($row['courseid']);
        $context = \context_course::instance($course->id);
        $instance = self::select_instance((int)$course->id, $method, $row['instanceid']);
        $plugin = enrol_get_plugin($instance->enrol);
        self::authorize_method($row['action'], $instance, $plugin, $context, $actor);
        $user = \core_user::get_user($row['userid']);
        if (
            !$user || $user->deleted || !$user->confirmed || isguestuser($user)
                || (int)$user->mnethostid !== (int)$CFG->mnet_localhost_id
                || ($row['action'] !== 'suspend' && $user->suspended)
        ) {
            throw new \moodle_exception('invaliduser', support::C);
        }
        $enrolment = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id], 'id,status');
        if (!$enrolment && $row['action'] !== 'enrol') {
            throw new \moodle_exception('noenrolment', support::C);
        }
        $rolepresent = true;
        if ($row['roleid']) {
            $assignable = get_assignable_roles($context, ROLENAME_SHORT, false, $actor);
            if (!isset($assignable[$row['roleid']]) || $plugin->roles_protected()) {
                throw new \moodle_exception('invalidrole', support::C);
            }
            $rolepresent = $DB->record_exists('role_assignments', [
                'contextid' => $context->id, 'userid' => $user->id, 'roleid' => $row['roleid'],
                'component' => '', 'itemid' => 0,
            ]);
        }
        $group = self::group($row['groupid'], $course, $context, $actor);
        if (
            support::limited_groups($course, $context, $actor)
                && !support::share_group((int)$course->id, $actor, (int)$user->id)
                && ($enrolment || $row['action'] !== 'enrol' || !$group)
        ) {
            throw new \moodle_exception('denied', support::C);
        }
        if ($group && $enrolment && (int)$enrolment->status !== ENROL_USER_ACTIVE) {
            throw new \moodle_exception('groupinactive', support::C);
        }
        $before = [
            'enrolled' => (int)($enrolment->id ?? 0), 'status' => isset($enrolment->status) ? (int)$enrolment->status : null,
            'role' => $rolepresent, 'group' => !$group || groups_is_member($group->id, $user->id),
        ];
        return compact('before', 'instance', 'plugin', 'context', 'user', 'group');
    }

    /**
     * Use the permissions associated with the operation before reading the user's enrollment.
     */
    private static function authorize_method(
        string $action,
        \stdClass $instance,
        ?\enrol_plugin $plugin,
        \context_course $context,
        int $actor
    ): void {
        if (!$plugin || !enrol_is_enabled($instance->enrol)) {
            throw new \moodle_exception('invalidmethod', support::C);
        }
        if (in_array($action, ['role', 'enrol'], true) && $instance->enrol !== 'manual') {
            throw new \moodle_exception('invalidmethod', support::C);
        }
        $cap = 'enrol/' . $instance->enrol . ':' . ($action === 'enrol' ? 'enrol' : 'manage');
        $allowed = $action === 'enrol' ? $plugin->allow_enrol($instance) : $plugin->allow_manage($instance);
        if (!$allowed || !has_capability($cap, $context, $actor)) {
            throw new \moodle_exception('denied', support::C);
        }
    }

    /**
     * Authorize a group in the same course.
     */
    private static function group(int $id, \stdClass $course, \context_course $context, int $actor): ?\stdClass {
        global $DB;
        if (!$id) {
            return null;
        }
        $group = $DB->get_record('groups', ['id' => $id, 'courseid' => $course->id]);
        if (
            !$group || !has_capability('moodle/course:managegroups', $context, $actor)
                || (support::limited_groups($course, $context, $actor) && !groups_is_member($id, $actor))
        ) {
            throw new \moodle_exception('invalidgroup', support::C);
        }
        return $group;
    }

    /**
     * Parse a strict positive Moodle ID.
     */
    private static function id(mixed $value): int {
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }
        if (!preg_match('/\A[1-9][0-9]{0,9}\z/D', $value)) {
            throw new \moodle_exception('invalidrow', support::C);
        }
        return (int)$value;
    }
}
