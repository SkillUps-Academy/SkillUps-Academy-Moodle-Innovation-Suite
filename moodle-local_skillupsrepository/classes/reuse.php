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

namespace local_skillupsrepository;

/**
 * Create native Moodle File and URL activities from approved library revisions.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reuse {
    /**
     * Check current course editing authority without disclosing other courses.
     *
     * @param \stdClass $course Destination
     * @param string $mod Module name
     * @return bool
     */
    public static function destination(\stdClass $course, string $mod): bool {
        $context = \context_course::instance($course->id);
        return $course->id != SITEID && ($course->visible || has_capability('moodle/course:viewhiddencourses', $context)) &&
            (is_enrolled($context, null, '', true) || has_capability('moodle/course:view', $context)) &&
            has_capability('moodle/course:manageactivities', $context) &&
            has_capability('mod/' . $mod . ':addinstance', $context) &&
            course_allowed_module($course, $mod);
    }

    /**
     * Copy an immutable published version to an existing native course section.
     *
     * @param int $assetid Asset ID
     * @param int $versionid Expected approved version
     * @param int $courseid Destination course
     * @param int $sectionid Existing section ID
     * @param string $requestkey Idempotence identifier
     * @return int Native course module ID
     */
    public static function insert(int $assetid, int $versionid, int $courseid, int $sectionid, string $requestkey): int {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/resourcelib.php');
        $categoryid = (int)store::get(store::ASSET, $assetid)->categoryid;
        $callback = static function () use (
            $DB,
            $USER,
            $assetid,
            $versionid,
            $courseid,
            $sectionid,
            $requestkey
        ): int {
            $asset = store::get(store::ASSET, $assetid);
            $version = store::get(store::REVISION, $versionid);
            if (!access::reuse($asset, $version) || !preg_match('/^[a-f0-9]{32}$/D', $requestkey)) {
                access::fail();
            }
            $course = get_course($courseid);
            $mod = $version->kind === 'link' ? 'url' : 'resource';
            $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid]);
            $coursecontext = \context_course::instance($courseid);
            if (
                !$section || (!($section->visible) && !has_capability('moodle/course:viewhiddensections', $coursecontext)) ||
                !self::destination($course, $mod)
            ) {
                access::fail();
            }
            $previous = $DB->get_record(store::USAGE, ['requestkey' => $requestkey]);
            if ($previous) {
                if (
                    $previous->assetid != $assetid || $previous->revisionid != $versionid || $previous->courseid != $courseid ||
                    $previous->sectionid != $sectionid || $previous->userid != $USER->id || $previous->removed
                ) {
                    access::fail('stale');
                }
                return (int)$previous->cmid;
            }
            [$module] = can_add_moduleinfo($course, $mod, (int)$section->section);
            $credit = get_string('coursecredit', access::COMPONENT, (object)[
                'credit' => $version->credit ?: '–', 'license' => get_string('license_' . $version->license, access::COMPONENT),
                'number' => $version->number,
            ]);
            $data = (object)[
                'modulename' => $mod, 'module' => $module->id, 'section' => $section->section, 'name' => $version->title,
                'visible' => 0, 'visibleoncoursepage' => 1, 'display' => RESOURCELIB_DISPLAY_DOWNLOAD,
                'intro' => s($credit), 'introformat' => FORMAT_HTML, 'showdescription' => 0,
            ];
            $draftid = 0;
            if ($mod === 'url') {
                $data->display = RESOURCELIB_DISPLAY_OPEN;
                $data->externalurl = $version->sourceurl;
            } else {
                $file = files::stored($asset, $version) ?: access::fail();
                $draftid = file_get_unused_draft_itemid();
                $data->files = $draftid;
                get_file_storage()->create_file_from_storedfile([
                    'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
                    'itemid' => $draftid, 'filepath' => '/', 'filename' => $file->get_filename(),
                ], $file);
            }
            try {
                $created = add_moduleinfo($data, $course);
            } finally {
                if ($draftid) {
                    get_file_storage()->delete_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $draftid);
                }
            }
            $DB->insert_record(store::USAGE, (object)[
                'assetid' => $assetid, 'revisionid' => $versionid, 'courseid' => $courseid, 'sectionid' => $sectionid,
                'cmid' => $created->coursemodule, 'userid' => $USER->id, 'created' => time(), 'requestkey' => $requestkey,
            ]);
            $DB->set_field(store::ASSET, 'uses', $asset->uses + 1, ['id' => $assetid]);
            store::audit($assetid, $versionid, 'copied');
            return (int)$created->coursemodule;
        };
        return store::write($categoryid, $callback);
    }
}
