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
use local_skillupsintegration\problem;
use local_skillupsintegration\profile;
use local_skillupsintegration\runs;

/**
 * Return minimized status for the REST operator who submitted a run.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class status extends external_api {
    /**
     * Describe the requested run ID.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['runid' => new external_value(PARAM_INT, 'Run ID')]);
    }

    /**
     * Return a run state for the originating account only.
     *
     * @param int $runid Submitted run
     * @return array
     */
    public static function execute(int $runid): array {
        global $CFG, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['runid' => $runid]);
        self::validate_context(\context_system::instance());
        access::require('ingest');
        if (!str_starts_with($CFG->wwwroot, 'https://')) {
            throw new problem('permission');
        }
        try {
            $run = runs::get($params['runid']);
            $source = profile::get($run->sourceid);
            if (
                !$source->enabled || $run->origin !== 'rest' || (int)$run->actorid !== (int)$USER->id ||
                (int)$source->pushuserid !== (int)$USER->id
            ) {
                throw new problem('unavailable');
            }
        } catch (problem $failure) {
            throw new problem('unavailable');
        }
        return ['runid' => (int)$run->id, 'state' => $run->state, 'records' => (int)$run->records];
    }

    /**
     * Describe the transport response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Run ID'),
            'state' => new external_value(PARAM_ALPHA, 'Current run state'),
            'records' => new external_value(PARAM_INT, 'Number of rows received'),
        ]);
    }
}
