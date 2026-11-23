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

namespace local_skillupspathways\integration;

use local_skillupsgamification\integration\milestone_provider;

defined('MOODLE_INTERNAL') || die();

if (!interface_exists(milestone_provider::class)) {
    return;
}

/**
 * Optional Gamification Pro v1 provider.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gamification_provider implements milestone_provider {
    /** @var int Gamification contract supported. */
    public const CONTRACT_VERSION = 1;

    /**
     * Return the verified persisted date when the milestone is an entire Pathways itinerary.
     *
     * @param int $courseid Entry course
     * @param int $userid Learner
     * @param int $milestoneid Path ID
     * @return int|null
     */
    public function completed_at(int $courseid, int $userid, int $milestoneid): ?int {
        return certification::verified($courseid, $milestoneid, $userid, false)['completedat'] ?? null;
    }
}
