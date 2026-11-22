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

namespace local_skillupsaigrading;

use local_skillupsaigrading\activity\assign;

/**
 * Common view helpers for Moodle-native pages.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Local plugin string.
     */
    public static function str(string $key, mixed $data = null): string {
        return get_string($key, access::C, $data);
    }

    /**
     * Local plugin URL.
     */
    public static function url(string $page, array $params): \moodle_url {
        return new \moodle_url('/local/skillupsaigrading/' . $page . '.php', $params);
    }

    /**
     * Render a Moodle page and heading.
     */
    public static function header(\moodle_url $url, \context $context, \stdClass $course, string $title): void {
        global $PAGE, $OUTPUT;
        $PAGE->set_url($url);
        $PAGE->set_context($context);
        $PAGE->set_course($course);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title($title);
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header(), $OUTPUT->heading($title);
    }

    /**
     * Link to a local plugin page. The label is escaped as plaintext.
     */
    public static function link(string $page, array $params, string $label): string {
        return \html_writer::link(self::url($page, $params), s($label));
    }

    /**
     * Start a CSRF-protected local form.
     */
    public static function form(string $page, array $values): void {
        echo \html_writer::start_tag('form', ['method' => 'post', 'action' => self::url($page, [])->out(false), 'class' => 'mb-4']);
        foreach (['sesskey' => sesskey()] + $values as $name => $value) {
            echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
    }

    /**
     * Plain text entry with an accessible visible label.
     */
    public static function textarea(string $key, string $value, int $limit): void {
        echo \html_writer::label(self::str($key), $key, true, ['class' => 'form-label']);
        echo \html_writer::tag('textarea', s($value), [
            'id' => $key, 'name' => $key, 'maxlength' => $limit, 'rows' => 7, 'class' => 'form-control mb-3',
        ]);
    }

    /**
     * Submit a named operation.
     */
    public static function button(string $action, bool $primary = false): void {
        echo \html_writer::tag('button', self::str($action), [
            'name' => 'action', 'value' => $action, 'type' => 'submit',
            'class' => 'btn me-2 mt-2 ' . ($primary ? 'btn-primary' : 'btn-secondary'),
        ]);
    }

    /**
     * Show a rubric using escaped labels and exact Moodle levels.
     */
    public static function rubric(array $rubric, array $results = []): void {
        global $OUTPUT;
        $results = array_column($results, null, 'id');
        echo $OUTPUT->heading(self::str('rubric'), 3);
        foreach ($rubric['criteria'] as $number => $criterion) {
            $row = $results[$criterion['id']] ?? null;
            echo \html_writer::start_tag('section', ['class' => 'card mb-3 p-3']);
            echo $OUTPUT->heading(s(($number + 1) . '. ' . $criterion['description']), 4);
            $levels = [];
            foreach ($criterion['levels'] as $level) {
                $label = s($level['description'] . ' (' . format_float($level['score'], 2, true, true) . ')');
                $levels[] = ($row['level'] ?? null) === $level['id'] ? \html_writer::tag('strong', $label) : $label;
            }
            echo \html_writer::alist($levels);
            if ($row !== null) {
                $score = $row['uncertain'] ? self::str('uncertain') : self::str('points', (string)$row['score']);
                echo \html_writer::tag('p', s($score), ['class' => 'fw-bold']);
                foreach ($row['quotes'] as $quote) {
                    echo \html_writer::tag('blockquote', s($quote), ['class' => 'border-start ps-3']);
                }
                if ($row['rationale'] !== '') {
                    echo \html_writer::tag('p', s(self::str('rationale') . ' — ' . $row['rationale']));
                }
                if ($row['gap'] !== '') {
                    echo \html_writer::tag('p', s(self::str('gap') . ' — ' . $row['gap']));
                }
            }
            echo \html_writer::end_tag('section');
        }
    }

    /**
     * Summarize a limited chronological sample of visible and authorized reviews.
     */
    public static function statistics(assign $activity, ?array $participants = null): array {
        global $DB;
        $participants ??= $activity->participants();
        $rows = [];
        foreach (array_chunk(array_keys($participants), 200) as $ids) {
            [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'subject');
            $rows += $DB->get_records_select(
                workflow::JOB,
                "cmid = :cmid AND studentid $sql",
                $params + ['cmid' => $activity->cm->id],
                'id DESC',
                'id, studentid, provider, state, reviewstate, suggested, human',
                0,
                500
            );
            krsort($rows, SORT_NUMERIC);
            $rows = array_slice($rows, 0, 500, true);
        }
        $data = ['total' => 0, 'ai' => 0, 'approved' => 0, 'rejected' => 0, 'failed' => 0, 'compared' => 0, 'error' => 0.0];
        foreach ($rows as $row) {
            $data['total']++;
            $data['ai'] += (int)($row->provider !== 'local');
            $data['approved'] += (int)($row->reviewstate === 'approved');
            $data['rejected'] += (int)($row->reviewstate === 'rejected');
            $data['failed'] += (int)($row->state === 'failed');
            if ($row->human !== null && $row->suggested !== null) {
                $data['compared']++;
                $data['error'] += abs($row->human - $row->suggested);
            }
        }
        $data['mae'] = $data['compared'] ? format_float($data['error'] / $data['compared'], 2) : '—';
        return [(object)$data, array_slice($rows, 0, 25)];
    }
}
