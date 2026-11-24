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

use DOMDocument;
use DOMXPath;
use stored_file;
use ZipArchive;

/**
 * Read limited document signals without extracting archives or contacting external services.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class documents {
    /** @var int Maximum bytes downloaded from Moodle file storage. */
    public const LIMIT = 5242880;

    /**
     * Check a supported MIME/extension pair and bounded file content.
     *
     * @param stored_file $file Authorized local file
     * @return array
     */
    public static function scan(stored_file $file): array {
        if ($file->is_external_file()) {
            return rules::result(false, 'externalfile');
        }
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $mime = $file->get_mimetype();
        $types = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'html' => 'text/html',
            'htm' => 'text/html',
        ];
        if (!isset($types[$extension]) || $mime !== $types[$extension]) {
            return rules::result(false, 'unsupported');
        }
        if ($file->get_filesize() > self::LIMIT) {
            return rules::result(false, 'oversize');
        }
        if ($extension === 'pdf') {
            return self::pdf($file->get_content());
        }
        if ($extension !== 'docx') {
            return rules::html($file->get_content());
        }
        $target = make_request_directory() . '/document.docx';
        $file->copy_content_to($target);
        try {
            return self::docx($target);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    /**
     * Identify PDF layout metadata and leave semantic inspection to a reviewer.
     *
     * @param string $data PDF bytes
     * @return array
     */
    public static function pdf(string $data): array {
        if (!str_starts_with($data, '%PDF-')) {
            return rules::result(false, 'unreadable');
        }
        $state = preg_match('~/MarkInfo\s*<<[^>]{0,600}/Marked\s+false\b~s', $data) ? 'markedfalse' :
            (preg_match('~/StructTreeRoot\b~', $data) ? 'structureseen' : 'structureunknown');
        return rules::result(false, 'pdfmanual', [rules::issue('pdfstructure', 'PDF', $state)]);
    }

    /**
     * Inspect a single bounded Word XML entry without extracting the rest of the ZIP.
     *
     * @param string $path Local authorized package
     * @return array
     */
    public static function docx(string $path): array {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return rules::result(false, 'unreadable');
        }
        try {
            $file = $zip->statName('word/document.xml');
            if (!$file || $file['size'] > 2097152) {
                return rules::result(false, $file ? 'oversize' : 'unreadable');
            }
            $xml = $zip->getFromName('word/document.xml', 2097152);
        } finally {
            $zip->close();
        }
        $doc = new DOMDocument();
        if (
            !$xml || stripos($xml, '<!DOCTYPE') !== false ||
            !$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)
        ) {
            return rules::result(false, 'unreadable');
        }
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xp->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
        if ($xp->query('/w:document/w:body')->length !== 1) {
            return rules::result(false, 'unreadable');
        }
        $issues = [];
        foreach ($xp->query('//wp:docPr') as $idx => $node) {
            $decorative = $xp->query('.//*[local-name()="decorative" and (@val="1" or @val="true")]', $node)->length > 0;
            if (!$decorative && trim($node->getAttribute('descr') . $node->getAttribute('title')) === '') {
                $issues[] = rules::issue('docxalt', 'drawing[' . ($idx + 1) . ']');
            }
        }
        $previous = 0;
        foreach ($xp->query('//w:pPr/w:pStyle/@w:val') as $idx => $attr) {
            if (preg_match('/^Heading([1-9])$/D', $attr->nodeValue, $match)) {
                $next = (int)$match[1];
                if ($previous && $next > $previous + 1) {
                    $issues[] = rules::issue('docxheading', 'paragraph-style[' . ($idx + 1) . ']', "$previous → $next");
                }
                $previous = $next;
            }
        }
        return rules::result(true, '', $issues);
    }
}
