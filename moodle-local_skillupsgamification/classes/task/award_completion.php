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

namespace local_skillupsgamification\task;

use local_skillupsgamification\access;
use local_skillupsgamification\engine;

/**
 * Retryable native completion award.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class award_completion extends \core\task\adhoc_task {
    /**
     * Return the task label.
     *
     * @return string
     */
    public function get_name(): string {
        return access::text('awardtask');
    }

    /**
     * Reverify the completion before awarding.
     */
    public function execute(): void {
        $source = $this->get_custom_data();
        engine::award(
            (int)$source->courseid,
            (int)$this->get_userid(),
            $source->kind,
            (int)$source->sourceid,
            (int)$source->recordid,
            (int)$source->occurred,
            (int)$this->get_id()
        );
    }
}
