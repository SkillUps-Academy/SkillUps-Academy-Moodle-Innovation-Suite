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

namespace tool_skillupsenrollment;

use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Read bounded CSV and XLSX files without evaluating formulas.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer {
    /** Upload size accepted by both validators. */
    public const MAXBYTES = 2097152;
    /** Stable import schema. */
    public const COLUMNS = ['action', 'userid', 'username', 'courseid', 'shortname', 'instanceid', 'method',
        'role', 'groupid', 'factory_job', 'factory_row'];

    /**
     * Read the whole validated table into the bounded preview.
     */
    public static function read(string $path, string $extension): array {
        if (!is_file($path) || filesize($path) > self::MAXBYTES) {
            throw new \moodle_exception('invalidfile', support::C);
        }
        $iterator = match ($extension) {
            'csv' => self::csv($path),
            'xlsx' => self::xlsx($path),
            default => throw new \moodle_exception('invalidfile', support::C),
        };
        $header = null;
        $rows = [];
        $line = 0;
        foreach ($iterator as $fields) {
            $line++;
            if ($line > 1001 || count($fields) > count(self::COLUMNS)) {
                throw new \moodle_exception('importlimit', support::C);
            }
            if ($header === null) {
                self::validate_header($fields);
                $header = $fields;
                continue;
            }
            if (!array_filter($fields, static fn($value) => trim($value) !== '')) {
                continue;
            }
            if (count($fields) > count($header)) {
                throw new \moodle_exception('invalidheader', support::C);
            }
            $rows[$line] = array_combine($header, array_pad($fields, count($header), ''));
        }
        if (!$rows) {
            throw new \moodle_exception('emptyimport', support::C);
        }
        return $rows;
    }

    /**
     * Reject missing, duplicate or unexpected column names.
     */
    private static function validate_header(array $header): void {
        if (
            array_diff(['action'], $header)
                || array_diff($header, self::COLUMNS) || count(array_unique($header)) !== count($header)
        ) {
            throw new \moodle_exception('invalidheader', support::C);
        }
    }

    /**
     * Stream UTF-8 comma-delimited records.
     */
    private static function csv(string $path): \Generator {
        $stream = fopen($path, 'rb');
        try {
            if (fread($stream, 3) !== "\xef\xbb\xbf") {
                rewind($stream);
            }
            while (($fields = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                yield array_map([self::class, 'field'], $fields);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Verify archive bounds before parsing the first worksheet with Moodle-bundled OpenSpout.
     */
    private static function xlsx(string $path): \Generator {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \moodle_exception('invalidfile', support::C);
        }
        try {
            self::validate_zip($zip);
        } finally {
            $zip->close();
        }
        $reader = new Reader();
        $opened = false;
        try {
            $reader->open($path);
            $opened = true;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = [];
                    $cells = $row->getCells();
                    if (count($cells) > 64) {
                        throw new \moodle_exception('importlimit', support::C);
                    }
                    foreach ($cells as $cell) {
                        if ($cell instanceof FormulaCell || $cell instanceof ErrorCell) {
                            throw new \moodle_exception('formula', support::C);
                        }
                        $value = $cell->getValue();
                        $values[] = self::field($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
                    }
                    while ($values && end($values) === '') {
                        array_pop($values);
                    }
                    yield $values;
                }
                break;
            }
        } catch (\moodle_exception $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new \moodle_exception('invalidfile', support::C);
        } finally {
            if ($opened) {
                $reader->close();
            }
        }
    }

    /**
     * Reject oversized, unsupported or unsafe ZIP members before expanding the worksheet.
     */
    private static function validate_zip(\ZipArchive $zip): void {
        if (
            $zip->numFiles > 128 || $zip->locateName('xl/workbook.xml') === false
                || $zip->locateName('[Content_Types].xml') === false
        ) {
            throw new \moodle_exception('invalidfile', support::C);
        }
        $total = 0;
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (
                !$stat || !preg_match('~\A[A-Za-z0-9_][A-Za-z0-9._/-]*\z~D', $stat['name'])
                    && $stat['name'] !== '[Content_Types].xml'
            ) {
                throw new \moodle_exception('invalidfile', support::C);
            }
            if (
                isset($names[$stat['name']]) || preg_match('~(?:^|/)\.\.?(?:/|$)|vbaProject|externalLinks~i', $stat['name'])
                    || $stat['size'] > 8388608 || ($total += $stat['size']) > 25165824
            ) {
                throw new \moodle_exception('invalidfile', support::C);
            }
            $names[$stat['name']] = true;
        }
    }

    /**
     * Normalize scalar workbook or CSV values and enforce UTF-8.
     */
    private static function field(mixed $value): string {
        if ($value === null) {
            return '';
        }
        if (!is_scalar($value)) {
            throw new \moodle_exception('invalidfile', support::C);
        }
        $value = trim((string)$value);
        if (strlen($value) > 4096 || !preg_match('//u', $value) || str_contains($value, "\0")) {
            throw new \moodle_exception('invalidfile', support::C);
        }
        return $value;
    }
}
