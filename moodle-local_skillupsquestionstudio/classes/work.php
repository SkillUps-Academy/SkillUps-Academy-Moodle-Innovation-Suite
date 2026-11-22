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

namespace local_skillupsquestionstudio;

use local_skillupsquestionstudio\extraction\document;
use local_skillupsquestionstudio\generation\local;
use local_skillupsquestionstudio\generation\native;

/**
 * Durable file and generation jobs. Ad hoc task payloads carry IDs only.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class work {
    /**
     * Store exactly one validated draft copy in an undisclosed File API area.
     */
    public static function upload(\stdClass $project, \stored_file $file, int $userid): int {
        global $DB;
        $type = document::extension($file);
        $scope = ['projectid' => $project->id];
        $lock = access::lock($project->id);
        try {
            if (
                $DB->count_records_select(
                    access::C . '_source',
                    'projectid = ? AND state <> ?',
                    [$project->id, 'deleted']
                ) >= 30
            ) {
                access::fail('sourcelimit');
            }
            $hash = $file->get_contenthash();
            if (
                $DB->record_exists_select(
                    access::C . '_source',
                    'projectid = ? AND hash = ? AND state IN (?, ?, ?)',
                    [$project->id, $hash, 'queued', 'working', 'ready']
                )
            ) {
                access::fail('duplicatesource');
            }
            $tx = $DB->start_delegated_transaction();
            try {
                $id = $DB->insert_record(access::C . '_source', (object)($scope + [
                    'userid' => $userid, 'hash' => $hash, 'name' => $file->get_filename(), 'state' => 'queued',
                    'timecreated' => time(), 'timemodified' => time(),
                ]));
                get_file_storage()->create_file_from_storedfile([
                    'contextid' => access::context($project->courseid)->id, 'component' => access::C,
                    'filearea' => 'inbox', 'itemid' => $id, 'filepath' => '/', 'filename' => 'source.' . $type,
                    'userid' => $userid,
                ], $file);
                self::queue('source', $id);
                studio::audit($project->id, 'sourcequeued', $id);
                $tx->allow_commit();
                return (int)$id;
            } catch (\Throwable $exception) {
                $tx->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Submit a bounded job only for a ready source in the current project.
     */
    public static function request(\stdClass $project, int $sourceid, array $request, int $userid): int {
        global $DB;
        $lock = access::lock($project->id);
        try {
            $scope = ['id' => $sourceid, 'projectid' => $project->id, 'state' => 'ready'];
            if (!$DB->record_exists(access::C . '_source', $scope)) {
                access::fail('sourceunavailable');
            }
            if (
                !in_array($request['kind'], question::TYPES, true) || !in_array($request['bloom'], question::BLOOM, true) ||
                    !in_array($request['level'], question::LEVELS, true) ||
                    !in_array($request['provider'], ['local', 'ai'], true) ||
                    $request['count'] < 1 || $request['count'] > 10
            ) {
                access::fail('invalidquestion');
            }
            if (
                $request['provider'] === 'ai' && (empty($request['consent']) ||
                    !native::enabled(access::context($project->courseid), $userid) ||
                    !\core_ai\manager::get_user_policy_status($userid))
            ) {
                access::fail('aiunavailable');
            }
            $request['unit'] = question::text($request['unit'], 150, true);
            $request['lang'] = current_language();
            competencies::require_valid($project->courseid, $request['competency']);
            $now = time();
            if (
                $DB->count_records_select(
                    access::C . '_job',
                    'projectid = ? AND timecreated > ?',
                    [$project->id, $now - DAYSECS]
                ) >= 60 ||
                    $DB->record_exists_select(
                        access::C . '_job',
                        'projectid = ? AND state IN (?, ?)',
                        [$project->id, 'queued', 'working']
                    )
            ) {
                access::fail('joblimit');
            }
            $tx = $DB->start_delegated_transaction();
            try {
                $id = $DB->insert_record(access::C . '_job', (object)[
                    'projectid' => $project->id, 'sourceid' => $sourceid, 'userid' => $userid,
                    'request' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'state' => 'queued', 'timecreated' => $now, 'timemodified' => $now,
                ]);
                self::queue('job', $id);
                studio::audit($project->id, 'generationqueued', $id);
                $tx->allow_commit();
                return (int)$id;
            } catch (\Throwable $exception) {
                $tx->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Run a pending request once. Never replay a generation request with an ambiguous result.
     */
    public static function execute(string $type, int $id): void {
        global $DB, $USER;
        if (!in_array($type, ['source', 'job'], true)) {
            return;
        }
        $table = access::C . '_' . $type;
        $record = $DB->get_record($table, ['id' => $id, 'state' => 'queued']);
        if (!$record) {
            return;
        }
        $lock = access::lock($record->projectid);
        try {
            $record = $DB->get_record($table, ['id' => $id, 'state' => 'queued']);
            if (!$record || !$project = $DB->get_record(access::C . '_project', ['id' => $record->projectid])) {
                return;
            }
            $DB->update_record($table, (object)['id' => $id, 'state' => 'working', 'timemodified' => time()]);
            $previous = $USER;
            try {
                \core\session\manager::set_user(access::actor($record->userid, $project->courseid));
                if ($type === 'source') {
                    self::extract($project, $record);
                } else {
                    self::generate($project, $record);
                }
            } catch (\Throwable $exception) {
                $update = (object)['id' => $id, 'state' => 'failed', 'timemodified' => time()];
                if ($type === 'source') {
                    $code = $exception instanceof \moodle_exception ? $exception->errorcode : '';
                    $update->errorcode = in_array($code, ['invaliddocument', 'pdfunavailable', 'emptydocument'], true) ?
                        $code : 'extractionfailed';
                }
                $DB->update_record($table, $update);
            } finally {
                if ($type === 'source') {
                    self::delete_upload($project->courseid, $id);
                }
                \core\session\manager::set_user($previous);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Read a private pending upload and keep only the bounded extracted text.
     */
    private static function extract(\stdClass $project, \stdClass $record): void {
        global $DB;
        $files = get_file_storage()->get_area_files(
            access::context($project->courseid)->id,
            access::C,
            'inbox',
            $record->id,
            'id',
            false
        );
        if (count($files) !== 1) {
            access::fail('invaliddocument');
        }
        $text = document::extract(reset($files));
        $DB->update_record(access::C . '_source', (object)[
            'id' => $record->id, 'state' => 'ready', 'content' => $text, 'timemodified' => time(),
        ]);
        studio::audit($project->id, 'extracted', $record->id);
    }

    /**
     * Validate each proposal and commit all accepted drafts and status together.
     */
    private static function generate(\stdClass $project, \stdClass $record): void {
        global $DB;
        $request = json_decode($record->request, true, 10, JSON_THROW_ON_ERROR);
        $source = $DB->get_record(access::C . '_source', [
            'id' => $record->sourceid, 'projectid' => $project->id, 'state' => 'ready',
        ], '*', MUST_EXIST);
        competencies::require_valid($project->courseid, $request['competency']);
        $generator = $request['provider'] === 'ai' ? new native() : new local();
        $proposals = $generator->generate($source->content, $request, access::context($project->courseid), $record->userid);
        $remaining = studio::MAX_ITEMS - $DB->count_records(access::C . '_item', ['projectid' => $project->id]);
        $tx = $DB->start_delegated_transaction();
        try {
            $inserted = 0;
            foreach ($proposals as $proposal) {
                if ($inserted >= $remaining) {
                    break;
                }
                try {
                    $data = question::validate($proposal);
                } catch (\moodle_exception $exception) {
                    continue;
                }
                if (!studio::duplicate($project->id, question::fingerprint($data))) {
                    studio::write($project->id, $data, $record->userid, null, $source->id, $request['provider']);
                    $inserted++;
                }
            }
            $DB->update_record(access::C . '_job', (object)[
                'id' => $record->id, 'state' => 'ready', 'resultcount' => $inserted, 'timemodified' => time(),
            ]);
            studio::audit($project->id, 'generated', $record->id);
            $tx->allow_commit();
        } catch (\Throwable $exception) {
            $tx->rollback($exception);
        }
    }

    /**
     * Remove the queued upload and the extracted text without removing reviewed drafts.
     */
    public static function remove(\stdClass $project, int $id): void {
        global $DB;
        $lock = access::lock($project->id);
        try {
            $DB->get_record(access::C . '_source', ['id' => $id, 'projectid' => $project->id], 'id', MUST_EXIST);
            self::delete_upload($project->courseid, $id);
            $DB->update_record(access::C . '_source', (object)[
                'id' => $id, 'state' => 'deleted', 'content' => null, 'name' => '', 'hash' => '', 'timemodified' => time(),
            ]);
            studio::audit($project->id, 'sourceremoved', $id);
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove a plugin-owned confidential upload without exposing a file-serving endpoint.
     */
    public static function delete_upload(int $courseid, int $id): void {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            get_file_storage()->delete_area_files($context->id, access::C, 'inbox', $id);
        }
    }

    /**
     * Schedule the ID and use Moodle's deduplication for pending tasks.
     */
    public static function queue(string $type, int $id, bool $recover = false): void {
        $job = new \local_skillupsquestionstudio\task\process();
        $job->set_custom_data(['type' => $type, 'id' => $id]);
        $result = \core\task\manager::queue_adhoc_task($job, $recover);
        if (!$result && !$recover) {
            access::fail('busy');
        }
    }
}
