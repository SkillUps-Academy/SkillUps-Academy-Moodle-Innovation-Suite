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

namespace local_skillupsgamification;

use moodle_url;
use stdClass;

/**
 * Localized presentation helpers and course navigation.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Initialize a real Moodle course page.
     *
     * @param stdClass $course Course record
     * @param moodle_url $url URL
     * @param string $heading Visible heading
     */
    public static function page(stdClass $course, moodle_url $url, string $heading): void {
        global $PAGE;
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title($heading);
        $PAGE->set_heading($course->fullname);
        $PAGE->navbar->add(access::text('pluginname'), access::url('index', (int)$course->id));
    }

    /**
     * Convert stable codes to editable localized labels.
     *
     * @param string $family Lang-key prefix
     * @param array $values Codes
     * @return array
     */
    public static function options(string $family, array $values): array {
        return array_combine($values, array_map(static fn(string $name): string => access::text($family . $name), $values));
    }

    /**
     * Link to each available portal section.
     *
     * @param int $courseid Course scope
     * @return array
     */
    public static function tabs(int $courseid): array {
        $context = \context_course::instance($courseid);
        $tabs = [];
        foreach (['progress', 'history', 'rankings'] as $section) {
            $tabs[] = [
                'label' => access::text($section),
                'url' => access::url('index', $courseid, ['tab' => $section])->out(false),
            ];
        }
        if (has_capability(access::CAP . 'manage', $context)) {
            $tabs[] = ['label' => access::text('manage'), 'url' => access::url('manage', $courseid)->out(false)];
        }
        return $tabs;
    }

    /**
     * Format learner goals from verified records.
     *
     * @param stdClass $goal Goal definition
     * @param stdClass|null $grant Stored receipt
     * @param int $userid Participant
     * @param int $teamid Current team
     * @param array $totals Personal points
     * @param bool $eligible Current eligibility
     * @return array
     */
    public static function goal(
        stdClass $goal,
        ?stdClass $grant,
        int $userid,
        int $teamid,
        array $totals,
        bool $eligible
    ): array {
        global $DB;
        $reward = $goal->kind === 'reward';
        $now = time();
        if ($reward) {
            $available = !$goal->stock || $DB->count_records(store::GRANT, ['goalid' => $goal->id]) < $goal->stock;
            $detail = access::text('costvalue', $goal->cost);
            $ready = $available && $totals['balance'] >= $goal->cost;
        } else {
            [$score, $own, $members] = $eligible ? goals::progress($goal, $userid, $teamid) : [0, 0, 0];
            $detail = access::text('progressvalue', (object)[
                'current' => $score, 'target' => $goal->target, 'metric' => access::text('metric_' . $goal->metric),
            ]);
            $ready = $score >= $goal->target && ($goal->scope !== 'team' || ($own && $members >= 2));
        }
        return [
            'id' => $goal->id, 'name' => $goal->name, 'description' => $goal->description,
            'kind' => access::text('goal_' . $goal->kind), 'teamscope' => $goal->scope === 'team',
            'symbol' => match ($goal->icon) {
                'flag' => '⚑', 'medal' => '🏅', 'target' => '◎', 'gift' => '◆', default => '★',
            },
            'detail' => $detail, 'claimed' => $grant ? access::text('state_' . $grant->state) : '',
            'window' => self::window($goal), 'ready' => !$grant && $eligible && $ready && goals::open($goal, $now),
            'button' => access::text($reward ? 'redeem' : 'claim'),
        ];
    }

    /**
     * Describe optional active dates.
     *
     * @param stdClass $record Date-bound record
     * @return string
     */
    public static function window(stdClass $record): string {
        return $record->starts || $record->ends ? access::text('windowrange', (object)[
            'start' => $record->starts ? userdate($record->starts) : access::text('unlimited'),
            'end' => $record->ends ? userdate($record->ends) : access::text('unlimited'),
        ]) : '';
    }
}
