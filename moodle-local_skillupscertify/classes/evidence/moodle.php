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

namespace local_skillupscertify\evidence;

use stdClass;

/**
 * Native course completion and optional Pathways evidence.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class moodle implements source {
    /**
     * Return the timestamp and type of verified evidence.
     *
     * @param stdClass $course Course
     * @param stdClass $revision Configuration
     * @param int $userid Subject
     * @param int $now Issuance time
     * @return array|null
     */
    public function proof(stdClass $course, stdClass $revision, int $userid, int $now): ?array {
        global $CFG;
        if ($revision->source === 'manual') {
            return ['kind' => 'manual', 'time' => $now];
        }
        if ($revision->source === 'pathway') {
            $provider = \local_skillupspathways\integration\certification::class;
            if (!self::pathways()) {
                return null;
            }
            $proof = $provider::verified_completion((int)$course->id, (int)$revision->pathid, $userid);
            return $proof === null ? null : [
                'kind' => 'pathway', 'time' => $proof['completedat'], 'pathid' => $proof['pathid'], 'version' => $proof['version'],
            ];
        }
        if ($revision->source !== 'course') {
            return null;
        }
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($course);
        if (!$completion->is_enabled() || !$completion->is_course_complete($userid)) {
            return null;
        }
        $record = new \completion_completion(['course' => $course->id, 'userid' => $userid]);
        return $record->timecompleted ? ['kind' => 'course', 'time' => (int)$record->timecompleted] : null;
    }

    /**
     * Check the local integration contract.
     *
     * @return bool
     */
    public static function pathways(): bool {
        $provider = \local_skillupspathways\integration\certification::class;
        return class_exists($provider) && defined($provider . '::CONTRACT_VERSION') && $provider::CONTRACT_VERSION === 1;
    }
}
