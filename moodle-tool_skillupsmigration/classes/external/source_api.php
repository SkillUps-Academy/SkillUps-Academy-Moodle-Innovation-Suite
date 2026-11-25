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

namespace tool_skillupsmigration\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_skillupsmigration\inventory;
use tool_skillupsmigration\source;
use tool_skillupsmigration\support;

/**
 * Expose only scoped inventory and backup actions through the native REST service.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_api extends external_api {
    /**
     * Validate the native service arguments.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'operation' => new external_value(PARAM_ALPHA, 'Source action'),
            'payload' => new external_value(PARAM_RAW, 'Structured action arguments'),
        ]);
    }

    /**
     * Run an authorized source action.
     *
     * @param string $operation Action name
     * @param string $payload JSON arguments
     * @return array JSON result envelope
     */
    public static function execute(string $operation, string $payload): array {
        global $CFG, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('operation', 'payload'));
        $actor = (int)$USER->id;
        support::active($actor);
        if (strlen($params['payload']) > 1048576) {
            support::fail('badrequest');
        }
        try {
            $data = support::decode($params['payload']);
        } catch (\Throwable $error) {
            support::fail('badrequest');
        }
        $op = $params['operation'];
        $course = (int)($data['course'] ?? 0);
        if (in_array($op, ['inventory', 'prepare'], true)) {
            inventory::course($course, $actor);
            self::validate_context(\context_course::instance($course));
        } else if (in_array($op, ['status', 'chunk'], true)) {
            $artifact = support::get(support::ARTIFACT, (int)($data['id'] ?? 0));
            if ($artifact->actor != $actor) {
                support::fail('unavailable');
            }
            inventory::course((int)$artifact->courseid, $actor);
            self::validate_context(\context_course::instance($artifact->courseid));
        } else {
            self::validate_context(\context_system::instance());
        }
        $result = match ($op) {
            'probe' => ['protocol' => support::PROTOCOL, 'version' => (int)$CFG->version],
            'browse' => inventory::browse($actor, (int)($data['after'] ?? 0), (int)($data['category'] ?? 0)),
            'inventory' => inventory::read($course, $actor, inventory::options($data['options'] ?? [])),
            'prepare' => source::prepare($actor, $data),
            'status' => source::read($actor, (int)($data['id'] ?? 0), null),
            'chunk' => source::read($actor, (int)($data['id'] ?? 0), (int)($data['offset'] ?? -1)),
            default => support::fail('badrequest'),
        };
        return ['data' => support::encode($result)];
    }

    /**
     * Declare the stable REST result envelope.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['data' => new external_value(PARAM_RAW, 'Serialized action result')]);
    }
}
