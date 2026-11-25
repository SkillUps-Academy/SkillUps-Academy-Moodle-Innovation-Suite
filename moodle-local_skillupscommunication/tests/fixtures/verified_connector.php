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
 * Synthetic connector evidence used only by the isolated PHPUnit suite.
 *
 * @package    local_skillupscommunication
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class verified_connector implements connector {
    /**
     * Return deterministic synthetic delivery evidence.
     *
     * @param int $userid Moodle target
     * @param string $subject Subject
     * @param string $body Message
     * @param string $key Stable key
     * @return string
     */
    public function send(int $userid, string $subject, string $body, string $key): string {
        return strlen($key) === 64 ? 'delivered' : 'retry';
    }
}
