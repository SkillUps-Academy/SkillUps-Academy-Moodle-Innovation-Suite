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

namespace local_skillupsquestionstudio\task;

/**
 * Execute an upload or generation request.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class process extends \core\task\adhoc_task {
    /**
     * Do not automatically repeat a possibly billed model request.
     */
    public function retry_until_success(): bool {
        return false;
    }

    /**
     * Dispatch the validated ID.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        \local_skillupsquestionstudio\work::execute((string)$data->type, (int)$data->id);
    }
}
