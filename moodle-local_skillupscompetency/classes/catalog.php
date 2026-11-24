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

namespace local_skillupscompetency;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;

/**
 * Reuse native scales and course associations, and store achievement indicators.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /**
     * Return the native scale labels indexed by level.
     *
     * @param competency $item Native competency record
     * @return array
     */
    public static function levels(competency $item): array {
        $options = [];
        foreach ($item->get_scale()->load_items() as $index => $label) {
            $options[$index + 1] = $label;
        }
        return $options;
    }

    /**
     * Save or clear an indicator for one proficiency level.
     *
     * @param int $id Record ID
     * @param int $level Ordinal scale level
     * @param string $text Indicator text
     */
    public static function indicator(int $id, int $level, string $text): void {
        store::write(static function () use ($id, $level, $text): void {
            $item = access::competency($id);
            $context = $item->get_context();
            require_capability(access::CAP . 'catalog', $context);
            require_capability('moodle/competency:competencymanage', $context);
            $value = trim($text);
            if (!isset(self::levels($item)[$level]) || \core_text::strlen($value) > 4000) {
                access::fail('invalidlevel');
            }
            self::write_indicator($item, $level, $value);
        });
    }

    /**
     * Import or export indicators by portable framework, competency and scale labels.
     *
     * @param competency_framework $framework Authorized native framework
     * @param string|null $input Uploaded JSON, or null to export
     * @return array|null Export document or null after import
     */
    public static function interchange(competency_framework $framework, ?string $input = null): ?array {
        global $DB;
        require_capability(access::CAP . 'catalog', $framework->get_context());
        require_capability('moodle/competency:competencymanage', $framework->get_context());
        $format = 'skillups-competency-indicators/1';
        $code = $framework->get('idnumber');
        $items = api::list_competencies(['competencyframeworkid' => $framework->get('id')]);
        if ($input === null) {
            $export = [];
            foreach ($items as $item) {
                $levels = self::levels($item);
                $records = $DB->get_records(store::IND, ['competencyid' => $item->get('id'), 'scaleid' => $item->get_scale()->id]);
                foreach ($records as $row) {
                    if (isset($levels[$row->grade])) {
                        $export[] = ['code' => $item->get('idnumber'), 'level' => (int)$row->grade,
                            'label' => $levels[$row->grade], 'indicator' => $row->indicator];
                    }
                }
            }
            return ['format' => $format, 'framework' => $code, 'indicators' => $export];
        }
        if (strlen($input) > 262144) {
            access::fail('invaliddocument');
        }
        try {
            $document = json_decode($input, true, 6, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            access::fail('invaliddocument');
        }
        if (
            !is_array($document) || ($document['format'] ?? null) !== $format || ($document['framework'] ?? null) !== $code ||
            !is_array($document['indicators'] ?? null) || count($document['indicators']) > 1000
        ) {
            access::fail('invaliddocument');
        }
        $bycode = [];
        foreach ($items as $item) {
            $bycode[$item->get('idnumber')] = $item;
        }
        $checked = [];
        foreach ($document['indicators'] as $row) {
            if (
                !is_array($row) || !is_string($row['code'] ?? null) || !is_int($row['level'] ?? null) ||
                !is_string($row['indicator'] ?? null) || \core_text::strlen($row['indicator']) > 4000
            ) {
                access::fail('invaliddocument');
            }
            $item = $bycode[$row['code']] ?? null;
            $level = $row['level'];
            $key = $row['code'] . ':' . $level;
            if (
                !$item || !isset(self::levels($item)[$level]) || self::levels($item)[$level] !== ($row['label'] ?? null) ||
                isset($checked[$key])
            ) {
                access::fail('invaliddocument');
            }
            $checked[$key] = [$item, $level, trim($row['indicator'])];
        }
        store::write(static function () use ($checked): void {
            foreach ($checked as [$item, $level, $text]) {
                self::write_indicator($item, $level, $text);
            }
        });
        return null;
    }

    /**
     * Write an already validated indicator while holding the write lock.
     *
     * @param competency $item Native competency
     * @param int $level Native proficiency level
     * @param string $value Indicator text, or empty to clear it
     */
    private static function write_indicator(competency $item, int $level, string $value): void {
        global $DB;
        $key = ['competencyid' => $item->get('id'), 'scaleid' => $item->get_scale()->id, 'grade' => $level];
        $existing = $DB->get_field(store::IND, 'id', $key);
        if ($value === '') {
            $DB->delete_records(store::IND, $key);
        } else if ($existing) {
            $DB->set_field(store::IND, 'indicator', $value, ['id' => $existing]);
        } else {
            $DB->insert_record(store::IND, (object)($key + ['indicator' => $value]));
        }
    }

    /**
     * Write the native course and activity associations.
     *
     * @param \stdClass $course Moodle course record
     * @param int $competencyid Native competency ID
     * @param int $cmid Optional course activity ID
     */
    public static function link(\stdClass $course, int $competencyid, int $cmid = 0): void {
        $context = \context_course::instance($course->id);
        require_capability('moodle/competency:coursecompetencymanage', $context);
        access::competency($competencyid, $context);
        if ($cmid && !(get_fast_modinfo($course)->get_cms()[$cmid]->uservisible ?? false)) {
            access::fail();
        }
        store::write(static function () use ($course, $competencyid, $cmid): void {
            api::add_competency_to_course((int)$course->id, $competencyid);
            if ($cmid) {
                api::add_competency_to_course_module($cmid, $competencyid);
            }
        });
    }
}
