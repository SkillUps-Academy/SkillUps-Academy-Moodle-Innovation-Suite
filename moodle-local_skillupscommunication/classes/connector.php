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

namespace local_skillupscommunication;

/**
 * Contract for an explicitly authorized installed Moodle connector.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface connector {
    /**
     * Hand off one notification using the supplied idempotency key.
     *
     * Return accepted or delivered only with the corresponding evidence. Return retry
     * only when the handoff provably did not take effect. An uncertain outcome must throw.
     *
     * @param int $userid Target Moodle account
     * @param string $subject Subject
     * @param string $body Plain text
     * @param string $key Stable idempotence identifier
     * @return string accepted, delivered or retry
     */
    public function send(int $userid, string $subject, string $body, string $key): string;
}
