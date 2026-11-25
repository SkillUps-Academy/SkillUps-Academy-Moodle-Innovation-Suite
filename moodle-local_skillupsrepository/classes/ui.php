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

namespace local_skillupsrepository;

/**
 * Present native Moodle pages and escaped library links.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Localize a plugin phrase.
     *
     * @param string $key Phrase key
     * @param mixed $data Optional interpolation
     * @return string
     */
    public static function t(string $key, mixed $data = null): string {
        return get_string($key, access::COMPONENT, $data);
    }

    /**
     * Construct a route inside the plugin.
     *
     * @param string $page Script name
     * @param array $params URL parameters
     * @return \moodle_url
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupsrepository/' . $page . '.php', $params);
    }

    /**
     * Build a table of untrusted text with a responsive viewport.
     *
     * @param array $head Localized column labels
     * @param array $rows Cells already escaped
     * @return string
     */
    public static function table(array $head, array $rows): string {
        if (!$rows) {
            return \html_writer::tag('p', self::t('empty'));
        }
        $table = new \html_table();
        $table->head = array_map('s', $head);
        $table->data = $rows;
        $table->attributes['class'] = 'generaltable';
        return \html_writer::div(\html_writer::table($table), 'table-responsive');
    }

    /**
     * Set the native output context and page title.
     *
     * @param string $page Script name
     * @param array $params Canonical route parameters
     * @param \context|null $context Optional native context
     */
    public static function page(string $page, array $params = [], ?\context $context = null): void {
        global $PAGE, $SITE;
        $PAGE->set_context($context ?? \context_system::instance());
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('report');
        $PAGE->set_title(self::t('pluginname'));
        $PAGE->set_heading(format_string($SITE->fullname));
        $PAGE->set_show_course_index(false);
        $PAGE->set_cacheable(false);
    }

    /**
     * Calculate native binary URL for an authorized interface.
     *
     * @param \stdClass $asset Resource
     * @param \stdClass $version Revision
     * @param bool $download Force attachment
     * @return \moodle_url
     */
    public static function fileurl(\stdClass $asset, \stdClass $version, bool $download): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            \context_coursecat::instance($asset->categoryid)->id,
            access::COMPONENT,
            files::AREA,
            $version->id,
            '/',
            $version->filename,
            $download
        );
    }

    /**
     * Map keys to localized phrases.
     *
     * @param array $keys Language keys
     * @param string $prefix Optional common prefix
     * @return array
     */
    public static function options(array $keys, string $prefix = ''): array {
        $labels = [];
        foreach ($keys as $key) {
            $labels[$key] = self::t($prefix . $key);
        }
        return $labels;
    }
}
