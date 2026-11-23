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

namespace local_skillupscertify;

use context_course;
use moodle_url;
use stdClass;

/**
 * Native output and course navigation for the credential registry.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Initialize a course-scoped page.
     *
     * @param stdClass $course Moodle course
     * @param moodle_url $url Endpoint
     * @param string $title Page title
     */
    public static function page(stdClass $course, moodle_url $url, string $title): void {
        global $PAGE;
        $PAGE->set_context(context_course::instance($course->id));
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title($title);
        $PAGE->set_heading(format_string($course->fullname));
    }

    /**
     * Render authorized links for the current course.
     *
     * @param stdClass $course Moodle course
     * @return array
     */
    public static function nav(stdClass $course): array {
        $context = context_course::instance($course->id);
        $params = ['courseid' => $course->id];
        $canread = has_capability(access::CAP . 'viewregistry', $context);
        return [
            'registryurl' => $canread ? access::url('index', $params)->out(false) : '',
            'templatesurl' => has_capability(access::CAP . 'manage', $context) ? access::url('templates', $params)->out(false) : '',
            'issueurl' => has_capability(access::CAP . 'issue', $context) ? access::url('issue', $params)->out(false) : '',
            'mineurl' => access::url('mine')->out(false),
            'verifyurl' => get_config(access::C, 'publicverification') ? access::url('verify')->out(false) : '',
        ];
    }

    /**
     * Build a restricted representation for an authorized credential list.
     *
     * @param stdClass $issue Credential row
     * @return array
     */
    public static function row(stdClass $issue): array {
        $snapshot = store::snapshot($issue);
        return [
            'id' => $issue->id,
            'title' => $snapshot['design']['title'],
            'recipient' => $snapshot['fields']['recipient'],
            'reference' => $snapshot['fields']['serial'],
            'issuedate' => $snapshot['fields']['issuedate'],
            'status' => access::text('status_' . $issue->status),
            'valid' => $issue->status === 'valid',
            'downloadurl' => access::url('download', ['id' => $issue->id])->out(false),
        ];
    }
}
