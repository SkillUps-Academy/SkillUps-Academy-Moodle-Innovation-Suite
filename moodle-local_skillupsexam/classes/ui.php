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

namespace local_skillupsexam;

/**
 * Render localized and escaped exam views.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Render a link.
     *
     * @param \moodle_url $url Destination
     * @param string $label Plain text
     * @param string $class Link classes
     * @return string HTML
     */
    public static function link(\moodle_url $url, string $label, string $class = ''): string {
        return \html_writer::link($url, s($label), ['class' => $class]);
    }

    /**
     * Render an escaped table with the given HTML cells.
     *
     * @param string[] $head Plain column labels
     * @param array $rows Escaped cells and built links
     * @return string
     */
    public static function table(array $head, array $rows): string {
        if (!$rows) {
            return \html_writer::tag('p', scope::text('empty'), ['class' => 'text-muted']);
        }
        $table = new \html_table();
        $table->head = array_map('s', $head);
        $table->data = $rows;
        $table->attributes['class'] = 'generaltable';
        return \html_writer::div(\html_writer::table($table), 'table-responsive');
    }

    /**
     * Return the projected application state.
     *
     * @param \stdClass $exam Convocation
     * @return string Locale key
     */
    public static function state(\stdClass $exam): string {
        if ($exam->status !== 'scheduled') {
            return $exam->status;
        }
        $now = time();
        return $exam->starts > $now ? 'scheduled' : ($exam->ends > $now ? 'running' : 'finished');
    }

    /**
     * Produce a CSV-safe string.
     *
     * @param string $value Plain value
     * @return string
     */
    public static function cell(string $value): string {
        return preg_match('/^[\x00-\x20]*[=+@-]|^[\t\r\n]/u', $value) ? "'" . $value : $value;
    }

    /**
     * Render navigable date, text and select controls.
     *
     * @param \moodle_url $url Route
     * @param array $fields Field definitions
     * @return string
     */
    public static function filters(\moodle_url $url, array $fields): string {
        $out = '';
        foreach ($fields as $name => [$value, $options]) {
            $id = 'exam-filter-' . $name;
            $field = $options === null
                ? \html_writer::empty_tag('input', [
                    'id' => $id, 'name' => $name, 'value' => $value, 'type' => 'month', 'class' => 'form-control',
                ])
                : \html_writer::select($options, $name, $value, false, ['id' => $id, 'class' => 'form-select']);
            $out .= \html_writer::div(\html_writer::label(scope::text($name), $id, false) . $field);
        }
        $out .= \html_writer::tag('button', scope::text('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
        $attributes = ['action' => $url->out_omit_querystring(), 'method' => 'get', 'class' => 'exam-filters'];
        return \html_writer::tag('form', $out, $attributes);
    }
}
