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

namespace local_skillupscopilot;

/**
 * Course-scoped documents and bounded lexical retrieval.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sources {
    /**
     * Maximum document size.
     */
    public const MAX_BYTES = 65536;

    /**
     * Maximum documents per course.
     */
    public const MAX_DOCUMENTS = 40;

    /**
     * Check the document ACL before the File API is called.
     */
    public static function allowed(\stdClass $source, int $courseid, \stdClass $user): bool {
        global $DB;
        if (!$courseid || (int)$source->courseid !== $courseid || !access::allowed($courseid, $user)) {
            return false;
        }
        if ($source->groupid) {
            if (!$DB->record_exists('groups', ['id' => $source->groupid, 'courseid' => $courseid])) {
                return false;
            }
            if (
                !groups_is_member($source->groupid, $user->id) &&
                !has_capability('moodle/site:accessallgroups', access::context($courseid), $user)
            ) {
                return false;
            }
        }
        if (!$source->cmid) {
            return true;
        }
        $module = get_fast_modinfo($courseid, $user->id)->get_cms()[$source->cmid] ?? null;
        return $module && $module->uservisible && (!$module->has_view() ||
            // Modules without a declared view capability are governed by Moodle availability.
            !get_capability_info('mod/' . $module->modname . ':view', false) ||
            has_capability('mod/' . $module->modname . ':view', $module->context, $user));
    }

    /**
     * Write a UTF-8 text document after validating its ACL and file payload.
     */
    public static function add(int $courseid, \stdClass $user, \stdClass $data, \stored_file $draft): int {
        global $DB;
        $context = access::context($courseid);
        require_capability('local/skillupscopilot:managesources', $context, $user);
        $source = (object)[
            'courseid' => $courseid, 'cmid' => $data->cmid, 'groupid' => $data->groupid,
            'title' => trim($data->title), 'revision' => bin2hex(random_bytes(16)), 'timecreated' => time(),
        ];
        if (
            $draft->get_filesize() > self::MAX_BYTES ||
            !preg_match('/\.(txt|md)\z/iD', $draft->get_filename()) ||
            !self::allowed($source, $courseid, $user)
        ) {
            throw new \moodle_exception('invalidsource', 'local_skillupscopilot');
        }
        $body = $draft->get_content();
        if (!preg_match('//u', $body) || trim($body) === '' || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $body)) {
            throw new \moodle_exception('invalidsource', 'local_skillupscopilot');
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_skillupscopilot')->get_lock('sources_' . $courseid, 5);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_skillupscopilot');
        }
        try {
            if ($DB->count_records('local_skillupscopilot_source', ['courseid' => $courseid]) >= self::MAX_DOCUMENTS) {
                throw new \moodle_exception('sourcelimit', 'local_skillupscopilot');
            }
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record('local_skillupscopilot_source', $source);
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id, 'component' => 'local_skillupscopilot', 'filearea' => 'source',
                'itemid' => $id, 'filepath' => '/', 'filename' => 'document.txt', 'userid' => null,
            ], $body);
            \local_skillupscopilot\event\operation_recorded::record($context, 'sourceadded', $id);
            $transaction->allow_commit();
            return $id;
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove a source and its file. Historic responses retain only the revision manifest.
     */
    public static function remove(int $courseid, int $id): void {
        global $DB;
        $context = access::context($courseid);
        require_capability('local/skillupscopilot:managesources', $context);
        $DB->get_record('local_skillupscopilot_source', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        get_file_storage()->delete_area_files($context->id, 'local_skillupscopilot', 'source', $id);
        $DB->delete_records('local_skillupscopilot_source', ['id' => $id, 'courseid' => $courseid]);
        \local_skillupscopilot\event\operation_recorded::record($context, 'sourceremoved', $id);
        $transaction->allow_commit();
    }

    /**
     * Read a small bounded set of authorized passages.
     */
    public static function find(int $courseid, \stdClass $user, string $prompt): array {
        global $DB;
        if ($courseid === 0 || !access::allowed($courseid, $user)) {
            return [];
        }
        $terms = self::terms($prompt);
        if (!$terms) {
            return [];
        }
        $records = $DB->get_records(
            'local_skillupscopilot_source',
            ['courseid' => $courseid],
            'id ASC',
            '*',
            0,
            self::MAX_DOCUMENTS
        );
        $matches = [];
        $context = access::context($courseid);
        foreach ($records as $source) {
            if (!self::allowed($source, $courseid, $user)) {
                continue;
            }
            $file = get_file_storage()->get_file(
                $context->id,
                'local_skillupscopilot',
                'source',
                $source->id,
                '/',
                'document.txt'
            );
            if (!$file || $file->get_filesize() > self::MAX_BYTES) {
                continue;
            }
            $body = $file->get_content();
            $length = \core_text::strlen($body);
            for ($offset = 0; $offset < $length; $offset += 800) {
                $text = \core_text::substr($body, $offset, 1000);
                $score = count(array_intersect($terms, self::terms($text . ' ' . $source->title, 1000)));
                if ($score > 0) {
                    $matches[] = [
                        'id' => (int)$source->id, 'revision' => $source->revision, 'title' => $source->title,
                        'score' => $score, 'text' => $text,
                    ];
                }
            }
        }
        usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($matches, 0, 4);
    }

    /**
     * Return current source titles only if all recorded sources remain authorized.
     */
    public static function titles(?string $manifest, int $courseid, \stdClass $user): ?array {
        global $DB;
        if ($manifest === null || $manifest === '') {
            return [];
        }
        $entries = json_decode($manifest, true);
        if (!is_array($entries)) {
            return null;
        }
        $titles = [];
        foreach ($entries as $id => $revision) {
            $source = $DB->get_record('local_skillupscopilot_source', ['id' => $id, 'courseid' => $courseid]);
            if (!$source || $source->revision !== $revision || !self::allowed($source, $courseid, $user)) {
                return null;
            }
            $titles[] = ['title' => $source->title];
        }
        return $titles;
    }

    /**
     * Tokenize Unicode text without a language-specific dictionary.
     */
    public static function terms(string $text, int $limit = 30): array {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', \core_text::strtolower($text), $matches);
        return array_slice(array_values(array_unique($matches[0])), 0, $limit);
    }
}
