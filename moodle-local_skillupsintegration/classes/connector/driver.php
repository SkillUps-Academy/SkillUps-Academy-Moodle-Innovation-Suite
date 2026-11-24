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

/**
 * The protocol contract for a SkillUps source.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface driver {
    /**
     * Declare runtime capabilities.
     *
     * @return array
     */
    public function capabilities(): array;

    /**
     * Identify the authentication documentation key.
     *
     * @return string
     */
    public function authentication(): string;

    /**
     * Fetch one bounded page.
     *
     * @param string $entity User or course
     * @param string $cursor Provider continuation
     * @return array Pair of records and cursor
     */
    public function page(string $entity, string $cursor): array;

    /**
     * Describe the native source field mappings.
     *
     * @param string $entity User or course
     * @return array
     */
    public function defaults(string $entity): array;
}
