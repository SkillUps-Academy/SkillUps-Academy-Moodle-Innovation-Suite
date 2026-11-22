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

namespace local_skillupsaigrading\activity;

/**
 * Supported activities supply authorization, immutable inputs and read-only grade comparisons.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface adapter {
    /**
     * Native module metadata.
     */
    public function module(): \cm_info;

    /**
     * Context where grading and review capabilities are checked.
     */
    public function context(): \context_module;

    /**
     * Parent course.
     */
    public function course(): \stdClass;

    /**
     * Resolve the authorized current source metadata and content fingerprint.
     */
    public function source(int $userid, int $sourceid): array;

    /**
     * Revalidate whether the current marker may access the subject.
     */
    public function visible(int $userid): bool;

    /**
     * Read the current submitted attempt for one authorized subject.
     */
    public function submission(int $userid): \stdClass;

    /**
     * Import current criteria and allowed levels from the activity.
     */
    public function rubric(): array;

    /**
     * Obtain a bounded textual evidence source through the activity's APIs.
     */
    public function extract(int $userid, int $sourceid): array;

    /**
     * Return a normalized human grade only when it matches the current attempt.
     */
    public function human_percent(int $userid, \stdClass $submission): ?float;
}
