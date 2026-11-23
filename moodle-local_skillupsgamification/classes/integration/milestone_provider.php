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

namespace local_skillupsgamification\integration;

/**
 * Local contract for verifying an optional Learning Path milestone.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface milestone_provider {
    /**
     * Return persisted completion time or null if unverified or outside the given course and user.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @param int $milestoneid Immutable milestone ID
     * @return int|null UTC Unix completion timestamp
     */
    public function completed_at(int $courseid, int $userid, int $milestoneid): ?int;
}
