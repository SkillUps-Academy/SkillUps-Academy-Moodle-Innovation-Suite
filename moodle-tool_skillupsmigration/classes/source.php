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
 * Generate and serve private native Moodle backups for an authorized source account.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source {
    /**
     * Request or reuse an artifact for the same account and request key.
     *
     * @param int $actor Source token user
     * @param array $data Validated service request
     * @return array Artifact status
     */
    public static function prepare(int $actor, array $data): array {
        global $DB;
        $key = (string)($data['key'] ?? '');
        $id = (int)($data['course'] ?? 0);
        $options = inventory::options($data['options'] ?? []);
        if (!preg_match('/^[0-9a-f]{48}$/D', $key)) {
            support::fail('badrequest');
        }
        $inventory = inventory::read($id, $actor, $options);
        if (!hash_equals(hash('sha256', support::encode($inventory)), (string)($data['fingerprint'] ?? ''))) {
            support::fail('sourcechanged');
        }
        $lock = 'source:' . $actor;
        $record = support::lock($lock, static function () use ($DB, $actor, $id, $inventory, $key, $options) {
            $record = $DB->get_record(support::ARTIFACT, ['actor' => $actor, 'requestkey' => $key]);
            if ($record) {
                if (
                    $record->courseid != $id || $record->options !== support::encode($options) ||
                    $record->inventory !== support::encode($inventory)
                ) {
                    support::fail('sourcechanged');
                }
                return $record;
            }
            $active = "actor = :actor AND state IN ('pending','building')";
            if ($DB->count_records_select(support::ARTIFACT, $active, ['actor' => $actor]) >= 20) {
                support::fail('archivebounds');
            }
            $record = (object)[
                'actor' => $actor, 'courseid' => $id, 'requestkey' => $key, 'state' => 'pending',
                'options' => support::encode($options), 'inventory' => support::encode($inventory), 'digest' => '',
                'created' => time(), 'updated' => time(), 'bytes' => 0, 'errorcode' => '',
            ];
            $record->id = $DB->insert_record(support::ARTIFACT, $record);
            return $record;
        });
        if ($record->state === 'pending') {
            self::queue((int)$record->id, $actor);
        }
        return self::describe($record);
    }

    /**
     * Schedule source backup generation.
     *
     * @param int $id Source artifact ID
     * @param int $actor Authorized account
     */
    public static function queue(int $id, int $actor): void {
        $task = new task\process();
        $task->set_custom_data(['source' => $id]);
        $task->set_userid($actor);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Generate an MBZ in general mode, then move it to plugin-owned File API storage.
     *
     * @param int $id Artifact ID
     */
    public static function build(int $id): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        support::lock('build:' . $id, static function () use ($DB, $id): void {
            $record = $DB->get_record(support::ARTIFACT, ['id' => $id]);
            if (!$record || !in_array($record->state, ['pending', 'building'], true)) {
                return;
            }
            $record->state = 'building';
            $record->updated = time();
            $DB->update_record(support::ARTIFACT, $record);
            $backup = null;
            $native = null;
            $key = '';
            try {
                $options = support::decode($record->options);
                $expected = inventory::read((int)$record->courseid, (int)$record->actor, $options);
                if (support::encode($expected) !== $record->inventory) {
                    support::fail('sourcechanged');
                }
                $backup = new \backup_controller(
                    \backup::TYPE_1COURSE,
                    (int)$record->courseid,
                    \backup::FORMAT_MOODLE,
                    \backup::INTERACTIVE_NO,
                    \backup::MODE_GENERAL,
                    (int)$record->actor
                );
                $key = $backup->get_backupid();
                $plan = $backup->get_plan();
                foreach (
                    [
                    'users' => false, 'role_assignments' => false, 'permissions' => false, 'blocks' => false,
                    'filters' => false, 'comments' => false, 'badges' => false, 'calendarevents' => false,
                    'userscompletion' => false, 'logs' => false, 'grade_histories' => false, 'customfield' => false,
                    'contentbankcontent' => false, 'xapistate' => false, 'legacyfiles' => false,
                    'groups' => $options['groups'], 'files' => $options['files'],
                    'filename' => 'skillups-migration-' . $key . '.mbz',
                    ] as $name => $value
                ) {
                    self::setting($plan, $name, $value, in_array($name, ['users', 'groups', 'files', 'filename'], true));
                }
                $sections = array_fill_keys(array_column($expected['sections'], 'id'), true);
                $modules = array_fill_keys(array_column($expected['modules'], 'id'), true);
                $modinfo = get_fast_modinfo((int)$record->courseid, (int)$record->actor);
                foreach ($modinfo->get_section_info_all() as $section) {
                    $name = 'section_' . $section->id . '_included';
                    self::setting($plan, $name, isset($sections[$section->id]), isset($sections[$section->id]));
                }
                foreach ($modinfo->get_cms() as $cm) {
                    if (!$cm->deletioninprogress) {
                        $name = $cm->modname . '_' . $cm->id . '_included';
                        self::setting($plan, $name, isset($modules[$cm->id]), isset($modules[$cm->id]));
                    }
                }
                $backup->execute_plan();
                $native = $backup->get_results()['backup_destination'] ?? null;
                if (!$native instanceof \stored_file || $native->get_filesize() < 1 || $native->get_filesize() > support::limit()) {
                    support::fail('archivebounds');
                }
                $fs = get_file_storage();
                $scope = \context_system::instance();
                $fs->delete_area_files($scope->id, support::C, 'outgoing', $id);
                $file = $fs->create_file_from_storedfile([
                    'contextid' => $scope->id, 'component' => support::C, 'filearea' => 'outgoing', 'itemid' => $id,
                    'filepath' => '/', 'filename' => 'course.mbz', 'userid' => $record->actor,
                ], $native);
                $handle = $file->get_content_file_handle();
                try {
                    $hash = hash_init('sha256');
                    hash_update_stream($hash, $handle);
                    $record->digest = hash_final($hash);
                } finally {
                    fclose($handle);
                }
                $record->bytes = $file->get_filesize();
                $record->state = 'ready';
            } catch (\Throwable $error) {
                $record->state = 'failed';
                $allowed = ['archivebounds', 'sourcechanged', 'selection', 'unavailable', 'configuration'];
                $record->errorcode = $error instanceof \moodle_exception && in_array($error->errorcode, $allowed, true)
                    ? $error->errorcode : 'backupfailed';
            } finally {
                if ($native instanceof \stored_file) {
                    $native->delete();
                }
                if ($backup) {
                    $backup->destroy();
                }
                if ($key) {
                    \backup_helper::delete_backup_dir($key);
                }
            }
            $record->updated = time();
            $DB->update_record(support::ARTIFACT, $record);
        }, 0);
    }

    /**
     * Apply a native setting without bypassing a site policy lock.
     *
     * @param object $plan Core plan
     * @param string $name Setting name
     * @param mixed $value Desired value
     * @param bool $required Whether missing is an incompatibility
     */
    public static function setting(object $plan, string $name, mixed $value, bool $required = false): void {
        if (!$plan->setting_exists($name)) {
            if ($required) {
                support::fail('configuration');
            }
            return;
        }
        $setting = $plan->get_setting($name);
        if ($setting->get_value() != $value) {
            try {
                $setting->set_value($value);
            } catch (\Throwable $error) {
                support::fail('configuration');
            }
        }
        if ($setting->get_value() != $value) {
            support::fail('configuration');
        }
    }

    /**
     * Read the artifact status, or return a bounded byte range to its original owner.
     *
     * @param int $actor Source token owner
     * @param int $id Artifact
     * @param int|null $offset Optional byte offset
     * @return array Status and optional transfer payload
     */
    public static function read(int $actor, int $id, ?int $offset): array {
        $record = support::get(support::ARTIFACT, $id);
        if ($record->actor != $actor) {
            support::fail('unavailable');
        }
        inventory::course((int)$record->courseid, $actor);
        $response = self::describe($record);
        if ($offset === null || $record->state !== 'ready') {
            return $response;
        }
        $file = get_file_storage()->get_file(\context_system::instance()->id, support::C, 'outgoing', $id, '/', 'course.mbz');
        if (!$file || $offset < 0 || $offset >= $record->bytes) {
            support::fail('badrequest');
        }
        $handle = $file->get_content_file_handle();
        try {
            if (fseek($handle, $offset) !== 0) {
                support::fail('archiveio');
            }
            $bytes = min(support::CHUNK, (int)$record->bytes - $offset);
            $chunk = fread($handle, $bytes);
            if (!is_string($chunk) || strlen($chunk) !== $bytes) {
                support::fail('archiveio');
            }
        } finally {
            fclose($handle);
        }
        return $response + ['offset' => $offset, 'data' => base64_encode($chunk)];
    }

    /**
     * Build the stable wire representation of a backup.
     *
     * @param \stdClass $record Artifact
     * @return array
     */
    private static function describe(\stdClass $record): array {
        return [
            'id' => (int)$record->id, 'state' => $record->state, 'bytes' => (int)$record->bytes,
            'sha256' => $record->digest, 'reason' => $record->errorcode,
        ];
    }
}
