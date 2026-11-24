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

namespace mod_skillupsattendance;

use context_module;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Resolve the native module and enforce its enrollment and group permissions.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity {
    /** @var string Activity component. */
    public const C = 'mod_skillupsattendance';
    /** @var string Capability prefix. */
    public const CAP = 'mod/skillupsattendance:';
    /** @var context_module Native module context. */
    public readonly context_module $context;

    /**
     * Bind configuration to Moodle's native course module.
     *
     * @param stdClass $record Attendance record
     * @param stdClass $cm Module record
     * @param stdClass $course Course
     */
    private function __construct(
        /** @var stdClass Attendance instance. */
        public readonly stdClass $record,
        /** @var stdClass Native module. */
        public readonly stdClass $cm,
        /** @var stdClass Course record. */
        public readonly stdClass $course,
    ) {
        $this->context = context_module::instance($cm->id);
    }

    /**
     * Require native course login and module visibility.
     *
     * @param int $id Course module
     * @return self
     */
    public static function open(int $id): self {
        global $DB;
        [$course, $info] = get_course_and_cm_from_cmid($id, 'skillupsattendance');
        require_login($course, true, $info);
        $cm = get_coursemodule_from_id('skillupsattendance', $id, $course->id, false, MUST_EXIST);
        $activity = new self($DB->get_record(store::ACTIVITY, ['id' => $cm->instance], '*', MUST_EXIST), $cm, $course);
        $activity->require('view');
        return $activity;
    }

    /**
     * Resolve an activity instance for internal callbacks and tasks.
     *
     * @param int $id Attendance instance
     * @return self
     */
    public static function by_instance(int $id): self {
        global $DB;
        $record = $DB->get_record(store::ACTIVITY, ['id' => $id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('skillupsattendance', $id, $record->course, false, MUST_EXIST);
        return new self($record, $cm, get_course($record->course));
    }

    /**
     * Require the current operator's module capability.
     *
     * @param string $action Capability suffix
     */
    public function require(string $action): void {
        require_capability(self::CAP . $action, $this->context);
    }

    /**
     * Inspect the capability of the active or provided user.
     *
     * @param string $action Capability suffix
     * @param int $userid Optional actor
     * @return bool
     */
    public function can(string $action, int $userid = 0): bool {
        return has_capability(self::CAP . $action, $this->context, $userid ?: null);
    }

    /**
     * List usable groups for the current Moodle operator.
     *
     * @return array
     */
    public function groups(): array {
        $rows = (int)groups_get_activity_groupmode($this->cm, $this->course) === NOGROUPS
            ? groups_get_all_groups($this->course->id, 0, $this->cm->groupingid)
            : groups_get_activity_allowed_groups($this->cm);
        $choices = $this->staff_group(0) ? [0 => self::text('allparticipants')] : [];
        foreach ($rows ?: [] as $group) {
            $choices[$group->id] = format_string($group->name, true, ['context' => $this->context]);
        }
        return $choices;
    }

    /**
     * Require valid groups and honor separate-group restrictions for staff.
     *
     * @param int $groupid Group or zero for the entire activity
     * @param int $userid Optional actor
     * @return bool
     */
    public function staff_group(int $groupid, int $userid = 0): bool {
        if (!$this->valid_group($groupid)) {
            return false;
        }
        if (
            (int)groups_get_activity_groupmode($this->cm, $this->course) !== SEPARATEGROUPS ||
                has_capability('moodle/site:accessallgroups', $this->context, $userid ?: null)
        ) {
            return true;
        }
        return $groupid > 0 && isset(groups_get_activity_allowed_groups($this->cm, $userid)[$groupid]);
    }

    /**
     * Limit groups to the active course and grouping.
     *
     * @param int $groupid Moodle group
     * @return bool
     */
    public function valid_group(int $groupid): bool {
        global $DB;
        if ($groupid === 0) {
            return true;
        }
        if (!$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $this->course->id])) {
            return false;
        }
        return !$this->cm->groupingid || $DB->record_exists('groupings_groups', [
            'groupingid' => $this->cm->groupingid, 'groupid' => $groupid,
        ]);
    }

    /**
     * Verify that a student can record or receive a mark in this audience.
     *
     * @param int $userid Student
     * @param int $groupid Seed group
     * @return bool
     */
    public function eligible(int $userid, int $groupid): bool {
        global $DB;
        if (
            !is_enrolled($this->context, $userid, self::CAP . 'recordown', true) ||
                !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])
        ) {
            return false;
        }
        if ($groupid) {
            return $this->valid_group($groupid) && groups_is_member($groupid, $userid);
        }
        if ($this->cm->groupingid) {
            $groups = groups_get_user_groups($this->course->id, $userid);
            return !empty($groups[$this->cm->groupingid]);
        }
        return true;
    }

    /**
     * Page through the native eligible enrollment set.
     *
     * @param int $groupid Group
     * @param int $offset Offset
     * @param int $limit Limit
     * @return array
     */
    public function roster(int $groupid, int $offset, int $limit): array {
        global $DB;
        if (!$this->valid_group($groupid)) {
            return [];
        }
        [$enrolled, $params] = get_enrolled_sql($this->context, self::CAP . 'recordown', $groupid, true);
        $scope = '';
        if (!$groupid && $this->cm->groupingid) {
            $scope = 'AND EXISTS (SELECT 1 FROM {groups_members} gm JOIN {groupings_groups} gg ON gg.groupid = gm.groupid
                WHERE gm.userid = u.id AND gg.groupingid = :attendancegrouping)';
            $params['attendancegrouping'] = $this->cm->groupingid;
        }
        return $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname FROM {user} u JOIN ($enrolled) en ON en.id = u.id
              WHERE u.deleted = 0 AND u.suspended = 0 $scope ORDER BY u.id",
            $params,
            $offset,
            $limit,
        );
    }

    /**
     * Initialize a native Moodle activity page.
     *
     * @param moodle_url $url Canonical URL
     * @param string $title Title
     */
    public function page(moodle_url $url, string $title): void {
        global $PAGE;
        $PAGE->set_url($url);
        $PAGE->set_context($this->context);
        $PAGE->set_cm($this->cm, $this->course, $this->record);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title($title);
        $PAGE->set_heading(format_string($this->course->fullname));
        $PAGE->set_cacheable(false);
    }

    /**
     * Construct a route scoped to this module.
     *
     * @param string $page Endpoint name
     * @param array $params Query parameters
     * @return moodle_url
     */
    public function url(string $page = 'view', array $params = []): moodle_url {
        return new moodle_url('/mod/skillupsattendance/' . $page . '.php', ['id' => $this->cm->id] + $params);
    }

    /**
     * Localise a label.
     *
     * @param string $key Identifier
     * @param mixed $value Optional value
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Raise a localised domain error.
     *
     * @param string $reason Language identifier
     * @return never
     */
    public static function fail(string $reason): never {
        throw new moodle_exception($reason, self::C);
    }
}
