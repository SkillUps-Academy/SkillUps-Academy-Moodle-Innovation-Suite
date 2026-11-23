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

use moodle_exception;

/**
 * Validate, normalize and sort a bounded directed acyclic itinerary graph.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class graph {
    /** @var array Prerequisite strategies. */
    public const JOINS = ['all', 'any'];

    /**
     * Parse an author draft and reject malformed graphs, invalid IDs and cycles.
     *
     * @param string $encoded Draft JSON
     * @param bool $publishing Require a meaningful publishable graph
     * @return array
     */
    public static function parse(string $encoded, bool $publishing = false): array {
        if (strlen($encoded) > 90000) {
            self::invalid();
        }
        try {
            $value = json_decode($encoded, true, 12, JSON_THROW_ON_ERROR);
        } catch (\JsonException $failure) {
            self::invalid();
        }
        if (!is_array($value) || !is_array($value['nodes'] ?? null) || !is_array($value['edges'] ?? null)) {
            self::invalid();
        }
        if (count($value['nodes']) > 60 || count($value['edges']) > 150) {
            self::invalid();
        }
        $nodes = [];
        foreach ($value['nodes'] as $source) {
            $node = self::node($source);
            if (isset($nodes[$node['key']])) {
                self::invalid();
            }
            $nodes[$node['key']] = $node;
        }
        $edges = [];
        foreach ($value['edges'] as $edge) {
            if (
                !is_array($edge) || !is_string($edge['from'] ?? null) || !is_string($edge['to'] ?? null) ||
                !isset($nodes[$edge['from']], $nodes[$edge['to']])
            ) {
                self::invalid();
            }
            $key = $edge['from'] . ':' . $edge['to'];
            $edges[$key] = ['from' => $edge['from'], 'to' => $edge['to']];
        }
        if ($publishing && (!$nodes || !in_array(true, array_column($nodes, 'required'), true))) {
            throw new moodle_exception('mandatoryrequired', access::C);
        }
        $result = ['nodes' => array_values($nodes), 'edges' => array_values($edges)];
        self::order($result);
        return $result;
    }

    /**
     * Normalize one node without coercing arbitrary input.
     *
     * @param mixed $source Input node data
     * @return array
     */
    private static function node(mixed $source): array {
        if (
            !is_array($source) || !is_string($source['key'] ?? null) || !preg_match('/^[a-z][a-z0-9]{0,31}$/D', $source['key']) ||
            !is_string($source['title'] ?? null) || !is_bool($source['completion'] ?? null) ||
            !is_bool($source['required'] ?? null) || !in_array($source['join'] ?? null, self::JOINS, true)
        ) {
            self::invalid();
        }
        $title = trim(clean_param($source['title'], PARAM_TEXT));
        if ($title === '' || \core_text::strlen($title) > 120) {
            self::invalid();
        }
        $node = [
            'key' => $source['key'], 'title' => $title, 'completion' => $source['completion'],
            'required' => $source['required'], 'join' => $source['join'],
        ];
        foreach (
            ['course' => [1, PHP_INT_MAX], 'cm' => [0, PHP_INT_MAX], 'grade' => [-1, 100],
            'competency' => [0, PHP_INT_MAX], 'lane' => [0, 7], 'row' => [0, 59]] as $field => [$min, $max]
        ) {
            $entry = $source[$field] ?? null;
            if (!is_int($entry) || $entry < $min || $entry > $max) {
                self::invalid();
            }
            $node[$field] = $entry;
        }
        if (!$node['completion'] && $node['grade'] < 0 && !$node['competency']) {
            self::invalid();
        }
        return $node;
    }

    /**
     * Return ordered nodes with prerequisite lists, detecting cycles by topological sort.
     *
     * @param array $graph Normalized graph
     * @return array
     */
    public static function order(array $graph): array {
        $indexed = array_column($graph['nodes'], null, 'key');
        $parents = array_fill_keys(array_keys($indexed), []);
        $children = $parents;
        foreach ($graph['edges'] as $edge) {
            $parents[$edge['to']][] = $edge['from'];
            $children[$edge['from']][] = $edge['to'];
        }
        $remaining = array_map('count', $parents);
        $queue = array_keys(array_filter($remaining, static fn(int $count): bool => !$count));
        $result = [];
        for ($i = 0; $i < count($queue); $i++) {
            $key = $queue[$i];
            $result[] = $indexed[$key] + ['parents' => $parents[$key]];
            foreach ($children[$key] as $child) {
                if (--$remaining[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }
        if (count($result) !== count($indexed)) {
            throw new moodle_exception('cycle', access::C);
        }
        return $result;
    }

    /**
     * Raise a standard validation error.
     */
    private static function invalid(): never {
        throw new moodle_exception('invalidgraph', access::C);
    }
}
