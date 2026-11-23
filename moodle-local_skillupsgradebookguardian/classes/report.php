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

namespace local_skillupsgradebookguardian;

use stdClass;

/**
 * Present course diagnostics and compare snapshots by stable rule and entity identity.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {
    /**
     * Compare two diagnoses, preserving both sides for changed evidence.
     *
     * @param array $current Current findings
     * @param array $baseline Baseline findings
     * @return array
     */
    public static function compare(array $current, array $baseline): array {
        $prior = [];
        foreach ($baseline as $finding) {
            $prior[self::key($finding)] = $finding;
        }
        $result = [];
        foreach ($current as $finding) {
            $key = self::key($finding);
            $old = $prior[$key] ?? null;
            $change = $old === null ? 'added' : ($old['evidence'] === $finding['evidence'] ? 'unchanged' : 'changed');
            $result[] = $finding + ['change' => $change, 'previous' => $old['evidence'] ?? []];
            unset($prior[$key]);
        }
        foreach ($prior as $finding) {
            $result[] = $finding + ['change' => 'resolved', 'previous' => []];
        }
        return $result;
    }

    /**
     * Build evidence identity independently of language.
     *
     * @param array $finding Finding
     * @return string
     */
    private static function key(array $finding): string {
        return $finding['rule'] . ':' . $finding['target'] . ':' . $finding['id'];
    }

    /**
     * Localize the structural evidence for the UI and exports.
     *
     * @param array $evidence Finding evidence
     * @return string
     */
    public static function evidence(array $evidence): string {
        $lines = [];
        foreach ($evidence as $name => $value) {
            if ($name === 'hidden') {
                $value = self::visibility((int)$value);
            }
            $lines[] = access::text('field_' . $name) . ' ' . (is_array($value) ? implode(', ', $value) : $value);
        }
        return $lines ? implode(' · ', $lines) : access::text('noevidence');
    }

    /**
     * Describe the raw grade item visibility field.
     *
     * @param int $hidden Grade item visibility
     * @return string
     */
    public static function visibility(int $hidden): string {
        return match ($hidden) {
            0 => access::text('visibility_visible'),
            1 => access::text('visibility_hidden'),
            default => access::text('visibility_until', userdate($hidden)),
        };
    }

    /**
     * Present a safe finding row and its course-scoped preview coordinates.
     *
     * @param array $finding Finding
     * @param bool $maypreview Whether preview is enabled
     * @return array
     */
    public static function finding(array $finding, bool $maypreview): array {
        [$severity, $treatment] = rules::CATALOGUE[$finding['rule']];
        return [
            'title' => access::text('rule_' . $finding['rule']),
            'reference' => access::text('target_' . $finding['target'], $finding['id']),
            'evidence' => self::evidence($finding['evidence']),
            'previous' => !empty($finding['previous']) && ($finding['change'] ?? '') === 'changed'
                ? self::evidence($finding['previous']) : '',
            'severity' => access::text('severity_' . $severity),
            'treatment' => access::text('treatment_' . $treatment),
            'recommendation' => access::text('advice_' . $finding['rule']),
            'change' => isset($finding['change']) ? access::text('change_' . $finding['change']) : '',
            'canpreview' => $maypreview && $treatment === 'repair' && ($finding['change'] ?? '') !== 'resolved',
            'itemid' => $finding['id'],
        ];
    }

    /**
     * Expose only derived history labels.
     *
     * @param stdClass $entry Audit entry
     * @param int $courseid Course ID
     * @return array
     */
    public static function historyrow(stdClass $entry, int $courseid): array {
        return [
            'id' => $entry->id,
            'url' => access::url('index', ['courseid' => $courseid, 'id' => $entry->id])->out(false),
            'label' => access::text('kind_' . $entry->kind),
            'date' => userdate($entry->timecreated),
            'actor' => $entry->userid ? access::text('operatorid', $entry->userid) : access::text('anonymized'),
        ];
    }
}
