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

namespace local_skillupssuccess;

/**
 * Localized Moodle presentation and form controls.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Configure the page and print the native layout.
     */
    public static function header(\context $context, \moodle_url $url, string $title): void {
        global $PAGE, $OUTPUT;
        $PAGE->set_context($context);
        $PAGE->set_url($url);
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        $PAGE->set_pagelayout('report');
        echo $OUTPUT->header();
    }

    /**
     * Escaped inline link.
     */
    public static function link(string $page, array $args, string $name): string {
        return \html_writer::link(util::url($page, $args), s($name), ['class' => 'me-3']);
    }

    /**
     * Begin a form using the Moodle session token for POSTs.
     */
    public static function form(string $page, array $fields, bool $post = true): void {
        echo \html_writer::start_tag('form', [
            'method' => $post ? 'post' : 'get', 'action' => util::url($page)->out(false), 'class' => 'mb-3',
        ]);
        if ($post) {
            $fields += ['sesskey' => sesskey()];
        }
        foreach ($fields as $name => $value) {
            echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
    }

    /**
     * Labeled and escaped form select.
     */
    public static function select(string $key, array $choices, mixed $value, string $fieldid = ''): void {
        $fieldid = $fieldid ?: 'success-' . $key;
        echo \html_writer::label(util::str($key), $fieldid, true, ['class' => 'form-label']);
        echo \html_writer::select(array_map('s', $choices), $key, $value, false, ['id' => $fieldid, 'class' => 'form-select mb-3']);
    }

    /**
     * Accessible localized submit button.
     */
    public static function submit(string $label, ?string $value = null): void {
        echo \html_writer::tag('button', s(util::str($label)), [
            'type' => 'submit', 'name' => 'action', 'value' => $value ?? $label, 'class' => 'btn btn-primary me-2',
        ]);
    }

    /**
     * Confirm a protected request is an actual POST.
     */
    public static function require_post(): void {
        require_sesskey();
        if (!data_submitted()) {
            util::fail('invalidrequest');
        }
    }

    /**
     * Build an accessible Moodle table.
     */
    public static function table(string $caption, array $labels, array $rows): string {
        $table = new \html_table();
        $table->caption = util::str($caption);
        $table->head = array_map([util::class, 'str'], $labels);
        $table->data = $rows;
        return \html_writer::div(\html_writer::table($table), 'table-responsive');
    }
}
