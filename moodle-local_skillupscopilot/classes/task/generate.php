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

namespace local_skillupscopilot\task;

use local_skillupscopilot\access;
use local_skillupscopilot\ai\provider_failure;
use local_skillupscopilot\ai\registry;
use local_skillupscopilot\sources;

/**
 * Process one chat turn with current permissions and provider configuration.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generate extends \core\task\adhoc_task {
    /**
     * Give the job a localized name in Moodle.
     */
    public function get_name(): string {
        return get_string('taskgenerate', 'local_skillupscopilot');
    }

    /**
     * Avoid replaying a request after a crash or an ambiguous transport failure.
     */
    public function retry_until_success(): bool {
        return false;
    }

    /**
     * Run the queued generation.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $id = (int)$data->turnid;
        $factory = \core\lock\lock_config::get_lock_factory('local_skillupscopilot');
        $lock = $factory->get_lock('turn_' . $id, 5);
        if (!$lock) {
            return;
        }
        try {
            $turn = $DB->get_record('local_skillupscopilot_turn', ['id' => $id, 'status' => 'pending']);
            if (!$turn) {
                return;
            }
            try {
                $this->process($turn, !empty($data->retry));
            } catch (\Throwable $exception) {
                // Never expose prompts, credentials or third-party error bodies in cron logs.
                $this->finish($id, 'failed');
                mtrace('SkillUps AI Copilot generation failed for turn ' . $id);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Build a fresh authorized prompt and submit it once.
     */
    private function process(\stdClass $turn, bool $retry): void {
        global $DB;
        $thread = $DB->get_record('local_skillupscopilot_thread', ['id' => $turn->threadid], '*', MUST_EXIST);
        $user = \core_user::get_user($thread->userid, '*', MUST_EXIST);
        $courseid = (int)$thread->courseid;
        $config = registry::config($turn->provider);
        if (
            !$config || $config['revision'] !== $turn->providerrev || !access::allowed($courseid, $user) ||
                !in_array($turn->mode, access::modes($courseid, $user), true)
        ) {
            $this->finish($turn->id, 'cancelled');
            return;
        }

        $passages = sources::find($courseid, $user, $turn->prompt);
        $manifest = [];
        $documents = [];
        foreach ($passages as $passage) {
            $manifest[$passage['id']] = $passage['revision'];
            $documents[] = ['title' => $passage['title'], 'passage' => $passage['text']];
        }
        $strings = get_string_manager();
        $mode = $strings->get_string('instruction_' . $turn->mode, 'local_skillupscopilot', null, $turn->lang);
        $instruction = $strings->get_string('instruction_base', 'local_skillupscopilot', $mode, $turn->lang);
        $messages = [['role' => 'system', 'content' => $instruction]];
        $previous = $DB->get_records_select(
            'local_skillupscopilot_turn',
            'threadid = :thread AND id < :id AND status = :status',
            ['thread' => $thread->id, 'id' => $turn->id, 'status' => 'complete'],
            'id DESC',
            'id, prompt, answer, manifest, mode, provider, providerrev',
            0,
            3
        );
        $modes = access::modes($courseid, $user);
        foreach (array_reverse($previous) as $past) {
            if (
                !in_array($past->mode, $modes, true) ||
                    $past->provider !== $turn->provider || $past->providerrev !== $turn->providerrev
            ) {
                continue;
            }
            $messages[] = ['role' => 'user', 'content' => \core_text::substr($past->prompt, 0, 1000)];
            if ($past->manifest === '[]') {
                $messages[] = ['role' => 'assistant', 'content' => \core_text::substr($past->answer, 0, 1200)];
            }
        }
        $messages[] = ['role' => 'user', 'content' => json_encode([
            'question' => $turn->prompt, 'authorized_passages' => $documents,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];

        if (!$DB->record_exists('local_skillupscopilot_turn', ['id' => $turn->id, 'status' => 'pending'])) {
            return;
        }
        if (sources::titles(json_encode($manifest, JSON_THROW_ON_ERROR), $courseid, $user) === null) {
            $this->finish($turn->id, 'cancelled');
            return;
        }
        $this->finish($turn->id, 'running');
        try {
            $result = registry::connector($turn->provider)->generate($messages, $config);
        } catch (provider_failure $failure) {
            if (!$retry && $failure->retryable) {
                $task = new self();
                $task->set_custom_data(['turnid' => (int)$turn->id, 'retry' => true]);
                $task->set_next_run_time(time() + 60);
                $transaction = $DB->start_delegated_transaction();
                try {
                    $this->finish($turn->id, 'pending');
                    if (!\core\task\manager::queue_adhoc_task($task)) {
                        throw new \moodle_exception('busy', 'local_skillupscopilot');
                    }
                    $transaction->allow_commit();
                } catch (\Throwable $exception) {
                    $transaction->rollback($exception);
                }
                return;
            }
            $this->finish($turn->id, 'failed');
            return;
        }
        $this->finish($turn->id, 'complete', $result, json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    /**
     * Store the state and measured usage without reviving removed conversation rows.
     */
    private function finish(int $id, string $status, ?array $result = null, ?string $manifest = null): void {
        global $DB;
        $now = time();
        $changes = ['id' => $id, 'status' => $status, 'timemodified' => $now];
        if ($result !== null) {
            $changes['answer'] = $result['text'];
            $changes['manifest'] = $manifest;
        }
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->update_record('local_skillupscopilot_turn', (object)$changes);
            $meter = $DB->get_record('local_skillupscopilot_meter', ['turnid' => $id], 'id');
            if ($meter) {
                $DB->update_record('local_skillupscopilot_meter', (object)[
                    'id' => $meter->id, 'status' => $status, 'timemodified' => $now,
                    'inputtokens' => $result['input'] ?? 0, 'outputtokens' => $result['output'] ?? 0,
                ]);
            }
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }
    }
}
