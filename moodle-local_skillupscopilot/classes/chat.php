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

use local_skillupscopilot\ai\registry;

/**
 * Owner-scoped chat operations and atomic job submission.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class chat {
    /**
     * Resolve a conversation by both owner and course.
     */
    public static function thread(int $id, int $courseid, int $userid): \stdClass {
        global $DB;
        return $DB->get_record(
            'local_skillupscopilot_thread',
            ['id' => $id, 'courseid' => $courseid, 'userid' => $userid],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Add a single turn and an ad hoc job, idempotently within a conversation.
     */
    public static function enqueue(int $courseid, int $threadid, \stdClass $user, \stdClass $data): int {
        global $DB;
        if (!access::allowed($courseid, $user) || !in_array($data->mode, access::modes($courseid, $user), true)) {
            throw new \moodle_exception('accessdenied', 'local_skillupscopilot');
        }
        $config = registry::config($data->provider);
        if (!$config || ($config['external'] && empty($data->consent))) {
            throw new \moodle_exception('providerunavailable', 'local_skillupscopilot');
        }
        $prompt = trim($data->prompt);
        if (
            $prompt === '' || \core_text::strlen($prompt) > 4000 ||
                !preg_match('/\A[a-f0-9]{32}\z/D', $data->requestkey)
        ) {
            throw new \moodle_exception('invalidprompt', 'local_skillupscopilot');
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_skillupscopilot');
        $lock = $factory->get_lock('quota', 5);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_skillupscopilot');
        }
        try {
            $now = time();
            if ($threadid) {
                self::thread($threadid, $courseid, (int)$user->id);
            } else {
                // Search by owner, course and the form nonce before making a conversation.
                $sql = "SELECT c.id
                          FROM {local_skillupscopilot_thread} c
                          JOIN {local_skillupscopilot_turn} t ON t.threadid = c.id
                         WHERE c.userid = :uid AND c.courseid = :cid AND t.requestkey = :nonce";
                $threadid = (int)$DB->get_field_sql(
                    $sql,
                    ['uid' => $user->id, 'cid' => $courseid, 'nonce' => $data->requestkey]
                );
            }
            if (
                $threadid && $DB->record_exists(
                    'local_skillupscopilot_turn',
                    ['threadid' => $threadid, 'requestkey' => $data->requestkey]
                )
            ) {
                return $threadid;
            }
            if (
                $threadid && $DB->record_exists_select(
                    'local_skillupscopilot_turn',
                    'threadid = ? AND status IN (?, ?)',
                    [$threadid, 'pending', 'running']
                )
            ) {
                throw new \moodle_exception('pending', 'local_skillupscopilot');
            }
            quota::require_capacity((int)$user->id, $courseid, $now);
            $transaction = $DB->start_delegated_transaction();
            try {
                if (!$threadid) {
                    $threadid = (int)$DB->insert_record('local_skillupscopilot_thread', (object)[
                        'courseid' => $courseid, 'userid' => $user->id, 'timecreated' => $now, 'timemodified' => $now,
                    ]);
                }
                $turnid = $DB->insert_record('local_skillupscopilot_turn', (object)[
                    'threadid' => $threadid, 'requestkey' => $data->requestkey, 'mode' => $data->mode,
                    'lang' => current_language(), 'provider' => $data->provider, 'providerrev' => $config['revision'],
                    'prompt' => $prompt, 'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
                ]);
                quota::reserve($turnid, (int)$user->id, $courseid, $data->provider, $now);

                $task = new \local_skillupscopilot\task\generate();
                $task->set_custom_data(['turnid' => $turnid]);
                if (!\core\task\manager::queue_adhoc_task($task)) {
                    throw new \moodle_exception('busy', 'local_skillupscopilot');
                }
                $DB->set_field('local_skillupscopilot_thread', 'timemodified', $now, ['id' => $threadid]);
                \local_skillupscopilot\event\operation_recorded::record(access::context($courseid), 'queued', $turnid);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
            return $threadid;
        } finally {
            $lock->release();
        }
    }

    /**
     * Delete turns and conversation headers scoped by a trusted caller.
     */
    public static function delete(array $conditions): void {
        global $DB;
        if (!$conditions) {
            throw new \coding_exception('Conversation deletion requires a scope');
        }
        $factory = \core\lock\lock_config::get_lock_factory('local_skillupscopilot');
        $lock = $factory->get_lock('quota', 10);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_skillupscopilot');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $clauses = [];
                foreach (array_keys($conditions) as $field) {
                    if (!in_array($field, ['id', 'courseid', 'userid'], true)) {
                        throw new \coding_exception('Invalid conversation deletion scope');
                    }
                    $clauses[] = "$field = :$field";
                }
                $where = implode(' AND ', $clauses);
                $DB->delete_records_select(
                    'local_skillupscopilot_turn',
                    "threadid IN (SELECT id FROM {local_skillupscopilot_thread} WHERE $where)",
                    $conditions
                );
                $DB->delete_records('local_skillupscopilot_thread', $conditions);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
        } finally {
            $lock->release();
        }
    }
}
