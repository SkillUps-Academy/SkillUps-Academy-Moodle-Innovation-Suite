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

namespace report_skillupsmonitor;

/**
 * Moodle-native visibility, page framing and display helpers.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * System authorization for every dashboard route.
     */
    public static function access(): \context_system {
        $context = \context_system::instance();
        require_capability(util::CAP . ':view', $context);
        return $context;
    }

    /**
     * Attach page context and a consistent navigation bar.
     */
    public static function header(string $page, string $title, array $params = []): void {
        global $PAGE, $OUTPUT;
        $context = \context_system::instance();
        $PAGE->set_context($context);
        $PAGE->set_url(util::url($page, $params));
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        $PAGE->set_pagelayout('report');
        echo $OUTPUT->header();
        $links = [];
        $pages = ['index' => 'dashboard', 'history' => 'history', 'incidents' => 'incidents', 'diagnostics' => 'diagnostics'];
        foreach ($pages as $id => $label) {
            $links[] = \html_writer::link(
                util::url($id),
                s(util::str($label)),
                $page === $id ? ['class' => 'fw-bold me-3', 'aria-current' => 'page'] : ['class' => 'me-3']
            );
        }
        echo \html_writer::tag('nav', implode(' ', $links), ['aria-label' => util::str('pluginname'), 'class' => 'mb-4']);
    }

    /**
     * Accessible table with Moodle table styles.
     */
    public static function table(string $caption, array $columns, array $rows): string {
        $table = new \html_table();
        $table->caption = util::str($caption);
        $table->head = array_map([util::class, 'str'], $columns);
        $table->data = $rows;
        return \html_writer::div(\html_writer::table($table), 'table-responsive');
    }

    /**
     * Open a safe report form and pass known scalar fields.
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
     * Labeled select with safe native HTML escaping.
     */
    public static function select(string $name, array $options, mixed $value): void {
        echo \html_writer::label(util::str($name), 'monitor-' . $name, true, ['class' => 'form-label']);
        echo \html_writer::select(array_map('s', $options), $name, $value, false, [
            'id' => 'monitor-' . $name, 'class' => 'form-select mb-2',
        ]);
    }

    /**
     * Named submission button.
     */
    public static function button(string $action): void {
        echo \html_writer::tag('button', s(util::str($action)), [
            'type' => 'submit', 'name' => 'action', 'value' => $action, 'class' => 'btn btn-primary',
        ]);
    }

    /**
     * Confirm a protected mutation was submitted via POST.
     */
    public static function post(): void {
        require_sesskey();
        if (!data_submitted()) {
            throw new \moodle_exception('badrequest', util::C);
        }
    }

    /**
     * Map finite internal states to a clear, colored Moodle badge.
     */
    public static function state(string $status): string {
        $color = match ($status) {
            'ok', 'up', 'recovered' => 'success',
            'warning', 'pending', 'open' => 'warning',
            'error', 'down' => 'danger',
            default => 'secondary',
        };
        return \html_writer::span(s(util::str('status_' . $status)), 'badge bg-' . $color . ' ' .
            ($color === 'warning' ? 'text-dark' : 'text-white'));
    }

    /**
     * Display a monotonic reading in milliseconds, or unavailable.
     */
    public static function latency(?int $us): string {
        return $us === null || $us < 0 ? '—' : util::str('milliseconds', format_float($us / 1000, 2));
    }

    /**
     * Display count-floor when a native query reached the configured bound.
     */
    public static function count_floor(?int $count, bool $capped = false): string {
        return $count === null ? '—' : ($capped ? util::str('countfloor', $count) : (string)$count);
    }
}
