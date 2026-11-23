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

namespace local_skillupscertify\evidence;

use stdClass;

/**
 * Contract for a trusted source of issuance evidence.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface source {
    /**
     * Return subject-specific persisted evidence or null when it is unavailable.
     *
     * @param stdClass $course Course
     * @param stdClass $revision Immutable configuration
     * @param int $userid Subject
     * @param int $now Issuance time
     * @return array|null
     */
    public function proof(stdClass $course, stdClass $revision, int $userid, int $now): ?array;
}
