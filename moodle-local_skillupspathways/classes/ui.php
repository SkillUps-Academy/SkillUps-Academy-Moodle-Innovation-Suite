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

namespace local_skillupspathways;

use moodle_url;
use stdClass;

/**
 * Localized page setup, navigation and safe diagram data.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Initialize native Moodle course output.
     *
     * @param stdClass $course Entry course
     * @param moodle_url $url Page URL
     * @param string $title Heading
     */
    public static function page(stdClass $course, moodle_url $url, string $title): void {
        global $PAGE;
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title($title);
        $PAGE->set_heading($course->fullname);
        $PAGE->navbar->add(access::text('pluginname'), access::url('index', (int)$course->id));
    }

    /**
     * Build capability-scoped local navigation.
     *
     * @param int $courseid Entry course
     * @param int $pathid Current path
     * @return array
     */
    public static function nav(int $courseid, int $pathid = 0): array {
        $context = \context_course::instance($courseid);
        $manager = has_capability(access::CAP . 'manage', $context);
        $report = has_capability(access::CAP . 'report', $context);
        return [
            'listurl' => access::url('index', $courseid)->out(false),
            'manageurl' => $manager ? access::url('designer', $courseid, ['pathid' => $pathid])->out(false) : '',
            'reportsurl' => $report && $pathid ? access::url('report', $courseid, ['pathid' => $pathid])->out(false) : '',
            'courseid' => $courseid,
            'pathid' => $pathid,
        ];
    }

    /**
     * Get localized editor labels for the JavaScript module.
     *
     * @return array
     */
    public static function editor_labels(): array {
        $keys = [
            'editorhint', 'addstep', 'stepmax', 'map', 'stepoptions', 'nochoices', 'prerequisites', 'nosteps',
            'steptitle', 'moved', 'edgelimit', 'cycle', 'right', 'down', 'remove',
        ];
        $labels = [];
        foreach ($keys as $key) {
            $labels[$key] = access::text($key);
        }
        foreach (['title', 'course', 'cm', 'grade', 'competency', 'completion', 'required', 'join', 'all', 'any'] as $key) {
            $labels[$key] = access::text('field_' . $key);
        }
        return $labels;
    }

    /**
     * Present evaluated graph rows without exposing restricted destinations.
     *
     * @param array $result Live results
     * @return array
     */
    public static function steps(array $result): array {
        $bykey = array_column($result['rows'], 'title', 'key');
        $rows = [];
        foreach ($result['rows'] as $row) {
            $titles = array_map(static fn(string $key): string => $bykey[$key], $row['parents']);
            $rows[] = $row + [
                'statelabel' => access::text('state_' . $row['state']),
                'recommended' => $row['state'] === 'ready',
                'requirement' => access::text($row['required'] ? 'requiredstep' : 'optionalstep'),
                'prerequisite' => $titles ? access::text('prereq_' . $row['join'], implode(' · ', $titles)) : '',
            ];
        }
        return $rows;
    }

    /**
     * Build only the layout and resolved title/status for the graphic view.
     *
     * @param array $rows Scoped rows
     * @return array
     */
    public static function diagram(array $rows): array {
        $nodes = [];
        $edges = [];
        foreach ($rows as $row) {
            $nodes[] = array_intersect_key($row, array_flip(['key', 'title', 'state', 'lane', 'row']));
            foreach ($row['parents'] as $parent) {
                $edges[] = ['from' => $parent, 'to' => $row['key']];
            }
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Build safe state labels for the diagram.
     *
     * @return array
     */
    public static function diagram_labels(): array {
        $states = [];
        foreach (['complete', 'ready', 'blocked', 'unavailable'] as $state) {
            $states[$state] = access::text('state_' . $state);
        }
        return ['map' => access::text('map'), 'states' => $states];
    }
}
