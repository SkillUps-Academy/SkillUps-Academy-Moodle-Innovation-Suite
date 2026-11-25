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
 * Validate library uploads and resolve Moodle File API objects.
 *
 * @package    local_skillupsrepository
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class files {
    /** @var string Versioned file area. */
    public const AREA = 'payload';
    /** @var array File families accepted by the uploader. */
    public const TYPES = [
        'pdf' => 'document', 'docx' => 'document', 'pptx' => 'document', 'xlsx' => 'document',
        'png' => 'image', 'gif' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'webp' => 'image',
        'mp3' => 'audio', 'ogg' => 'audio', 'wav' => 'audio',
        'mp4' => 'video', 'webm' => 'video',
        'zip' => 'package', 'imscc' => 'package', 'h5p' => 'package',
    ];
    /** @var array Browser MIME types checked independently of the upload name. */
    private const MIMES = [
        'pdf' => ['application/pdf'], 'png' => ['image/png'], 'gif' => ['image/gif'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp'],
        'mp3' => ['audio/mpeg'], 'ogg' => ['audio/ogg', 'application/ogg'], 'wav' => ['audio/wav', 'audio/x-wav'],
        'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
    ];

    /**
     * Effective size limit shared by the form and its submission handler.
     *
     * @return int
     */
    public static function limit(): int {
        global $CFG;
        $configured = (int)get_config(access::COMPONENT, 'maxbytes');
        return get_max_upload_file_size($CFG->maxbytes, 0, $configured > 0 ? $configured : 52428800);
    }

    /**
     * Resolve a submitted draft only from the current user's native draft context.
     *
     * @param int $draftid Draft item ID
     * @return \stored_file|null
     */
    public static function draft(int $draftid): ?\stored_file {
        global $USER;
        $context = \context_user::instance($USER->id);
        $items = get_file_storage()->get_area_files($context->id, 'user', 'draft', $draftid, 'id ASC', false);
        if (!$items) {
            return null;
        }
        $file = reset($items);
        if (
            count($items) !== 1 || $file->get_filepath() !== '/' || $file->is_external_file() ||
            $file->get_filesize() < 1 || (self::limit() && $file->get_filesize() > self::limit())
        ) {
            access::fail('badfile');
        }
        return $file;
    }

    /**
     * Verify MIME signatures and supported package layout. Package entries are never extracted.
     *
     * @param \stored_file $file Valid draft file
     * @return array File family and verified MIME
     */
    public static function validate(\stored_file $file): array {
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $kind = self::TYPES[$extension] ?? null;
        if (!$kind) {
            access::fail('badfile');
        }
        if (isset(self::MIMES[$extension])) {
            $handle = $file->get_content_file_handle();
            try {
                $magic = (new \finfo(FILEINFO_MIME_TYPE))->buffer(fread($handle, 8192));
            } finally {
                fclose($handle);
            }
            if (!in_array($magic, self::MIMES[$extension], true)) {
                access::fail('badfile');
            }
            return [$kind, $magic === 'application/ogg' ? 'audio/ogg' : $magic];
        }

        $manifest = match ($extension) {
            'docx' => 'word/document.xml',
            'pptx' => 'ppt/presentation.xml',
            'xlsx' => 'xl/workbook.xml',
            'h5p' => 'h5p.json',
            default => 'imsmanifest.xml',
        };
        $scratch = $file->copy_content_to_temp('skillupsrepository', 'artifact_');
        try {
            $zip = new \ZipArchive();
            if ($zip->open($scratch, \ZipArchive::RDONLY) !== true) {
                access::fail('badfile');
            }
            try {
                if (
                    !$zip->statName($manifest) ||
                    ($kind === 'document' &&
                    (!$zip->statName('[Content_Types].xml') ||
                    $zip->statName('word/vbaProject.bin') ||
                        $zip->statName('ppt/vbaProject.bin') ||
                        $zip->statName('xl/vbaProject.bin')))
                ) {
                    access::fail('badfile');
                }
            } finally {
                $zip->close();
            }
        } finally {
            unlink($scratch);
        }
        return [$kind, $file->get_mimetype()];
    }

    /**
     * Return the file of a known stored revision.
     *
     * @param \stdClass $asset Resource
     * @param \stdClass $revision Revision
     * @return \stored_file|false
     */
    public static function stored(\stdClass $asset, \stdClass $revision): \stored_file|false {
        if (!$revision->filename || $revision->assetid != $asset->id) {
            return false;
        }
        $context = \context_coursecat::instance($asset->categoryid, IGNORE_MISSING);
        return $context ? get_file_storage()->get_file(
            $context->id,
            access::COMPONENT,
            self::AREA,
            $revision->id,
            '/',
            $revision->filename
        ) : false;
    }

    /**
     * Decide whether the resource can be rendered as a native browser media element.
     *
     * @param \stdClass $version Revision
     * @return bool
     */
    public static function previewable(\stdClass $version): bool {
        return in_array($version->kind, ['image', 'audio', 'video'], true);
    }
}
