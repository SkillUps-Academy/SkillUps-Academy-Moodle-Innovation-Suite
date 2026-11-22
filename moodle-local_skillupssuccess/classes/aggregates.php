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

namespace local_skillupssuccess;

/**
 * Desensitize course and program reporting into fixed bands and supported rates.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class aggregates {
    /**
     * Optional course custom field follows native per-course visibility.
     */
    public static function labels(array $courses, bool $programs): array {
        if (!$programs) {
            return array_map(static fn($course) => format_string(
                $course->fullname,
                true,
                ['context' => \context_course::instance($course->id), 'escape' => false]
            ), $courses);
        }
        $labels = array_fill_keys(array_keys($courses), util::str('unmapped'));
        $short = (string)(get_config(util::C, 'programfield') ?: 'insightprogram');
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_fields() as $field) {
            if ($field->get('shortname') !== $short || $field->get('type') !== 'text') {
                continue;
            }
            $visible = array_filter(array_keys($courses), static fn($id) => $handler->can_view($field, $id));
            $fid = $field->get('id');
            foreach (array_chunk($visible, 200) as $ids) {
                $rows = \core_customfield\api::get_instances_fields_data([$fid => $field], $ids);
                foreach ($rows as $id => $data) {
                    $value = trim(clean_param($data[$fid]->export_value() ?? '', PARAM_TEXT));
                    if ($value !== '') {
                        $labels[$id] = \core_text::substr($value, 0, 120);
                    }
                }
            }
            break;
        }
        return $labels;
    }

    /**
     * Aggregate operational outcomes without selecting identities or notes.
     */
    public static function cases(array $courses): array {
        global $DB;
        $cases = store::CASES;
        $entries = store::ENTRY;
        $results = [];
        foreach (array_chunk(array_keys($courses), 200) as $ids) {
            [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'course');
            $rows = $DB->get_records_sql("SELECT c.courseid, COUNT(1) cases,
                SUM(CASE WHEN c.state = :resolved THEN 1 ELSE 0 END) resolved,
                SUM(CASE WHEN c.tutorid > 0 THEN 1 ELSE 0 END) assigned,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM {{$entries}} e WHERE e.caseid = c.id AND
                     e.action IN (:contact, :plan, :followup)) THEN 1 ELSE 0 END) supported
                FROM {{$cases}} c WHERE c.courseid $sql GROUP BY c.courseid", $params +
                ['resolved' => 'resolved', 'contact' => 'contact', 'plan' => 'plan', 'followup' => 'followup']);
            $results += $rows;
        }
        return $results;
    }

    /**
     * Combine one scope's per-course data into exclusively requested display groups.
     */
    public static function build(array $courses, bool $programs, int $cohort, bool $statistical): array {
        $labels = self::labels($courses, $programs);
        $cases = self::cases($courses);
        $quality = quality::cohorts($courses, $cohort, $statistical);
        $casegroups = [];
        $study = [];
        foreach ($labels as $id => $label) {
            $label = trim(html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $key = $programs ? 'program:' . $label : 'course:' . $id;
            $casegroups[$key] ??= ['label' => $label, 'cases' => 0, 'resolved' => 0, 'assigned' => 0, 'supported' => 0];
            foreach (['cases', 'resolved', 'assigned', 'supported'] as $metric) {
                $casegroups[$key][$metric] += $cases[$id]->{$metric} ?? 0;
            }
            foreach ($quality[$id] ?? [] as $version => $data) {
                $entry = $study[$key][$version] ?? ['label' => $label, 'version' => $version, 'data' => quality::blank()];
                foreach ($data as $name => $quantity) {
                    $entry['data'][$name] += $quantity;
                }
                $study[$key][$version] = $entry;
            }
        }
        $release = [];
        foreach ($study as $entries) {
            foreach ($entries as $entry) {
                if ($entry['data']['total'] >= quality::minimum()) {
                    $release[] = ['label' => $entry['label'], 'version' => $entry['version']] +
                        quality::published($entry['data'], $statistical);
                }
            }
        }
        $caseout = [];
        foreach ($casegroups as $row) {
            if ($row['cases'] >= quality::minimum()) {
                $caseout[] = ['label' => $row['label'], ...array_map(
                    [quality::class, 'band'],
                    array_intersect_key($row, array_flip(['cases', 'resolved', 'assigned', 'supported']))
                )];
            }
        }
        return [$caseout, $release];
    }
}
