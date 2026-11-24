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
 * Connect site navigation and protected files to Moodle.
 *
 * @package    local_skillupscompetency
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add SkillUps Competency Manager to Moodle navigation.
 *
 * @param global_navigation $navigation Moodle tree
 */
function local_skillupscompetency_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser() || !\core_competency\api::is_enabled()) {
        return;
    }
    $navigation->add(
        \local_skillupscompetency\access::text('pluginname'),
        \local_skillupscompetency\access::url(),
        navigation_node::TYPE_CUSTOM,
    );
    global $COURSE;
    if ($COURSE->id != SITEID) {
        $node = $navigation->find($COURSE->id, navigation_node::TYPE_COURSE);
        if ($node) {
            $node->add(
                \local_skillupscompetency\access::text('pluginname'),
                \local_skillupscompetency\access::url('course', ['id' => $COURSE->id]),
            );
        }
    }
}

/**
 * Deliver evidence only after checking course, activity, learner and assessment visibility.
 *
 * @param stdClass $course Moodle course object
 * @param stdClass|null $cm Course module
 * @param context $context Moodle context
 * @param string $filearea Evidence area
 * @param array $args Assessment ID and filename
 * @param bool $forcedownload Moodle download option
 * @param array $options Additional delivery options
 * @return bool
 */
function local_skillupscompetency_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    global $DB;
    if (
        $context->contextlevel !== CONTEXT_COURSE || $filearea !== \local_skillupscompetency\assessment::AREA ||
        count($args) !== 2 || !ctype_digit((string)$args[0])
    ) {
        return false;
    }
    require_login($course);
    $record = $DB->get_record(\local_skillupscompetency\store::ASSESS, ['id' => (int)$args[0], 'courseid' => $context->instanceid]);
    if (!$record || !\local_skillupscompetency\access::read_assessment($record)) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, 'local_skillupscompetency', $filearea, $record->id, '/', $args[1]);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, ['cacheability' => 'private'] + $options);
    return true;
}
