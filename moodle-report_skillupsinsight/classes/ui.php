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

namespace report_skillupsinsight;

/**
 * Moodle page setup, accessible controls and shared table formatting.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Read the request through Moodle's parameter API.
     */
    public static function filters(): array {
        return report::config([
            'category' => optional_param('category', 0, PARAM_INT), 'course' => optional_param('course', 0, PARAM_INT),
            'period' => optional_param('period', 0, PARAM_INT), 'compare' => optional_param('compare', 0, PARAM_INT),
            'run' => optional_param('run', 0, PARAM_INT), 'group' => optional_param('group', 'course', PARAM_ALPHA),
            'comparerun' => optional_param('comparerun', 0, PARAM_INT),
            'program' => optional_param('program', '', PARAM_TEXT), 'metrics' => optional_param_array('metrics', [], PARAM_ALPHA),
        ]);
    }

    /**
     * Bind navigation to the requested Moodle context.
     */
    public static function header(string $title, \context $context, \moodle_url $url): void {
        global $PAGE, $OUTPUT;
        $PAGE->set_url($url);
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('report');
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        echo $OUTPUT->header();
    }

    /**
     * Stable URL arguments without unchecked JSON or user-provided SQL.
     */
    public static function params(array $config): array {
        $params = $config;
        if ($config['metrics'] === metrics::IDS) {
            unset($params['metrics']);
        } else {
            unset($params['metrics']);
            foreach ($config['metrics'] as $index => $metric) {
                $params['metrics[' . $index . ']'] = $metric;
            }
        }
        return array_filter($params, fn($value) => $value !== 0 && $value !== '');
    }

    /**
     * Safely open a POST form with a Moodle session token.
     */
    public static function form(string $page, array $fields = []): void {
        echo \html_writer::start_tag('form', [
            'method' => 'post', 'action' => util::url($page)->out(false), 'class' => 'mb-3',
        ]);
        foreach (['sesskey' => sesskey()] + $fields as $name => $value) {
            self::hidden($name, $value);
        }
    }

    /**
     * Render a hidden scalar field.
     */
    public static function hidden(string $name, mixed $value): void {
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }

    /**
     * Render escaped form choices.
     */
    public static function select(string $name, array $choices, mixed $selected, ?string $label = null): void {
        $label = $label ?? util::str($name);
        echo \html_writer::label($label, 'insight-' . $name, true, ['class' => 'form-label']);
        echo \html_writer::select(
            array_map('s', $choices),
            $name,
            $selected,
            false,
            ['id' => 'insight-' . $name, 'class' => 'form-select mb-3']
        );
    }

    /**
     * Render a required labeled input.
     */
    public static function input(string $name, string $type, array $attributes = []): void {
        echo \html_writer::label(util::str($name), 'insight-' . $name, true, ['class' => 'form-label']);
        echo \html_writer::empty_tag('input', $attributes + [
            'id' => 'insight-' . $name, 'name' => $name, 'type' => $type, 'class' => 'form-control mb-3', 'required' => 'required',
        ]);
    }

    /**
     * Render a submit action.
     */
    public static function button(string $action, bool $primary = true): void {
        echo \html_writer::tag('button', s(util::str($action)), [
            'type' => 'submit', 'name' => 'action', 'value' => $action,
            'class' => $primary ? 'btn btn-primary' : 'btn btn-secondary',
        ]);
    }

    /**
     * Show available dimensions without leaking names outside the user's context.
     */
    public static function filter(array $config, array $view): void {
        echo \html_writer::start_tag('form', [
            'method' => 'get', 'action' => util::url('index')->out(false), 'class' => 'card p-3 mb-4',
        ]);
        echo \html_writer::start_tag('div', ['class' => 'row']);
        if ($config['course']) {
            self::hidden('course', $config['course']);
        } else {
            $categories = has_capability(util::CAP . ':view', \context_system::instance()) ? [0 => util::str('institution')] : [];
            foreach (\core_course_category::get_all() as $category) {
                if (has_capability(util::CAP . ':view', $category->get_context())) {
                    $categories[$category->id] = $category->get_formatted_name(['escape' => false]);
                }
            }
            echo \html_writer::start_tag('div', ['class' => 'col-sm-6 col-xl-4']);
            self::select('category', $categories, $config['category']);
            echo \html_writer::end_tag('div');
        }
        $periods = array_map(static fn($period) => $period->name, $view['periods']);
        $groups = ['course', 'category', 'program'];
        $controls = [
            'period' => [$periods ?: [0 => util::str('noperiods')], $view['current']->id ?? 0],
            'compare' => [[0 => util::str('nocmp')] + $periods, $config['compare']],
            'group' => [array_combine($groups, array_map([util::class, 'str'], $groups)), $config['group']],
            'program' => [['' => util::str('allprograms')] + $view['programs'], $config['program']],
        ];
        foreach ($controls as $name => [$choices, $selected]) {
            echo \html_writer::start_tag('div', ['class' => 'col-sm-6 col-xl-4']);
            self::select($name, $choices, $selected);
            echo \html_writer::end_tag('div');
        }
        echo \html_writer::end_tag('div');
        foreach ($config['metrics'] as $metric) {
            self::hidden('metrics[]', $metric);
        }
        self::button('applyfilters');
        echo \html_writer::end_tag('form');
    }

    /**
     * Render analytics rows with at most 25 groups per page.
     */
    public static function table(array $rows, array $metrics, bool $comparison, int $page, \moodle_url $url): void {
        global $OUTPUT;
        $table = new \html_table();
        $table->caption = util::str('breakdown');
        $table->head = [util::str('dimension'), util::str('coverage')];
        foreach ($metrics as $metric) {
            $table->head[] = util::str('metric_' . $metric);
        }
        $table->data = [];
        foreach (array_slice($rows, $page * 25, 25) as $row) {
            $current = report::values($row['current'], $row['coverage']);
            $prior = report::values($row['prior'], $row['priorcoverage']);
            $name = s($row['label']);
            if ($row['courseid']) {
                $detail = new \moodle_url($url, ['course' => $row['courseid']]);
                $detail->remove_params('category', 'page');
                $name = \html_writer::link($detail, $name);
            }
            $cells = [$name, s($row['coverage'] . '/' . $row['scope'])];
            foreach ($metrics as $metric) {
                $value = $current[$metric];
                $cell = s(report::display($value, $metric));
                if ($comparison) {
                    $delta = $value !== null && $prior[$metric] !== null ? $value - $prior[$metric] : null;
                    $cell .= \html_writer::tag('small', s(util::str('tablecompare', (object)[
                        'prior' => report::display($prior[$metric], $metric),
                        'delta' => report::display($delta, $metric, true),
                    ])), ['class' => 'd-block text-muted']);
                }
                $cells[] = $cell;
            }
            $table->data[] = new \html_table_row($cells);
        }
        if ($table->data) {
            echo \html_writer::div(\html_writer::table($table), 'table-responsive');
            echo $OUTPUT->paging_bar(count($rows), $page, 25, $url);
        } else {
            echo $OUTPUT->notification(util::str('nocourses'), 'info');
        }
    }

    /**
     * Scope navigation calls can be used from any dashboard page.
     */
    public static function link(string $page, array $params, string $label): string {
        return \html_writer::link(util::url($page, $params), s($label), ['class' => 'me-3']);
    }
}
