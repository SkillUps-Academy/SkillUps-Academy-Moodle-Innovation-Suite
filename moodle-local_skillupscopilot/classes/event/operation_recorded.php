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

namespace local_skillupscopilot\event;

/**
 * An auditable operation. Payloads, answers, credentials and URLs are excluded.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class operation_recorded extends \core\event\base {
    /**
     * Set Moodle event metadata.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Moodle event name.
     */
    public static function get_name(): string {
        return get_string('eventoperation', 'local_skillupscopilot');
    }

    /**
     * Store only a fixed operation name and a local reference.
     */
    public static function record(\context $context, string $operation, int $reference = 0): void {
        self::create(['context' => $context, 'other' => ['operation' => $operation, 'reference' => $reference]])->trigger();
    }

    /**
     * Render a stable log description.
     */
    public function get_description(): string {
        return "User {$this->userid} performed Copilot operation {$this->other['operation']} ({$this->other['reference']}).";
    }
}
