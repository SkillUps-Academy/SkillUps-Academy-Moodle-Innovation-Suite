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
 * Moodle File API callback for private Helpdesk attachments.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\attachments;

/**
 * Serve a private post attachment as a download after ticket and note authorization.
 *
 * @param stdClass $course Site course
 * @param stdClass|null $cm Unused module
 * @param context $context Requested context
 * @param string $filearea Requested area
 * @param array $args Post ID and filename
 * @param bool $forcedownload Caller preference
 * @param array $options Moodle response options
 * @return bool
 */
function local_skillupshelpdesk_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');
    require_login();
    if (
        $context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'attachment' || count($args) !== 2 ||
        !ctype_digit((string)$args[0]) || !attachments::visible((int)$args[0])
    ) {
        send_header_404();
        header('Cache-Control: private, no-store');
        exit;
    }
    $file = get_file_storage()->get_file($context->id, access::COMPONENT, $filearea, (int)$args[0], '/', $args[1]);
    if (!$file || $file->is_directory()) {
        send_header_404();
        header('Cache-Control: private, no-store');
        exit;
    }
    send_stored_file($file, 0, 0, true, $options);
    return true;
}
