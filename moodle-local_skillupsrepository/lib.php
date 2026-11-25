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

/**
 * Serve authorized library version files through Moodle File API.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve an exact version and enforce its read or download policy.
 *
 * @param stdClass $course Course or site record supplied by Moodle
 * @param stdClass|null $cm Activity record if present
 * @param context $context Owning context
 * @param string $filearea File API area
 * @param array $args Revision and filename
 * @param bool $forcedownload Request for an attachment
 * @param array $options File serving options supplied by Moodle
 * @return bool
 */
function local_skillupsrepository_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    global $DB;
    if (
        $context->contextlevel !== CONTEXT_COURSECAT || $filearea !== \local_skillupsrepository\files::AREA ||
        count($args) !== 2 || !ctype_digit((string)$args[0])
    ) {
        return false;
    }
    $revision = $DB->get_record(\local_skillupsrepository\store::REVISION, ['id' => (int)$args[0]]);
    $asset = $revision ? $DB->get_record(\local_skillupsrepository\store::ASSET, ['id' => $revision->assetid]) : null;
    if (!$asset || $asset->categoryid != $context->instanceid || $revision->filename !== $args[1]) {
        return false;
    }
    $download = $forcedownload || !\local_skillupsrepository\files::previewable($revision);
    $allowed = $download ? \local_skillupsrepository\access::download($asset, $revision) :
        \local_skillupsrepository\access::view($asset, $revision);
    $file = $allowed ? \local_skillupsrepository\files::stored($asset, $revision) : false;
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, $download, ['cacheability' => 'private']);
    return false;
}

/**
 * Add a library entry in the Moodle course navigation.
 *
 * @param navigation_node $navigation Course navigation
 * @param stdClass $course Moodle course
 * @param context_course $context Course context
 */
function local_skillupsrepository_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (\local_skillupsrepository\access::can('view', (int)$course->category)) {
        $navigation->add(
            get_string('pluginname', 'local_skillupsrepository'),
            new moodle_url('/local/skillupsrepository/index.php', ['categoryid' => $course->category])
        );
    }
}
