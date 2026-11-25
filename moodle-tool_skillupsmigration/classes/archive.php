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

namespace tool_skillupsmigration;

/**
 * Validate real native backup content before any destination write.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class archive {
    /**
     * Return policy and version compatibility issues from the current destination.
     *
     * @param array $inventory Source topology
     * @param array $options Selected features
     * @return array Issues as localized keys and safe qualifiers
     */
    public static function compatibility(array $inventory, array $options): array {
        global $CFG;
        $issues = [];
        if ((int)($inventory['version'] ?? PHP_INT_MAX) > (int)$CFG->version) {
            $issues[] = ['code' => 'newersource', 'detail' => (string)($inventory['release'] ?? '')];
        }
        $format = $inventory['format'] ?? '';
        $manager = \core_plugin_manager::instance();
        $installed = $manager->get_installed_plugins('format');
        if (empty($installed[$format])) {
            $issues[] = ['code' => 'missingplugin', 'detail' => 'format_' . $format];
        }
        $installed = $manager->get_installed_plugins('mod');
        foreach ($inventory['modules'] ?? [] as $mod) {
            $name = $mod['type'];
            $code = '';
            if (empty($mod['backup'])) {
                $code = 'unsupportedactivity';
            } else if (empty($installed[$name]) || !plugin_supports('mod', $name, FEATURE_BACKUP_MOODLE2, false)) {
                $code = 'missingplugin';
            } else if ((int)$installed[$name] < (int)$mod['version']) {
                $code = 'olderplugin';
            }
            if ($code) {
                $issues[] = ['code' => $code, 'detail' => 'mod_' . $name . ' #' . (int)$mod['id']];
            }
        }
        if ($options['staff']) {
            try {
                destination::resolve_staff($options['staff']);
            } catch (\moodle_exception $error) {
                $issues[] = ['code' => 'usersmap', 'detail' => ''];
            }
        }
        return array_slice($issues, 0, 40);
    }

    /**
     * Examine archive names and expanded sizes before extracting to a new private native workspace.
     *
     * @param string $path Verified local MBZ
     * @param string $key Native backup workspace key
     * @return array File paths declared by the packer
     */
    public static function extract(string $path, string $key): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if (!is_file($path) || filesize($path) > support::limit()) {
            support::fail('archivebounds');
        }
        $packer = get_file_packer('application/vnd.moodle.backup');
        $entries = $packer->list_files($path);
        if (!is_array($entries) || !$entries || count($entries) > 100000) {
            support::fail('archivebounds');
        }
        $expanded = 0;
        $result = [];
        foreach ($entries as $entry) {
            $name = rtrim($entry->pathname, '/');
            $size = (int)($entry->size ?? 0);
            $original = rtrim($entry->original_pathname ?? $entry->pathname, '/');
            if (
                $name === '' || $name !== $original || !preg_match('~^[A-Za-z0-9_./-]{1,450}$~D', $name) ||
                in_array('..', explode('/', $name), true) || in_array('.', explode('/', $name), true) ||
                str_starts_with($name, '/') || isset($result[$name]) || $size < 0
            ) {
                support::fail('archivepath');
            }
            $result[$name] = $entry;
            $expanded += $size;
            if ($expanded > support::limit() * 4 || (str_ends_with($name, '.xml') && $size > 33554432)) {
                support::fail('archivebounds');
            }
        }
        if (!isset($result['moodle_backup.xml'], $result['course/course.xml'])) {
            support::fail('archiveformat');
        }
        $root = make_backup_temp_directory($key);
        $free = disk_free_space($root);
        if ($free !== false && $expanded > $free) {
            support::fail('archivebounds');
        }
        $extracted = $packer->extract_to_pathname($path, $root);
        if (!$extracted) {
            support::fail('archiveio');
        }
        foreach ($result as $name => $entry) {
            $file = $root . '/' . $name;
            if (!empty($entry->is_directory)) {
                continue;
            }
            if (is_link($file) || empty($extracted[$name]) || !is_file($file) || filesize($file) !== (int)$entry->size) {
                support::fail('archiveio');
            }
            if (str_ends_with($name, '.xml')) {
                self::scan_xml($file);
            }
        }
        return $result;
    }

    /**
     * Parse backup XML without loading DTDs or permitting entities.
     *
     * @param string $file Native XML file
     * @param callable|null $visit Optional visitor for plain text element scans
     */
    private static function scan_xml(string $file, ?callable $visit = null): void {
        $reader = new \XMLReader();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$reader->open($file, null, LIBXML_NONET)) {
                support::fail('archiveformat');
            }
            while ($reader->read()) {
                if (in_array($reader->nodeType, [\XMLReader::DOC_TYPE, \XMLReader::ENTITY_REF], true)) {
                    support::fail('archiveformat');
                }
                if ($visit && $reader->nodeType === \XMLReader::ELEMENT) {
                    $visit($reader);
                }
            }
            foreach (libxml_get_errors() as $error) {
                if ($error->level >= LIBXML_ERR_ERROR) {
                    support::fail('archiveformat');
                }
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Verify native topology, forbidden user data and question/file dependencies.
     *
     * @param string $path Local archive
     * @param array $inventory Selected source topology
     * @param array $options Selected features
     * @return array Report with issues and measured artifact counts
     */
    public static function inspect(string $path, array $inventory, array $options): array {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $key = \restore_controller::get_tempdir_name();
        $report = ['issues' => self::compatibility($inventory, $options), 'artifacts' => []];
        try {
            $entries = self::extract($path, $key);
            $root = make_backup_temp_directory($key);
            $info = \backup_general_helper::get_backup_information($key);
            $policy = [
                'courseid' => (int)$info->original_course_id === (int)$inventory['id'],
                'version' => (int)$info->moodle_version === (int)$inventory['version'],
                'format' => $info->format === \backup::FORMAT_MOODLE && $info->type === \backup::TYPE_1COURSE,
                'users' => empty($info->root_settings['users']),
                'references' => empty($info->include_file_references_to_external_content),
                'files' => (bool)$info->include_files === $options['files'],
                'groups' => (bool)($info->root_settings['groups'] ?? 0) === $options['groups'],
            ];
            $invalid = array_keys(array_filter($policy, static fn($valid) => !$valid));
            if ($invalid) {
                $report['issues'][] = ['code' => 'archivemismatch', 'detail' => implode(', ', $invalid)];
            }
            $modules = array_map(static fn($item) => $item['type'] . '_' . $item['id'], $inventory['modules']);
            $sections = array_map(static fn($item) => 'section_' . $item['id'], $inventory['sections']);
            $topology = ['activities' => [$modules, $info->activities], 'sections' => [$sections, $info->sections]];
            foreach ($topology as $kind => $pair) {
                [$expected, $actual] = $pair;
                $keys = array_keys($actual);
                $difference = array_merge(array_diff($expected, $keys), array_diff($keys, $expected));
                if ($difference) {
                    $report['issues'][] = ['code' => 'topology', 'detail' => implode(', ', array_slice($difference, 0, 12))];
                }
                $report['artifacts'][$kind] = count($actual);
            }
            $types = [];
            $files = 0;
            foreach ($entries as $name => $entry) {
                if (basename($name) === 'questions.xml') {
                    self::scan_xml($root . '/' . $name, static function (\XMLReader $reader) use (&$types): void {
                        if ($reader->name === 'qtype') {
                            $kind = trim($reader->readString());
                            if (preg_match('/^[a-z][a-z0-9_]*$/D', $kind)) {
                                $types[$kind] = true;
                            }
                        }
                    });
                }
                if (preg_match('~^files/([a-f0-9]{2})/([a-f0-9]{40})$~D', $name, $match)) {
                    if (!hash_equals($match[2], sha1_file($root . '/' . $name))) {
                        support::fail('fileintegrity');
                    }
                    $files++;
                }
            }
            if ($options['files'] && isset($entries['files.xml'])) {
                self::scan_xml($root . '/files.xml', static function (\XMLReader $reader) use ($entries): void {
                    if ($reader->name !== 'file' || $reader->isEmptyElement) {
                        return;
                    }
                    $file = new \SimpleXMLElement($reader->readOuterXml(), LIBXML_NONET);
                    if ((string)$file->filename === '.') {
                        return;
                    }
                    $hash = (string)$file->contenthash;
                    if (!preg_match('/^[a-f0-9]{40}$/D', $hash) || !isset($entries['files/' . substr($hash, 0, 2) . '/' . $hash])) {
                        support::fail('fileintegrity');
                    }
                });
            }
            $available = \core_plugin_manager::instance()->get_installed_plugins('qtype');
            foreach (array_keys($types) as $type) {
                if (empty($available[$type])) {
                    $report['issues'][] = ['code' => 'missingplugin', 'detail' => 'qtype_' . $type];
                }
            }
            $report['artifacts'] += ['files' => $files, 'questiontypes' => array_keys($types), 'bytes' => filesize($path)];
            return $report;
        } finally {
            \backup_helper::delete_backup_dir($key);
        }
    }
}
