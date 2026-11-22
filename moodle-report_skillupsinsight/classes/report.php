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
 * Whitelisted report configuration and consistent weighted views.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {
    /** Owned report configurations. */
    public const PRESET = 'report_skillupsinsight_preset';
    /** Owned schedules and goals. */
    public const WATCH = 'report_skillupsinsight_watch';

    /**
     * Normalize all interactive and stored filters.
     */
    public static function config(array $input): array {
        $group = $input['group'] ?? 'course';
        $group = in_array($group, ['course', 'category', 'program'], true) ? $group : 'course';
        $inputmetrics = $input['metrics'] ?? [];
        $selected = array_values(array_intersect(metrics::IDS, array_filter((array)$inputmetrics, 'is_string')));
        return [
            'category' => max(0, (int)($input['category'] ?? 0)),
            'course' => max(0, (int)($input['course'] ?? 0)),
            'period' => max(0, (int)($input['period'] ?? 0)),
            'compare' => max(0, (int)($input['compare'] ?? 0)),
            'run' => max(0, (int)($input['run'] ?? 0)),
            'comparerun' => max(0, (int)($input['comparerun'] ?? 0)),
            'group' => $group,
            'program' => \core_text::substr(trim(clean_param($input['program'] ?? '', PARAM_TEXT)), 0, 120),
            'metrics' => $selected ?: metrics::IDS,
        ];
    }

    /**
     * Evaluate with one exact authorization boundary and record coverage counts.
     */
    public static function build(array $config, string $action = 'view'): array {
        global $DB;
        $courses = scope::courses($config['category'], $config['course'], $action);
        $periods = $DB->get_records(warehouse::PERIOD, null, 'begins DESC, id DESC');
        $started = array_filter($periods, static fn($period) => $period->begins <= time());
        $current = $config['period'] ? ($periods[$config['period']] ?? null) : (reset($started) ?: (reset($periods) ?: null));
        $previous = $periods[$config['compare']] ?? null;
        if (($config['period'] && !$current) || ($config['compare'] && (!$previous || $previous->id == ($current->id ?? 0)))) {
            throw new \moodle_exception('invalidperiod', util::C);
        }
        $run = $current ? warehouse::run($current, $config['run']) : null;
        $older = $previous ? warehouse::run($previous, $config['comparerun']) : null;
        $programs = [];
        $programbycourse = scope::programs($courses);
        foreach ($courses as $id => $course) {
            $name = $programbycourse[$id] ?? '';
            if ($name !== '') {
                $programs[$name] = $name;
            }
            if ($config['program'] !== '' && $config['program'] !== $name) {
                unset($courses[$id]);
            }
        }
        natcasesort($programs);
        $data = $run ? warehouse::read($run->id, $courses) : [];
        $prior = $older ? warehouse::read($older->id, $courses) : [];
        $rows = [];
        $total = metrics::zero();
        $baseline = metrics::zero();
        foreach ($courses as $id => $course) {
            $category = \core_course_category::get($course->category);
            $program = $programbycourse[$id] ?? '';
            [$key, $label] = match ($config['group']) {
                'category' => ['cat' . $category->id, $category->get_formatted_name(['escape' => false])],
                'program' => ['pgm' . $program, $program ?: util::str('unmapped')],
                default => ['course' . $id, format_string($course->fullname, true, [
                    'escape' => false, 'context' => \context_course::instance($id),
                ])],
            };
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'label' => trim(html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    'courseid' => $config['group'] === 'course' ? $id : 0,
                    'current' => metrics::zero(), 'prior' => metrics::zero(), 'coverage' => 0,
                    'priorcoverage' => 0, 'scope' => 0,
                ];
            }
            $rows[$key]['scope']++;
            if (isset($data[$id])) {
                $rows[$key]['current'] = metrics::sum($rows[$key]['current'], $data[$id]);
                $rows[$key]['coverage']++;
                $total = metrics::sum($total, $data[$id]);
            }
            if (isset($prior[$id])) {
                $rows[$key]['prior'] = metrics::sum($rows[$key]['prior'], $prior[$id]);
                $rows[$key]['priorcoverage']++;
                $baseline = metrics::sum($baseline, $prior[$id]);
            }
        }
        uasort($rows, static fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
        return [
            'periods' => $periods, 'current' => $current, 'previous' => $previous, 'run' => $run, 'older' => $older,
            'programs' => $programs, 'rows' => array_values($rows), 'summary' => self::values($total, count($data)),
            'baseline' => self::values($baseline, count($prior)), 'coverage' => count($data), 'priorcoverage' => count($prior),
            'scope' => count($courses), 'comparison' => $previous !== null,
        ];
    }

    /**
     * Keep known zeros distinct from rows without a snapshot.
     */
    public static function values(array $raw, int $coverage): array {
        return $coverage > 0 ? metrics::values($raw) : array_fill_keys(metrics::IDS, null);
    }

    /**
     * Load a preset by its owner, with no cross-owner sharing.
     */
    public static function preset(int $id, int $userid): \stdClass {
        global $DB;
        return $DB->get_record(self::PRESET, ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
    }

    /**
     * Format a metric for display and indicate missing data explicitly.
     */
    public static function display(?float $value, string $metric, bool $difference = false): string {
        if ($value === null) {
            return '—';
        }
        $percent = metrics::percent($metric);
        $number = format_float($value, $percent ? 1 : 0);
        return $percent ? util::str($difference ? 'points' : 'percent', $number) : $number;
    }

    /**
     * Protect textual spreadsheet cells from formula interpretation.
     */
    public static function sheettext(string $text): string {
        return preg_match('/^[\s\p{Z}\p{Cc}\p{Cf}]*[=+\-@]/u', $text) ? "'" . $text : $text;
    }
}
