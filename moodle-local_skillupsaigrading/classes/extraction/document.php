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

namespace local_skillupsaigrading\extraction;

use local_skillupsaigrading\access;

/**
 * Bounded text-only extraction. No macros, network, document execution or rendered HTML.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class document {
    /**
     * Maximum compressed input size.
     */
    public const MAX_BYTES = 8 * 1024 * 1024;
    /**
     * Maximum retained extracted content, in Unicode characters.
     */
    public const MAX_TEXT = 80000;
    /**
     * Formats supported by the importer.
     */
    public const EXTENSIONS = ['txt', 'pdf', 'docx', 'pptx'];

    /**
     * Validate the draft file before allowing it into temporary storage.
     */
    public static function extension(\stored_file $file): string {
        $type = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (
            !$file->get_filesize() || $file->get_filesize() > self::MAX_BYTES ||
                !in_array($type, self::EXTENSIONS, true) || $file->is_directory() || $file->is_external_file()
        ) {
            access::fail('invaliddocument');
        }
        return $type;
    }

    /**
     * Extract bounded text and remove all private scratch files immediately.
     */
    public static function extract(\stored_file $file): string {
        $type = self::extension($file);
        if ($type === 'txt') {
            return self::normalize($file->get_content());
        }
        $directory = make_request_directory();
        $in = $directory . '/source.' . $type;
        $out = $directory . '/output.txt';
        $err = $directory . '/parser.log';
        try {
            if (!$file->copy_content_to($in)) {
                access::fail('invaliddocument');
            }
            $text = $type === 'pdf' ? self::pdf($in, $out, $err) : self::office($in, $type);
            return self::normalize($text);
        } finally {
            foreach ([$in, $out, $err] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Validate encoding, remove control characters and limit the retained text.
     */
    private static function normalize(string $text): string {
        if (!mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")) {
            access::fail('invaliddocument');
        }
        $text = str_replace(["\r\n", "\r", "\f"], "\n", $text);
        $text = preg_replace('/[\x01-\x08\x0b\x0e-\x1f\x7f\x{fffe}\x{ffff}\x{feff}]/u', '', $text);
        $text = trim($text);
        if ($text === '') {
            access::fail('emptydocument');
        }
        return \core_text::substr($text, 0, self::MAX_TEXT);
    }

    /**
     * Read only expected OOXML parts from a validated central directory.
     */
    private static function office(string $file, string $type): string {
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::RDONLY) !== true) {
            access::fail('invaliddocument');
        }
        try {
            if (!$zip->numFiles || $zip->numFiles > 1000 || $zip->locateName('[Content_Types].xml') === false) {
                access::fail('invaliddocument');
            }
            $selected = [];
            $seen = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'] ?? '';
                if (
                    $name === '' || isset($seen[$name]) || preg_match('~(?:^/|\\\\|:|(?:^|/)\.\.(?:/|$))~', $name) ||
                        str_ends_with(strtolower($name), 'vbaproject.bin') || ($entry['encryption_method'] ?? 0) !== 0
                ) {
                    access::fail('invaliddocument');
                }
                $seen[$name] = true;
                $size = $entry['size'];
                $total += $size;
                if (
                    $total > 32 * 1024 * 1024 || $size > 8 * 1024 * 1024 ||
                        ($size > 1024 * 1024 && $size > 250 * max(1, $entry['comp_size']))
                ) {
                    access::fail('invaliddocument');
                }
                if (
                    ($type === 'docx' && $name === 'word/document.xml') ||
                        ($type === 'pptx' && preg_match('~\Appt/slides/slide[0-9]+\.xml\z~D', $name))
                ) {
                    if ($size > 1024 * 1024) {
                        access::fail('invaliddocument');
                    }
                    $selected[$name] = $i;
                }
            }
            if (!$selected || count($selected) > 80) {
                access::fail('invaliddocument');
            }
            ksort($selected, SORT_NATURAL);
            $result = '';
            foreach ($selected as $index) {
                $xml = $zip->getFromIndex($index, 1024 * 1024 + 1);
                if ($xml === false || strlen($xml) > 1024 * 1024) {
                    access::fail('invaliddocument');
                }
                $result .= self::paragraphs($xml, $type) . "\n";
                if (strlen($result) > 4 * self::MAX_TEXT) {
                    break;
                }
            }
            return $result;
        } finally {
            $zip->close();
        }
    }

    /**
     * Read only paragraph/run text from transitional or strict Office XML.
     */
    private static function paragraphs(string $xml, string $type): string {
        if (!mb_check_encoding($xml, 'UTF-8') || preg_match('/<!\s*(DOCTYPE|ENTITY)|\x00/i', $xml)) {
            access::fail('invaliddocument');
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT) || $dom->doctype) {
            access::fail('invaliddocument');
        }
        $namespaces = $type === 'docx' ?
            ['http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'http://purl.oclc.org/ooxml/wordprocessingml/main'] :
            ['http://schemas.openxmlformats.org/drawingml/2006/main', 'http://purl.oclc.org/ooxml/drawingml/main'];
        $lines = [];
        foreach ($dom->getElementsByTagNameNS('*', 'p') as $p) {
            if (!in_array($p->namespaceURI, $namespaces, true)) {
                continue;
            }
            $line = '';
            foreach ($p->getElementsByTagNameNS('*', 't') as $node) {
                if (in_array($node->namespaceURI, $namespaces, true)) {
                    $line .= $node->textContent;
                }
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Invoke only the deployment-configured Poppler binary with fixed arguments and no shell.
     */
    private static function pdf(string $in, string $out, string $err): string {
        global $CFG;
        $configured = $CFG->local_skillupsaigrading_pdftotext ?? '';
        $binary = $configured === '' ? false : realpath($configured);
        if (
            !$binary || !is_file($binary) || !is_executable($binary) ||
                !in_array(strtolower(basename($binary)), ['pdftotext', 'pdftotext.exe'], true) ||
                !function_exists('proc_open')
        ) {
            access::fail('pdfunavailable');
        }
        $input = fopen($in, 'rb');
        if (!$input) {
            access::fail('invaliddocument');
        }
        try {
            if (!str_starts_with(fread($input, 8), '%PDF-')) {
                access::fail('invaliddocument');
            }
        } finally {
            fclose($input);
        }
        $pipes = [];
        $process = proc_open(
            [$binary, '-f', '1', '-l', '100', '-enc', 'UTF-8', $in, '-'],
            [0 => ['pipe', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']],
            $pipes,
            dirname($in),
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            access::fail('invaliddocument');
        }
        fclose($pipes[0]);
        $exit = -1;
        $deadline = microtime(true) + 12;
        try {
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exit = $status['exitcode'];
                    break;
                }
                clearstatcache();
                if (microtime(true) >= $deadline || filesize($out) > 1024 * 1024 || filesize($err) > 262144) {
                    proc_terminate($process, 9);
                    access::fail('invaliddocument');
                }
                usleep(40000);
            } while (true);
        } finally {
            proc_close($process);
        }
        clearstatcache();
        if ($exit !== 0 || filesize($out) > 1024 * 1024 || filesize($err) > 262144) {
            access::fail('invaliddocument');
        }
        return (string)file_get_contents($out);
    }
}
