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
 * Enumerate only static, authored content in Moodle contexts.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sources {
    /** @var array Authored module intro tables verified in Moodle Core. */
    private const INTRO = ['assign', 'book', 'folder', 'label', 'page', 'resource', 'url'];

    /**
     * Stream authorized sources for a specific course.
     *
     * @param stdClass $course Moodle course
     * @return \Generator
     */
    public static function collect(stdClass $course): \Generator {
        global $DB;
        if (trim($course->summary) !== '') {
            yield self::asset('course', $course->fullname, 0, 0, 0, $course->summary, (int)$course->summaryformat);
        }
        $info = get_fast_modinfo($course);
        foreach ($info->get_section_info_all() as $section) {
            if ($section->uservisible && trim($section->summary) !== '') {
                yield self::asset(
                    'section',
                    ' #' . $section->section,
                    0,
                    $section->id,
                    0,
                    $section->summary,
                    $section->summaryformat,
                );
            }
        }
        foreach ($info->get_cms() as $cm) {
            if (!scope::module($cm)) {
                continue;
            }
            $type = $cm->modname;
            if (!in_array($type, self::INTRO, true)) {
                yield self::asset('unsupported', $cm->name, $cm->id);
                continue;
            }
            $fields = $type === 'page' ? 'id,intro,introformat,content,contentformat' : 'id,intro,introformat';
            $content = $DB->get_record($type, ['id' => $cm->instance], $fields);
            if (!$content) {
                continue;
            }
            if (trim($content->intro) !== '') {
                yield self::asset('intro', $cm->name, $cm->id, 0, 0, $content->intro, $content->introformat);
            }
            if ($type === 'page' && trim($content->content) !== '') {
                yield self::asset('page', $cm->name, $cm->id, 0, 0, $content->content, $content->contentformat);
            } else if ($type === 'book') {
                $fields = 'id,title,content,contentformat,hidden';
                $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance], 'pagenum', $fields);
                foreach ($chapters as $chapter) {
                    if (!$chapter->hidden || has_capability('mod/book:viewhiddenchapters', $cm->context)) {
                        yield self::asset(
                            'chapter',
                            $chapter->title,
                            $cm->id,
                            0,
                            $chapter->id,
                            $chapter->content,
                            $chapter->contentformat,
                        );
                    }
                }
            } else if ($type === 'resource' || $type === 'folder') {
                yield from self::files($cm);
            } else if (in_array($type, ['url', 'assign'], true)) {
                // Their external destination or dynamic interface is outside the authored intro.
                yield self::asset('unsupported', $cm->name, $cm->id);
            }
        }
    }

    /**
     * Use the native File Browser to authorize authored Resource and Folder files.
     *
     * @param \cm_info $cm Moodle activity
     * @return \Generator
     */
    private static function files(\cm_info $cm): \Generator {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $area = 'content';
        $component = 'mod_' . $cm->modname;
        $parent = get_file_browser()->get_file_info($cm->context, $component, $area, 0, '/', '.');
        if (!$parent) {
            yield self::asset('unsupported', $cm->name, $cm->id);
            return;
        }
        $folders = [[$parent, 0]];
        while ($folders) {
            [$folder, $depth] = array_pop($folders);
            foreach ($folder->get_children() as $entry) {
                if ($entry->is_directory()) {
                    if ($depth < 12) {
                        $folders[] = [$entry, $depth + 1];
                    } else {
                        yield self::asset('unsupported', $cm->name, $cm->id, 0, 1);
                    }
                    continue;
                }
                $args = $entry->get_params();
                if ($args['contextid'] != $cm->context->id || $args['component'] !== $component || $args['filearea'] !== $area) {
                    continue;
                }
                $stored = get_file_storage()->get_file(
                    $cm->context->id,
                    $component,
                    $area,
                    0,
                    $args['filepath'],
                    $args['filename'],
                );
                if (!$stored) {
                    continue;
                }
                $data = self::asset('file', $args['filename'], $cm->id);
                $data['sourcekey'] = hash('sha256', $cm->id . ':' . $args['filepath'] . $args['filename']);
                $data['file'] = $stored;
                yield $data;
            }
        }
    }

    /**
     * Describe a source without persisting its body.
     *
     * @param string $kind Source kind
     * @param string $label Author-visible display name
     * @param int $cmid Moodle activity ID
     * @param int $sectionid Moodle section ID
     * @param int $refid Optional adapter reference
     * @param string $body Authored content
     * @param int $format Moodle content format
     * @return array
     */
    private static function asset(
        string $kind,
        string $label,
        int $cmid = 0,
        int $sectionid = 0,
        int $refid = 0,
        string $body = '',
        int $format = FORMAT_HTML,
    ): array {
        return [
            'kind' => $kind, 'label' => \core_text::substr(strip_tags($label), 0, 255),
            'cmid' => $cmid, 'sectionid' => $sectionid, 'refid' => $refid,
            'sourcekey' => hash('sha256', "$kind:$cmid:$sectionid:$refid"),
            'body' => $body, 'format' => $format,
        ];
    }

    /**
     * Return the content fingerprint for this ruleset.
     *
     * @param array $source Authorized source
     * @return string
     */
    public static function digest(array $source): string {
        $file = $source['file'] ?? null;
        if ($file && $file->is_external_file()) {
            return hash('sha256', rules::VERSION . ':external-file');
        }
        $value = $file ? $file->get_contenthash() . ':' . $file->get_mimetype() . ':' . $file->get_filename() :
            $source['kind'] . ':' . $source['format'] . ':' . $source['body'];
        return hash('sha256', rules::VERSION . ':' . $value);
    }

    /**
     * Run the applicable rules for one source.
     *
     * @param array $source Authorized source
     * @return array
     */
    public static function analyze(array $source): array {
        if ($source['kind'] === 'unsupported') {
            return rules::result(false, 'unsupported');
        }
        if (isset($source['file'])) {
            return documents::scan($source['file']);
        }
        if ($source['format'] == FORMAT_HTML) {
            return rules::html($source['body']);
        }
        return rules::result(false, 'formatunsupported');
    }
}
