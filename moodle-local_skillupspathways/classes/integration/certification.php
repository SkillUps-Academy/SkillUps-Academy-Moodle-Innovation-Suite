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

use local_skillupspathways\access;
use local_skillupspathways\progress;
use local_skillupspathways\store;

/**
 * Read-only completion evidence contract for trusted local certification providers.
 *
 * @package    local_skillupspathways
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certification {
    /** @var int Public integration version. */
    public const CONTRACT_VERSION = 1;

    /**
     * Retrieve still-valid certification evidence when the pathway allows it.
     *
     * @param int $courseid Entry course
     * @param int $pathid Pathway ID
     * @param int $userid Learner ID
     * @return array|null Identifier and persisted completion for the pinned edition
     */
    public static function verified_completion(int $courseid, int $pathid, int $userid): ?array {
        return self::verified($courseid, $pathid, $userid, true);
    }

    /**
     * Resolve proof for a locally installed integration from the verified pinned itinerary.
     *
     * @param int $courseid Entry course
     * @param int $pathid Path ID
     * @param int $userid Learner ID
     * @param bool $certification Require explicit certificate authorization
     * @return array|null
     */
    public static function verified(int $courseid, int $pathid, int $userid, bool $certification): ?array {
        global $DB;
        $path = $DB->get_record(store::PATH, ['id' => $pathid, 'courseid' => $courseid, 'enabled' => 1]);
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (
            !$path || !$path->activeversion || !$course || ($certification && !$path->certificate) ||
            !access::enrolled($course, $userid, true)
        ) {
            return null;
        }
        $pinned = progress::get($pathid, $userid);
        if (!$pinned || !$pinned->completedat) {
            return null;
        }
        $version = store::version($pathid, (int)$pinned->versionid);
        $live = progress::evaluate($version, $userid);
        if (!$live['required'] || $live['passed'] !== $live['required']) {
            return null;
        }
        return ['pathid' => $pathid, 'version' => (int)$version->versionnum, 'completedat' => (int)$pinned->completedat];
    }
}
