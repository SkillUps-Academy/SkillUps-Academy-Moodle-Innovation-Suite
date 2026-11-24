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

namespace local_skillupsintegration;

/**
 * A bounded, localised integration error safe to persist or return.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class problem extends \moodle_exception {
    /**
     * Construct a sanitized error.
     *
     * @param string $reason Machine-readable code
     * @param bool $retryable Whether to retry automatically
     * @param int $wait Minimum retry delay in seconds
     */
    public function __construct(
        /** @var string Safe operational reason. */
        public readonly string $reason,
        /** @var bool Whether automatic reattempts are allowed. */
        public readonly bool $retryable = false,
        /** @var int Minimum retry delay in seconds. */
        public readonly int $wait = 0,
    ) {
        parent::__construct('err_' . $reason, 'local_skillupsintegration');
    }
}
