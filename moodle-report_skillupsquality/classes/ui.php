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

namespace report_skillupsquality;

use html_writer;
use moodle_url;

/**
 * Moodle output helpers for authorized course audits.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Resolve a report page URL.
     */
    public static function url(string $page, array $parameters = []): moodle_url {
        return new moodle_url('/report/skillupsquality/' . $page . '.php', $parameters);
    }

    /**
     * Set the native context before using the renderer.
     */
    public static function header(moodle_url $url, string $title, ?\stdClass $course = null, ?\context $context = null): void {
        global $PAGE, $OUTPUT;
        $context ??= $course ? \context_course::instance($course->id) : \context_system::instance();
        if ($course && (int)$PAGE->course->id !== (int)$course->id) {
            $PAGE->set_course($course);
        }
        $PAGE->set_context($context);
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('report');
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        echo $OUTPUT->header();
        $links = [];
        if ($course) {
            $links[] = html_writer::link(self::url('index', ['course' => $course->id]), s(scope::text('courseaudits')));
            $links[] = html_writer::link(self::url('plans', ['course' => $course->id]), s(scope::text('plans')));
        }
        if (has_capability('report/skillupsquality:batch', \context_system::instance())) {
            $links[] = html_writer::link(self::url('index'), s(scope::text('dashboard')));
        }
        if ($links) {
            echo html_writer::tag('nav', implode(' ', $links), [
                'aria-label' => scope::text('pluginname'), 'class' => 'd-flex gap-3 flex-wrap mb-4',
            ]);
        }
    }

    /**
     * Write a responsive report table with localized column headers.
     */
    public static function table(string $caption, array $columns, array $rows): void {
        $table = new \html_table();
        $table->caption = scope::text($caption);
        $table->head = array_map([scope::class, 'text'], $columns);
        $table->data = $rows;
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /**
     * Require an authenticated form submission for mutations and downloads.
     */
    public static function post(): void {
        require_sesskey();
        if (!data_submitted()) {
            throw new \moodle_exception('invalidaction', scope::COMPONENT);
        }
    }

    /**
     * Add a native POST action.
     */
    public static function button(string $page, array $parameters, string $label): void {
        global $OUTPUT;
        echo $OUTPUT->single_button(self::url($page, $parameters), scope::text($label), 'post');
    }

    /**
     * Localize published audit statuses.
     */
    public static function state(string $value): string {
        $color = match ($value) {
            'done', 'absent' => 'text-bg-success',
            'failed', 'denied', 'present', 'overdue' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
        return html_writer::span(s(scope::text('state_' . $value)), 'badge ' . $color);
    }

    /**
     * Return a display label for one versioned finding.
     */
    public static function rule_name(string $code): string {
        return get_string_manager()->string_exists('rule_' . $code, scope::COMPONENT)
            ? scope::text('rule_' . $code)
            : $code;
    }

    /**
     * Format structured evidence with locale-aware dates and numbers.
     */
    public static function evidence(\stdClass $finding): array {
        $rows = [];
        foreach (store::decode($finding->evidence) as $key => $value) {
            $datefields = ['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'timeopen', 'timeclose', 'startdate', 'enddate'];
            $display = in_array($key, $datefields, true)
                ? userdate((int)$value)
                : (is_float($value) ? format_float($value, 2) : (string)$value);
            $rows[scope::text('evidence_' . $key)] = $display;
        }
        return $rows;
    }

    /**
     * Show target metadata and a valid native activity or gradebook link.
     */
    public static function target(\stdClass $course, \stdClass $item): string {
        if ($item->targettype === 'module') {
            $modules = get_fast_modinfo($course, -1)->get_cms();
            $cm = $modules[$item->targetid] ?? null;
            if ($cm && $cm->url) {
                return html_writer::link($cm->url, format_string($cm->name, true, ['context' => $cm->context]));
            }
        }
        if ($item->targettype === 'gradeitem') {
            $url = new moodle_url('/grade/edit/tree/index.php', ['id' => $course->id]);
            return html_writer::link($url, s(scope::text('target_gradeitem', $item->targetid)));
        }
        return s(scope::text('target_' . $item->targettype, $item->targetid));
    }
}
