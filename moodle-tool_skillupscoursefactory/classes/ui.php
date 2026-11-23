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

use html_writer;
use moodle_url;

/**
 * Prepare authorized Moodle pages and accessible output.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Resolve a page relative to the installation's admin path.
     */
    public static function url(string $page, array $parameters = []): moodle_url {
        global $CFG;
        return new moodle_url('/' . $CFG->admin . '/tool/skillupscoursefactory/' . $page . '.php', $parameters);
    }

    /**
     * Set the page context before preparing Moodle forms.
     */
    public static function setup(string $page, string $title, array $params = []): void {
        global $PAGE, $USER;
        require_capability('tool/skillupscoursefactory:operate', \context_system::instance());
        if (!support::can_operate((int)$USER->id)) {
            throw new \moodle_exception('denied', support::C);
        }
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('admin');
        $PAGE->set_title(support::text($title));
        $PAGE->set_heading(support::text($title));
    }

    /**
     * Print the native header and contextual links.
     */
    public static function header(): void {
        global $OUTPUT;
        echo $OUTPUT->header();
        $pages = ['index' => 'pluginname', 'create' => 'single', 'import' => 'import'];
        if (has_capability('tool/skillupscoursefactory:manage', \context_system::instance())) {
            $pages['templates'] = 'templates';
        }
        $links = [];
        foreach ($pages as $page => $name) {
            $links[] = html_writer::link(self::url($page), s(support::text($name)));
        }
        echo html_writer::tag('nav', implode(' ', $links), [
            'class' => 'd-flex gap-3 flex-wrap mb-4', 'aria-label' => support::text('pluginname'),
        ]);
    }

    /**
     * Render responsive Moodle tables.
     */
    public static function table(string $caption, array $columns, array $rows): void {
        $table = new \html_table();
        $table->caption = support::text($caption);
        $table->head = array_map([support::class, 'text'], $columns);
        $table->data = $rows;
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /**
     * Format a job or row state without depending on color.
     */
    public static function state(string $value): string {
        $color = match ($value) {
            'done' => 'text-bg-success',
            'failed', 'attention', 'invalid' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
        return html_writer::span(s(support::text('state_' . $value)), 'badge ' . $color);
    }

    /**
     * Return choices only for courses the operator may back up.
     */
    public static function sources(): array {
        global $USER;
        $choices = [0 => support::text('emptycourse')];
        $courses = get_user_capability_course('moodle/backup:backupcourse', null, true, 'fullname', 'fullname', 101);
        foreach ($courses ?: [] as $course) {
            if (support::source((int)$course->id, (int)$USER->id)) {
                $choices[$course->id] = format_string($course->fullname);
            }
        }
        return $choices;
    }

    /**
     * Return the allowed institutional templates.
     */
    public static function templates(): array {
        global $DB, $USER;
        $choices = [0 => support::text('notemplate')];
        foreach ($DB->get_records(jobs::TPL, [], 'name', '*', 0, 100) as $row) {
            if (support::source((int)$row->sourceid, (int)$USER->id)) {
                $choices[$row->id] = $row->name;
            }
        }
        return $choices;
    }

    /**
     * Read effective IANA timezone from Moodle.
     */
    public static function timezone(): string {
        return \core_date::get_user_timezone();
    }

    /**
     * Neutralize spreadsheet cell interpretation on any authored value.
     */
    public static function csv_safe(mixed $value): mixed {
        return is_string($value) && preg_match('/^\s*[=+@-]|^[\x00-\x1f]/u', $value) ? "'" . $value : $value;
    }
}
