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

namespace tool_skillupsmigration\task;

use tool_skillupsmigration\processor;
use tool_skillupsmigration\source;

/**
 * Run an origin artifact or one destination wave asynchronously.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class process extends \core\task\adhoc_task {
    /**
     * Execute the scheduled action.
     */
    public function execute(): void {
        $payload = $this->get_custom_data();
        if (isset($payload->source)) {
            source::build((int)$payload->source);
        } else {
            processor::advance((int)$payload->job, (int)$payload->wave);
        }
    }
}
