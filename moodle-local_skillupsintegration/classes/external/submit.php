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

namespace local_skillupsintegration\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_skillupsintegration\access;
use local_skillupsintegration\runs;

/**
 * Expose authenticated JSON ingestion through the Moodle REST web service.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submit extends external_api {
    /**
     * Describe the connector identifier and body.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourceid' => new external_value(PARAM_INT, 'Connector ID'),
            'payload' => new external_value(PARAM_RAW, 'Exact JSON event'),
        ]);
    }

    /**
     * Accept a bounded event for its dedicated service account.
     *
     * @param int $sourceid Connector
     * @param string $payload Documented JSON contract
     * @return array
     */
    public static function execute(int $sourceid, string $payload): array {
        global $CFG, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['sourceid' => $sourceid, 'payload' => $payload]);
        self::validate_context(\context_system::instance());
        access::require('ingest');
        if (!str_starts_with($CFG->wwwroot, 'https://')) {
            throw new \local_skillupsintegration\problem('permission');
        }
        return runs::ingest($params['sourceid'], 'rest', (int)$USER->id, $params['payload']);
    }

    /**
     * Describe the stable acknowledgement.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Simulation ID'),
            'state' => new external_value(PARAM_ALPHA, 'Current simulation state'),
            'replayed' => new external_value(PARAM_BOOL, 'Whether the event was already acknowledged'),
        ]);
    }
}
