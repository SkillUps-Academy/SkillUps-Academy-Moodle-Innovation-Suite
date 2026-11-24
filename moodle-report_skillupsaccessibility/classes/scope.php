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

namespace report_skillupsaccessibility;

use stdClass;

/**
 * Apply current Moodle course, section, module and file permissions.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /** @var string Report component. */
    public const C = 'report_skillupsaccessibility';
    /** @var string Capability namespace. */
    public const CAP = 'report/skillupsaccessibility:';

    /** @var array Core activity capabilities for the static author-content adapters. */
    public const VIEW = [
        'page' => 'mod/page:view',
        'book' => 'mod/book:read',
        'resource' => 'mod/resource:view',
        'folder' => 'mod/folder:view',
        'url' => 'mod/url:view',
        'assign' => 'mod/assign:view',
    ];

    /**
     * Resolve a report label.
     *
     * @param string $key Language key
     * @param mixed $value Optional string interpolation data
     * @return string
     */
    public static function text(string $key, mixed $value = null): string {
        return get_string($key, self::C, $value);
    }

    /**
     * Raise a localized report error.
     *
     * @param string $key Language key
     */
    public static function fail(string $key = 'notavailable'): never {
        throw new \moodle_exception($key, self::C);
    }

    /**
     * Build a report URL.
     *
     * @param string $page Report endpoint
     * @param array $params Page parameters
     * @return \moodle_url
     */
    public static function url(string $page = 'index', array $params = []): \moodle_url {
        return new \moodle_url('/report/skillupsaccessibility/' . $page . '.php', $params);
    }

    /**
     * Check active course access and author permissions.
     *
     * @param stdClass $course Moodle course
     * @param string $action Capability suffix
     * @param int $userid Moodle user ID or zero for the active account
     * @return bool
     */
    public static function allowed(stdClass $course, string $action = 'view', int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int)$USER->id;
        if ($course->id == SITEID || $userid <= 0) {
            return false;
        }
        $context = \context_course::instance($course->id);
        return has_capability(self::CAP . 'view', $context, $userid) &&
            has_capability(self::CAP . $action, $context, $userid) &&
            has_capability('moodle/course:manageactivities', $context, $userid) &&
            ($course->visible || has_capability('moodle/course:viewhiddencourses', $context, $userid)) &&
            (is_enrolled($context, $userid, '', true) || has_capability('moodle/course:view', $context, $userid));
    }

    /**
     * Check current native activity visibility and the matching module permission.
     *
     * @param \cm_info $cm Native Moodle activity
     * @param int $userid Moodle user ID or zero for the active account
     * @return bool
     */
    public static function module(\cm_info $cm, int $userid = 0): bool {
        if (!$cm->uservisible || $cm->deletioninprogress) {
            return false;
        }
        if (isset(self::VIEW[$cm->modname])) {
            return has_capability(self::VIEW[$cm->modname], $cm->context, $userid ?: null);
        }
        return true;
    }

    /**
     * Revalidate the current scope for one stored asset.
     *
     * @param stdClass $course Moodle course
     * @param stdClass $asset Stored author source
     * @param int $userid Moodle user ID or zero for the active account
     * @return bool
     */
    public static function asset(stdClass $course, stdClass $asset, int $userid = 0): bool {
        global $DB;
        if ($asset->courseid != $course->id || !self::allowed($course, 'view', $userid)) {
            return false;
        }
        $info = get_fast_modinfo($course, $userid);
        if ($asset->sectionid) {
            foreach ($info->get_section_info_all() as $section) {
                if ($section->id == $asset->sectionid) {
                    return $section->uservisible;
                }
            }
            return false;
        }
        if (!$asset->cmid) {
            return true;
        }
        $cm = $info->get_cms()[$asset->cmid] ?? null;
        if (!$cm || !self::module($cm, $userid)) {
            return false;
        }
        if ($asset->kind === 'chapter') {
            $chapter = $DB->get_record('book_chapters', ['id' => $asset->refid, 'bookid' => $cm->instance], 'id,hidden');
            return $chapter && (!$chapter->hidden || has_capability('mod/book:viewhiddenchapters', $cm->context, $userid ?: null));
        }
        return $asset->kind !== 'file' || has_capability('moodle/course:managefiles', $cm->context, $userid ?: null);
    }

    /**
     * Resolve bulk scope through native course category APIs.
     *
     * @param int $categoryid Categoryid
     * @param string $action Capability suffix
     * @return \Generator
     */
    public static function courses(int $categoryid, string $action = 'view'): \Generator {
        global $DB;
        $context = $categoryid ? \context_coursecat::instance($categoryid) : \context_system::instance();
        require_capability(self::CAP . 'bulk', $context);
        if ($categoryid) {
            $category = \core_course_category::get($categoryid, MUST_EXIST, true);
            foreach ($category->get_courses(['recursive' => true, 'sort' => ['id' => SORT_ASC]]) as $item) {
                $course = get_course($item->id);
                if (self::allowed($course, $action)) {
                    yield $course;
                }
            }
            return;
        }
        $recordset = $DB->get_recordset_select('course', 'id <> :site', ['site' => SITEID], 'id');
        try {
            foreach ($recordset as $course) {
                if (self::allowed($course, $action)) {
                    yield $course;
                }
            }
        } finally {
            $recordset->close();
        }
    }

    /**
     * Set up a report page after login.
     *
     * @param string $page Report endpoint
     * @param \context $context Authorized Moodle context
     * @param array $params Page parameters
     */
    public static function setup(string $page, \context $context, array $params = []): void {
        global $PAGE, $SITE;
        $PAGE->set_context($context);
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('report');
        $PAGE->set_show_course_index(false);
        $PAGE->set_title(self::text('pluginname'));
        $PAGE->set_heading(format_string($SITE->fullname));
        $PAGE->set_cacheable(false);
    }
}
