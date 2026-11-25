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
 * Render native localized and escaped Moodle controls.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Translate one component phrase.
     *
     * @param string $key Language key
     * @param mixed $a Interpolation
     * @return string
     */
    public static function t(string $key, mixed $a = null): string {
        return get_string($key, access::C, $a);
    }

    /**
     * Compose a local route.
     *
     * @param string $page Entry point
     * @param array $params Parameters
     * @return \moodle_url
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupscommunication/' . $page . '.php', $params);
    }

    /**
     * Expand a permitted set of status or type keys.
     *
     * @param array $keys Language keys
     * @return array
     */
    public static function options(array $keys): array {
        return array_combine($keys, array_map([self::class, 't'], $keys));
    }

    /**
     * Render a local action button or link.
     *
     * @param \moodle_url $url Route
     * @param string $label Plain text
     * @param string $style Bootstrap classes
     * @return string
     */
    public static function link(\moodle_url $url, string $label, string $style = ''): string {
        return \html_writer::link($url, s($label), ['class' => $style]);
    }

    /**
     * Prepare a scoped report page with the native Moodle layout.
     *
     * @param string $page Entry point
     * @param array $params Route parameters
     * @param \context $scope Authorizing Moodle context
     */
    public static function page(string $page, array $params, \context $scope): void {
        global $PAGE, $SITE;
        $PAGE->set_context($scope);
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('report');
        $PAGE->set_show_course_index(false);
        $PAGE->set_cacheable(false);
        $PAGE->set_title(self::t('pluginname'));
        $PAGE->set_heading(format_string($SITE->fullname));
    }

    /**
     * Render an accessible responsive HTML table.
     *
     * @param array $head Column language keys
     * @param array $rows Escaped cell values
     * @return string
     */
    public static function table(array $head, array $rows): string {
        if (!$rows) {
            return \html_writer::tag('p', self::t('empty'), ['class' => 'text-muted']);
        }
        $table = new \html_table();
        $table->head = array_map([self::class, 't'], $head);
        $table->data = $rows;
        $table->attributes['class'] = 'generaltable';
        return \html_writer::div(\html_writer::table($table), 'table-responsive');
    }

    /**
     * Return authorized selector options for a campaign scope.
     *
     * @param \context $scope Moodle context
     * @return array
     */
    public static function selectors(\context $scope): array {
        global $DB;
        $unset = [0 => self::t('any')];
        $options = array_fill_keys(audience::FILTERS, $unset);
        if ($scope->contextlevel === CONTEXT_SYSTEM) {
            $options['roleid'][-1] = self::t('siteadmins');
        }
        foreach (get_all_roles() as $role) {
            $options['roleid'][$role->id] = role_get_name($role, null, ROLENAME_ALIAS);
        }
        foreach (get_courses('all') as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $context = \context_course::instance($course->id);
            if (!access::contains($scope, $context) || !access::can($context, 'view')) {
                continue;
            }
            $name = format_string($course->fullname, true, ['escape' => false, 'context' => $context]);
            $options['courseid'][$course->id] = $name;
            foreach (groups_get_all_groups($course->id) as $group) {
                if (
                    (int)$course->groupmode !== SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context) ||
                    groups_is_member($group->id, $GLOBALS['USER']->id)
                ) {
                    $options['groupid'][$group->id] = $name . ' / ' . $group->name;
                }
            }
        }
        foreach ($DB->get_records('cohort', [], 'name') as $cohort) {
            $context = \context::instance_by_id($cohort->contextid, IGNORE_MISSING);
            if (
                $context && access::contains($scope, $context) && has_capability('moodle/cohort:view', $context) &&
                ($cohort->visible || has_capability('moodle/cohort:manage', $context))
            ) {
                $options['cohortid'][$cohort->id] = $cohort->name;
            }
        }
        foreach ($DB->get_records(store::SEG, [], 'name') as $segment) {
            $context = \context::instance_by_id($segment->scopeid, IGNORE_MISSING);
            if ($context && access::contains($scope, $context) && access::can($context, 'view')) {
                $options[$segment->kind . 'id'][$segment->id] = $segment->name;
            }
        }
        return $options;
    }
}
