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

namespace local_skillupsflow;

/**
 * Parser for the portable, strictly bounded workflow format.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class definition {
    /**
     * Registered Moodle event identifiers.
     */
    public const EVENTS = ['user_created', 'course_created', 'course_completed', 'user_enrolment_created'];
    /**
     * Editor and executor share the same node catalogue and ports.
     */
    public const PORTS = [
        'condition' => ['yes', 'no', 'error'], 'delay' => ['next'],
        'approval' => ['yes', 'no'], 'suspend' => ['next', 'error'],
        'visibility' => ['next', 'error'], 'enrol' => ['next', 'error'],
        'notify' => ['next', 'error'], 'stop' => [],
    ];

    /**
     * Read, validate and normalize editor or template input.
     */
    public static function parse(string $json): array {
        if (strlen($json) > 65536) {
            self::invalid();
        }
        try {
            $data = json_decode($json, true, 12, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            self::invalid();
        }
        if (
            !is_array($data) || ($data['format'] ?? null) !== 1 ||
                !is_array($data['trigger'] ?? null) || !is_array($data['nodes'] ?? null) ||
                !array_is_list($data['nodes']) || count($data['nodes']) < 1 || count($data['nodes']) > 40
        ) {
            self::invalid();
        }
        $trigger = $data['trigger'];
        $type = $trigger['type'] ?? '';
        if (!in_array($type, array_merge(['manual', 'once', 'interval'], self::EVENTS), true)) {
            self::invalid();
        }
        $trigger = [
            'type' => $type, 'userid' => self::integer($trigger, 'userid', 0, 2147483647, 0),
            'courseid' => self::integer($trigger, 'courseid', 0, 2147483647, 0),
            'start' => self::integer($trigger, 'start', 0, 253402300799, 0),
            'minutes' => self::integer($trigger, 'minutes', 1, 43200, 60),
        ];
        if ($type === 'once' && !$trigger['start']) {
            self::invalid();
        }
        $nodes = [];
        foreach ($data['nodes'] as $node) {
            if (
                !is_array($node) || !is_string($node['id'] ?? null) ||
                    !preg_match('/\A[a-z][a-z0-9]{0,23}\z/D', $node['id']) ||
                    isset($nodes[$node['id']]) || !is_string($node['type'] ?? null) ||
                    !array_key_exists($node['type'], self::PORTS)
            ) {
                self::invalid();
            }
            $id = $node['id'];
            $kind = $node['type'];
            $normalized = [
                'id' => $id, 'type' => $kind, 'label' => self::plain($node['label'] ?? '', 100),
                'x' => self::integer($node, 'x', 0, 3800, 30), 'y' => self::integer($node, 'y', 0, 3800, 30),
                'userid' => self::integer($node, 'userid', 0, 2147483647, 0),
                'courseid' => self::integer($node, 'courseid', 0, 2147483647, 0),
            ];
            foreach (self::PORTS[$kind] as $port) {
                $target = $node[$port] ?? '';
                if (!is_string($target)) {
                    self::invalid();
                }
                $normalized[$port] = $target;
            }
            $normalized += match ($kind) {
                'condition' => self::condition($node),
                'delay' => ['minutes' => self::integer($node, 'minutes', 1, 43200, 5)],
                'approval' => ['minutes' => self::integer($node, 'minutes', 1, 43200, 1440)],
                'suspend', 'visibility' => ['value' => self::integer($node, 'value', 0, 1, 1)],
                'enrol' => ['roleid' => self::integer($node, 'roleid', 1, 2147483647)],
                'notify' => ['body' => self::plain($node['body'] ?? '', 2000, true)],
                default => [],
            };
            $nodes[$id] = $normalized;
        }
        $start = $data['entry'] ?? '';
        if (!is_string($start) || !isset($nodes[$start])) {
            self::invalid();
        }
        $colors = [];
        self::visit($start, $nodes, $colors);
        if (count($colors) !== count($nodes)) {
            self::invalid();
        }
        return ['format' => 1, 'trigger' => $trigger, 'entry' => $start, 'nodes' => array_values($nodes)];
    }

    /**
     * Reject unknown targets, cycles and disconnected nodes at the server.
     */
    private static function visit(string $id, array $nodes, array &$colors): void {
        if (!isset($nodes[$id]) || ($colors[$id] ?? 0) === 1) {
            self::invalid();
        }
        if (($colors[$id] ?? 0) === 2) {
            return;
        }
        $colors[$id] = 1;
        foreach (self::PORTS[$nodes[$id]['type']] as $port) {
            if ($nodes[$id][$port] !== '') {
                self::visit($nodes[$id][$port], $nodes, $colors);
            }
        }
        $colors[$id] = 2;
    }

    /**
     * Parse closed predicates; users can combine them with AND or OR.
     */
    private static function condition(array $node): array {
        $items = $node['tests'] ?? [];
        if (!is_array($items) || !array_is_list($items) || !$items || count($items) > 8) {
            self::invalid();
        }
        foreach ($items as $item) {
            if (!in_array($item, ['activeuser', 'visiblecourse', 'enrolled', 'hasuser', 'hascourse'], true)) {
                self::invalid();
            }
        }
        $operator = $node['operator'] ?? 'and';
        if (!in_array($operator, ['and', 'or'], true)) {
            self::invalid();
        }
        return ['tests' => $items, 'operator' => $operator];
    }

    /**
     * Strict integers prevent ambiguous IDs and out-of-range values.
     */
    private static function integer(array $data, string $name, int $min, int $max, ?int $default = null): int {
        $value = $data[$name] ?? $default;
        if (!is_int($value) || $value < $min || $value > $max) {
            self::invalid();
        }
        return $value;
    }

    /**
     * Keep labels and messages as plain Moodle text.
     */
    private static function plain(mixed $value, int $length, bool $required = false): string {
        if (!is_string($value) || \core_text::strlen($value) > $length || ($required && trim($value) === '')) {
            self::invalid();
        }
        return trim($value);
    }

    /**
     * Generate an editable initial definition.
     */
    public static function starter(): array {
        return [
            'format' => 1, 'trigger' => ['type' => 'manual', 'userid' => 0, 'courseid' => 0, 'start' => 0, 'minutes' => 60],
            'entry' => 'start', 'nodes' => [['id' => 'start', 'type' => 'stop', 'label' => '', 'x' => 40, 'y' => 40]],
        ];
    }

    /**
     * Report a safe, localized format error.
     */
    private static function invalid(): never {
        throw new \moodle_exception('invaliddefinition', 'local_skillupsflow');
    }
}
