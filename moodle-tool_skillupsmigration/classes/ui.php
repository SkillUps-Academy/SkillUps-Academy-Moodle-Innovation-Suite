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

namespace tool_skillupsmigration;

/**
 * Present scoped administrative controls using native Moodle output.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Translate a component phrase.
     *
     * @param string $key Language key
     * @param mixed $data Interpolation
     * @return string
     */
    public static function t(string $key, mixed $data = null): string {
        return get_string($key, support::C, $data);
    }

    /**
     * Build a route under the actual Moodle administration directory.
     *
     * @param string $name Entry point
     * @param array $params Route parameters
     * @return \moodle_url
     */
    public static function url(string $name = 'index', array $params = []): \moodle_url {
        global $CFG;
        // Moodle 5.0 supports a custom administration path.
        // phpcs:ignore moodle.Deprecations.DeprecatedCFGProperties.DeprecatedCFGProperty
        return new \moodle_url('/' . $CFG->admin . '/tool/skillupsmigration/' . $name . '.php', $params);
    }

    /**
     * Prepare a native report page.
     *
     * @param string $page Entry point
     * @param array $params Route parameters
     */
    public static function page(string $page, array $params = []): void {
        global $PAGE, $SITE;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('report');
        $PAGE->set_show_course_index(false);
        $PAGE->set_title(self::t('pluginname'));
        $PAGE->set_heading(format_string($SITE->fullname));
        $PAGE->set_cacheable(false);
    }

    /**
     * Return categories where both local destination permissions are present.
     *
     * @return array IDs and labels
     */
    public static function categories(): array {
        $result = [];
        foreach (\core_course_category::make_categories_list('moodle/course:create') as $id => $label) {
            $context = \context_coursecat::instance($id);
            if (has_capability('moodle/restore:restorecourse', $context)) {
                $result[$id] = $label;
            }
        }
        return $result;
    }

    /**
     * Render an escaped link.
     *
     * @param \moodle_url $url Route
     * @param string $label Plain text label
     * @param string $css Bootstrap classes
     * @return string
     */
    public static function link(\moodle_url $url, string $label, string $css = ''): string {
        return \html_writer::link($url, s($label), ['class' => $css]);
    }

    /**
     * Render a responsive table.
     *
     * @param array $keys Column translation keys
     * @param array $rows Already escaped cells
     * @return string
     */
    public static function table(array $keys, array $rows): string {
        if (!$rows) {
            return \html_writer::tag('p', self::t('empty'));
        }
        $table = new \html_table();
        $table->head = array_map([self::class, 't'], $keys);
        $table->data = $rows;
        $table->attributes['class'] = 'generaltable';
        return \html_writer::div(\html_writer::table($table), 'table-responsive');
    }

    /**
     * Parse an operator-supplied course list or mapping list without evaluating data.
     *
     * @param string $value Plain text lines
     * @param int $cols Exact columns per line
     * @return array Trimmed values
     */
    public static function lines(string $value, int $cols): array {
        $result = [];
        foreach (preg_split('/\R/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $line = array_map('trim', explode(',', $line));
            if (count($line) !== $cols || in_array('', $line, true)) {
                support::fail('selection');
            }
            $result[] = $line;
        }
        return $result;
    }
}
