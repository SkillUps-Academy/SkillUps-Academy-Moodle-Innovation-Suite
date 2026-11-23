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

namespace tool_skillupscoursefactory;

/**
 * Normalize course rows and recheck permissions before mutation.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validator {
    /**
     * Collect per-row errors before creating any courses.
     */
    public static function preview(array $records, int $userid, string $timezone): array {
        $result = [];
        $names = [];
        $codes = [];
        foreach ($records as $position => $record) {
            try {
                $fields = self::normalize($record, $timezone);
                self::authorize($fields, $userid);
                $name = \core_text::strtolower($fields['shortname']);
                $code = \core_text::strtolower($fields['idnumber']);
                if (isset($names[$name]) || ($code !== '' && isset($codes[$code]))) {
                    throw new \moodle_exception('duplicate', support::C);
                }
                self::unique($fields);
                $names[$name] = true;
                if ($code !== '') {
                    $codes[$code] = true;
                }
                $result[$position] = ['fields' => $fields, 'error' => ''];
            } catch (\moodle_exception $error) {
                $result[$position] = [
                    'fields' => ['fullname' => clean_param($record['fullname'] ?? '', PARAM_TEXT),
                        'shortname' => clean_param($record['shortname'] ?? '', PARAM_TEXT)],
                    'error' => $error->errorcode,
                ];
            }
        }
        return $result;
    }

    /**
     * Build an immutable course and enrollment request.
     */
    private static function normalize(array $row, string $timezone): array {
        global $DB;
        $fields = [];
        foreach (['fullname' => 254, 'shortname' => 100, 'idnumber' => 100, 'period' => 80] as $key => $length) {
            $text = trim((string)($row[$key] ?? ''));
            if (
                \core_text::strlen($text) > $length || $text !== clean_param($text, PARAM_TEXT)
                    || ($text === '' && in_array($key, ['fullname', 'shortname'], true))
            ) {
                throw new \moodle_exception('invalidrow', support::C);
            }
            $fields[$key] = $text;
        }
        $fields['category'] = self::id($row['category'] ?? '');
        $fields['source'] = self::id($row['source'] ?? '');
        $template = self::id($row['template'] ?? '');
        if ($template) {
            if ($fields['source'] || !($record = $DB->get_record(jobs::TPL, ['id' => $template]))) {
                throw new \moodle_exception('invalidtemplate', support::C);
            }
            $fields['source'] = (int)$record->sourceid;
        }
        $fields['startdate'] = self::date($row['startdate'] ?? '', $timezone);
        $fields['enddate'] = self::date($row['enddate'] ?? '', $timezone, true);
        if ($fields['enddate'] && $fields['startdate'] > $fields['enddate']) {
            throw new \moodle_exception('invaliddate', support::C);
        }
        $visible = trim((string)($row['visible'] ?? ''));
        if (!in_array($visible, ['', '0', '1'], true)) {
            throw new \moodle_exception('invalidrow', support::C);
        }
        $fields['visible'] = (int)$visible;
        $fields['staff'] = self::staff($row);
        $fields['periodfield'] = $fields['period'] === '' ? '' : (string)get_config(support::C, 'periodfield');
        return $fields;
    }

    /**
     * Resolve site-configured employee roles and exact Moodle user IDs.
     */
    private static function staff(array $row): array {
        global $DB;
        $staff = [];
        foreach (['teachers' => 'teacherrole', 'coordinators' => 'coordinatorrole'] as $key => $setting) {
            $raw = trim((string)($row[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            $role = (int)get_config(support::C, $setting);
            if (!$role && $key === 'teachers') {
                $role = (int)$DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            }
            $ids = preg_split('/[;\s]+/', $raw);
            if (!$role || count($ids) > 25) {
                throw new \moodle_exception('invalidstaff', support::C);
            }
            foreach ($ids as $token) {
                $id = self::id($token);
                if (!$id) {
                    throw new \moodle_exception('invalidstaff', support::C);
                }
                $staff[$id . ':' . $role] = ['userid' => $id, 'role' => $role, 'setting' => $setting];
            }
        }
        return array_values($staff);
    }

    /**
     * Enforce current actor, source, category and role permissions.
     */
    public static function authorize(array $fields, int $actor): void {
        $context = support::destination($fields['category'], $actor, (bool)$fields['source']);
        if (!support::can_operate($actor) || !$context || ($fields['source'] && !support::source($fields['source'], $actor))) {
            throw new \moodle_exception('denied', support::C);
        }
        if ($fields['visible'] && !has_capability('moodle/course:visibility', $context, $actor)) {
            throw new \moodle_exception('denied', support::C);
        }
        if ($fields['period'] !== '' && $fields['periodfield'] !== (string)get_config(support::C, 'periodfield')) {
            throw new \moodle_exception('periodunavailable', support::C);
        }
        self::check_staff($fields, $context, $actor);
    }

    /**
     * Require manual enrollment and allowed role assignments in this context.
     */
    public static function check_staff(array $fields, \context $context, int $actor): void {
        if (!$fields['staff']) {
            return;
        }
        global $DB;
        if (!enrol_is_enabled('manual') || !has_capability('enrol/manual:enrol', $context, $actor)) {
            throw new \moodle_exception('invalidstaff', support::C);
        }
        $assignable = $context->contextlevel === CONTEXT_COURSE
            ? get_assignable_roles($context, ROLENAME_SHORT, false, $actor)
            : [];
        foreach ($fields['staff'] as $person) {
            $currentrole = (int)get_config(support::C, $person['setting']);
            if (!$currentrole && $person['setting'] === 'teacherrole') {
                $currentrole = (int)$DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            }
            $user = \core_user::get_user($person['userid']);
            $allowed = $context->contextlevel === CONTEXT_COURSE
                ? isset($assignable[$person['role']])
                : self::category_role($context, $actor, $person['role']);
            if (
                $currentrole !== $person['role'] || !$allowed
                    || !$user || $user->deleted || $user->suspended || !$user->confirmed || isguestuser($user)
            ) {
                throw new \moodle_exception('invalidstaff', support::C);
            }
        }
    }

    /**
     * Check assignability to a future course from the encompassing category.
     */
    private static function category_role(\context $context, int $actor, int $role): bool {
        global $DB;
        if (!has_capability('moodle/role:assign', $context, $actor) || !in_array(CONTEXT_COURSE, get_role_contextlevels($role))) {
            return false;
        }
        if (is_siteadmin($actor)) {
            return true;
        }
        foreach (get_user_roles($context, $actor, true) as $assignment) {
            if ($DB->record_exists('role_allow_assign', ['roleid' => $assignment->roleid, 'allowassign' => $role])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect collisions on normalized Moodle destination identifiers.
     */
    public static function unique(array $fields, int $owned = 0): void {
        global $DB;
        foreach (['shortname', 'idnumber'] as $field) {
            if ($fields[$field] === '') {
                continue;
            }
            $equal = $DB->sql_equal($field, ':identity', false);
            $params = ['identity' => $fields[$field], 'owned' => $owned];
            if ($DB->record_exists_select('course', "$equal AND id <> :owned", $params)) {
                throw new \moodle_exception('duplicate', support::C);
            }
        }
    }

    /**
     * Parse a positive integer identifier or an omitted optional value.
     */
    private static function id(mixed $raw): int {
        $value = trim((string)$raw);
        if ($value === '') {
            return 0;
        }
        if (!preg_match('/\A[1-9][0-9]{0,9}\z/D', $value)) {
            throw new \moodle_exception('invalidrow', support::C);
        }
        return (int)$value;
    }

    /**
     * Validate a calendar date in the requesting user's effective time zone.
     */
    private static function date(mixed $raw, string $timezone, bool $end = false): int {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return $end ? 0 : time();
        }
        $zone = new \DateTimeZone($timezone);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $zone);
        if (!$date || $date->format('Y-m-d') !== $raw || $date->getTimestamp() <= 0) {
            throw new \moodle_exception('invaliddate', support::C);
        }
        return ($end ? $date->setTime(23, 59, 59) : $date)->getTimestamp();
    }
}
