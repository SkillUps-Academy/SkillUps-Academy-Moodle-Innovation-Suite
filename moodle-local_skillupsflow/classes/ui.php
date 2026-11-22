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

namespace local_skillupsflow;

/**
 * Shared links and translations for Flow pages.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Component identifier.
     */
    public const C = 'local_skillupsflow';

    /**
     * Create a URL within this plugin.
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/local/skillupsflow/' . $page . '.php', $params);
    }

    /**
     * Add a visible administrative heading and tabs.
     */
    public static function navigation(string $current): string {
        global $OUTPUT;
        $tabs = [];
        $context = \context_system::instance();
        if (has_capability('local/skillupsflow:manage', $context)) {
            foreach (['index' => 'dashboard', 'history' => 'history', 'templates' => 'templates'] as $page => $label) {
                $tabs[] = ['href' => self::url($page)->out(false), 'label' => get_string($label, self::C),
                    'active' => $page === $current];
            }
        }
        if (has_capability('local/skillupsflow:approve', $context)) {
            $tabs[] = ['href' => self::url('approvals')->out(false), 'label' => get_string('approvals', self::C),
                'active' => $current === 'approvals'];
        }
        return $OUTPUT->render_from_template(self::C . '/navigation', ['tabs' => $tabs]);
    }

    /**
     * Set up a standalone system page after a login and capability check.
     */
    public static function page(string $page, string $title, array $params = []): void {
        global $PAGE, $SITE;
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_pagelayout('admin');
        $PAGE->set_title(get_string($title, self::C));
        $PAGE->set_heading($SITE->fullname);
        $PAGE->navbar->add(get_string('pluginname', self::C), self::url());
    }
}
