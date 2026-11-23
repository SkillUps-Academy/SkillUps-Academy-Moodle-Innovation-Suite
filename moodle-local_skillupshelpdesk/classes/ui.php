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

namespace local_skillupshelpdesk;

use context_system;
use moodle_url;
use stdClass;

/**
 * Shared Moodle page setup and localized display values.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Initialize a Helpdesk page after normal login and authorization.
     *
     * @param moodle_url $url Canonical URL
     * @param string $heading Page heading
     */
    public static function page(moodle_url $url, string $heading): void {
        global $PAGE;
        access::require(access::portal());
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title($heading);
        $PAGE->set_heading(access::text('pluginname'));
        $PAGE->navbar->add(access::text('pluginname'), access::url());
    }

    /**
     * Expose shared navigation links to Moodle Mustache.
     *
     * @return array
     */
    public static function nav(): array {
        return [
            'portalurl' => access::url()->out(false),
            'openurl' => access::can('request') ? access::url('create')->out(false) : '',
            'knowledgeurl' => access::url('knowledge')->out(false),
            'reportsurl' => access::can('work') || access::can('supervise') ? access::url('reports')->out(false) : '',
            'adminurl' => access::can('configure') ? access::url('manage')->out(false) : '',
        ];
    }

    /**
     * Load accessible course options for intake. Do not enumerate inaccessible site courses.
     *
     * @return array
     */
    public static function courses(): array {
        global $USER;
        $options = [0 => access::text('nocourse')];
        foreach (enrol_get_users_courses($USER->id, true, 'fullname') as $course) {
            if ($course->id != SITEID && can_access_course($course, $USER, '', true)) {
                $context = \context_course::instance($course->id);
                $options[$course->id] = format_string($course->fullname, true, ['context' => $context]);
            }
        }
        return $options;
    }

    /**
     * Resolve usernames without exposing emails or profiles.
     *
     * @param int $id Moodle user ID
     * @return string
     */
    public static function person(int $id): string {
        if (!$id) {
            return access::text('unassigned');
        }
        $user = \core_user::get_user($id);
        return $user && !$user->deleted ? fullname($user) : access::text('anonymous');
    }

    /**
     * Present a registered course or dimension.
     *
     * @param string $kind Bucket kind
     * @param int $id Group key
     * @return string
     */
    public static function bucket(string $kind, int $id): string {
        if ($kind === 'assignee') {
            return self::person($id);
        }
        if ($kind === 'course') {
            if (!$id) {
                return access::text('nocourse');
            }
            global $DB;
            $course = $DB->get_record('course', ['id' => $id], 'id,fullname');
            return $course ? format_string($course->fullname, true, ['context' => \context_course::instance($id)]) :
                access::text('courseid', $id);
        }
        return catalog::label(catalog::entry($kind, $id, false));
    }

    /**
     * Show a timestamp with Moodle's user timezone or a localized missing value.
     *
     * @param int $timestamp Time to format
     * @return string
     */
    public static function date(int $timestamp): string {
        return $timestamp ? userdate($timestamp) : access::text('notrecorded');
    }

    /**
     * Prepare common dashboard filter controls with historic catalog options.
     *
     * @param array $filter Active filters
     * @param bool $reports Whether to apply staff report scope
     * @return array
     */
    public static function filters(array $filter, bool $reports = false): array {
        $courses = [];
        foreach (queries::course_ids($reports) as $id) {
            $courses[$id] = self::bucket('course', (int)$id);
        }
        $groups = [];
        foreach (queries::FILTERS as $kind) {
            $choices = $kind === 'course' ? $courses : array_map([catalog::class, 'label'], catalog::records($kind, false));
            unset($choices[0]);
            if ($kind === 'course' && $filter['course'] && !isset($choices[$filter['course']])) {
                $choices[$filter['course']] = access::text('courseid', $filter['course']);
            }
            $groups[] = [
                'field' => $kind,
                'label' => access::text('kind_' . $kind),
                'options' => self::options($choices, $filter[$kind]),
            ];
        }
        $periods = [7, 30, 90, 365];
        $periodlabels = array_combine($periods, array_map(static fn(int $n): string => access::text('days', $n), $periods));
        $views = ['mine' => access::text('mytickets')];
        if (access::can('work') || access::can('supervise')) {
            $views['assigned'] = access::text('assignedtome');
        }
        if (access::can('supervise')) {
            $views['unassigned'] = access::text('unassigned');
        }
        return [
            'queueview' => !$reports,
            'views' => self::options($views, $filter['view'] ?? ''),
            'filters' => $groups,
            'periods' => self::options($periodlabels, $filter['days']),
            'q' => $filter['q'],
        ];
    }

    /**
     * Map option identifiers to escaped Mustache values.
     *
     * @param array $choices Value to label
     * @param string|int $selected Current value
     * @return array
     */
    public static function options(array $choices, string|int $selected): array {
        $options = [];
        foreach ($choices as $id => $label) {
            $options[] = ['id' => $id, 'label' => $label, 'selected' => (string)$id === (string)$selected];
        }
        return $options;
    }

    /**
     * Return a short ticket row for the queue.
     *
     * @param stdClass $ticket One visible ticket
     * @param array $labels Lookup of historical state and priorities
     * @return array
     */
    public static function row(stdClass $ticket, array $labels): array {
        $now = time();
        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'url' => access::url('ticket', ['id' => $ticket->id])->out(false),
            'status' => $labels['status'][$ticket->statusid] ?? '',
            'priority' => $labels['priority'][$ticket->priorityid] ?? '',
            'severity' => $labels['severity'][$ticket->severityid] ?? '',
            'modified' => self::date((int)$ticket->timemodified),
            'overdue' => $ticket->responsebreach || $ticket->resolvebreach ||
                (!$ticket->resolvedat && (($ticket->firstdue < $now && !$ticket->firstresponse) || $ticket->resolvedue < $now)),
        ];
    }
}
