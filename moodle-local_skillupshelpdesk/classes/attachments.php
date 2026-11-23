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

namespace local_skillupshelpdesk;

use context_system;
use context_user;
use moodle_exception;
use moodle_url;

/**
 * Private attachment validation, persistence and download links.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attachments {
    /** @var array Strict extension-to-MIME map. */
    private const MIME = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'txt' => ['text/plain', 'application/x-empty'],
    ];

    /**
     * Return the same constraints for the form and the persistent file operation.
     *
     * @return array
     */
    public static function options(): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/repository/lib.php');
        return [
            'maxfiles' => 3,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes, 0, 5 * 1024 * 1024),
            'areamaxbytes' => 12 * 1024 * 1024,
            'subdirs' => 0,
            'accepted_types' => array_map(static fn(string $extension): string => '.' . $extension, array_keys(self::MIME)),
            'return_types' => FILE_INTERNAL,
        ];
    }

    /**
     * Verify Moodle's current user draft against the component limits.
     *
     * @param int $draftid Draft area id
     * @return bool
     */
    public static function valid(int $draftid): bool {
        return self::validated_files($draftid) !== null;
    }

    /**
     * Return the exact immutable files that passed draft validation.
     *
     * @param int $draftid Draft item ID
     * @return array|null Validated stored files, or null for a rejected draft
     */
    private static function validated_files(int $draftid): ?array {
        global $USER;
        if (!$draftid) {
            return [];
        }
        $options = self::options();
        $files = get_file_storage()->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id', false);
        if (count($files) > $options['maxfiles']) {
            return null;
        }
        $total = 0;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $total += $file->get_filesize();
            if (
                $file->is_external_file() || $file->get_filepath() !== '/' || !isset(self::MIME[$extension]) ||
                $file->get_filesize() > $options['maxbytes'] || $total > $options['areamaxbytes']
            ) {
                return null;
            }
            if (!in_array($finfo->buffer($file->get_content()), self::MIME[$extension], true)) {
                return null;
            }
        }
        return $files;
    }

    /**
     * Copy allowed draft attachments into the message's private area.
     *
     * @param int $draftid Draft item ID
     * @param int $postid Message ID
     */
    public static function save(int $draftid, int $postid): void {
        $files = self::validated_files($draftid);
        if ($files === null) {
            throw new moodle_exception('invalidattachment', access::COMPONENT);
        }
        $storage = get_file_storage();
        $context = context_system::instance();
        foreach ($files as $file) {
            $storage->create_file_from_storedfile([
                'contextid' => $context->id,
                'component' => access::COMPONENT,
                'filearea' => 'attachment',
                'itemid' => $postid,
                'filepath' => '/',
                'filename' => $file->get_filename(),
            ], $file);
        }
    }

    /**
     * Build download links for an already-authorized message.
     *
     * @param int $postid Message ID
     * @return array
     */
    public static function links(int $postid): array {
        $context = context_system::instance();
        $files = get_file_storage()->get_area_files($context->id, access::COMPONENT, 'attachment', $postid, 'filename', false);
        $links = [];
        foreach ($files as $file) {
            $links[] = [
                'name' => $file->get_filename(),
                'url' => moodle_url::make_pluginfile_url(
                    $context->id,
                    access::COMPONENT,
                    'attachment',
                    $postid,
                    '/',
                    $file->get_filename(),
                    true
                )->out(false),
            ];
        }
        return $links;
    }

    /**
     * Authorize a message and its files independently of any UI.
     *
     * @param int $postid Message ID
     * @return bool
     */
    public static function visible(int $postid): bool {
        global $DB;
        $post = $DB->get_record(storage::POST, ['id' => $postid], 'id,ticketid,internal');
        $ticket = $post ? $DB->get_record(storage::TICKET, ['id' => $post->ticketid]) : null;
        return $ticket && access::ticket($ticket) && (!$post->internal || access::staff($ticket));
    }
}
