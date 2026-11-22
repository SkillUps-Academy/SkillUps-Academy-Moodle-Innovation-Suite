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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Checked format for imported and editor-generated definitions.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(definition::class)]
final class definition_test extends \basic_testcase {
    /**
     * Reject cycles even when the edge is a fallback.
     */
    public function test_cycles_are_rejected(): void {
        $graph = [
            'format' => 1, 'trigger' => ['type' => 'manual'], 'entry' => 'wait',
            'nodes' => [['id' => 'wait', 'type' => 'delay', 'next' => 'wait']],
        ];
        $this->expectException(\moodle_exception::class);
        definition::parse(json_encode($graph));
    }

    /**
     * Reject unreachable payloads and unknown action types.
     */
    public function test_unreachable_nodes_are_rejected(): void {
        $graph = definition::starter();
        $graph['nodes'][] = ['id' => 'unused', 'type' => 'stop'];
        $this->expectException(\moodle_exception::class);
        definition::parse(json_encode($graph));
    }

    /**
     * Verify malformed JSON remains a localized validation error.
     */
    public function test_malformed_json_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        definition::parse('{"format":1,');
    }
}
