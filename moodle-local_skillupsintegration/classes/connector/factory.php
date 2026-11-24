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

namespace local_skillupsintegration\connector;

use local_skillupsintegration\http_transport;
use local_skillupsintegration\problem;
use local_skillupsintegration\profile;
use local_skillupsintegration\transport;
use stdClass;

/**
 * Resolve installed adapters with no dependency on their protocol internals.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class factory {
    /**
     * Return a connector for the selected source family.
     *
     * @param stdClass $source Source
     * @param transport|null $http Optional transport for tests
     * @return driver
     */
    public static function make(stdClass $source, ?transport $http = null): driver {
        $options = profile::settings($source);
        $http ??= new http_transport();
        return match ($source->type) {
            'google' => new google($source, $options, $http),
            'microsoft' => new microsoft($source, $options, $http),
            'sis', 'hr' => new contract($source, $options, $http),
            'inbound' => new inbound($source, $options, $http),
            default => throw new problem('config'),
        };
    }
}
