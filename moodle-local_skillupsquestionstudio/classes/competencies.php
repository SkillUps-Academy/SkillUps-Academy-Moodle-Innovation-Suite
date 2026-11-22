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

namespace local_skillupsquestionstudio;

/**
 * Reuse the Moodle competencies already associated with the project course.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class competencies {
    /**
     * Provide only identities returned by Moodle's course competencies service.
     */
    public static function options(int $id): array {
        global $CFG;
        $options = [0 => get_string('notassigned', access::C)];
        if (empty($CFG->enablecompetencies)) {
            return $options;
        }
        try {
            foreach (\core_competency\api::list_course_competencies($id) as $item) {
                $entry = $item['competency'];
                $options[$entry->get('id')] = $entry->get('shortname');
            }
        } catch (\required_capability_exception $exception) {
            return $options;
        }
        return $options;
    }

    /**
     * Enforce the same mapping on direct form submissions and publication.
     */
    public static function require_valid(int $courseid, int $id): void {
        if ($id && !isset(self::options($courseid)[$id])) {
            access::fail('invalidcompetency');
        }
    }
}
