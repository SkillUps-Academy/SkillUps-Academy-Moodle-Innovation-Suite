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
use local_skillupscopilot\privacy\provider;
use local_skillupscopilot\task\generate;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Queue and provider contracts exercised inside a Moodle test installation.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(chat::class)]
#[CoversClass(quota::class)]
#[CoversClass(generate::class)]
#[CoversClass(registry::class)]
#[CoversClass(provider::class)]
final class generation_test extends \advanced_testcase {
    /**
     * Reserve once, normalize a provider reply and enforce the daily limit.
     */
    public function test_queue_is_idempotent_and_metered(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->configure();
        set_config('userquota', 1, 'local_skillupscopilot');

        $data = (object)[
            'mode' => 'admin', 'provider' => 'ollama', 'prompt' => 'Draft an introduction',
            'requestkey' => str_repeat('a', 32),
        ];
        $threadid = chat::enqueue(0, 0, $GLOBALS['USER'], $data);
        $this->assertSame($threadid, chat::enqueue(0, 0, $GLOBALS['USER'], $data));
        $this->assertEquals(1, $DB->count_records('local_skillupscopilot_meter'));

        $turn = $DB->get_record('local_skillupscopilot_turn', ['threadid' => $threadid], '*', MUST_EXIST);
        \curl::mock_response(json_encode([
            'message' => ['content' => 'Draft introduction'], 'prompt_eval_count' => 12, 'eval_count' => 5,
        ]));
        $task = new generate();
        $task->set_custom_data(['turnid' => $turn->id]);
        $task->execute();
        $task->execute();
        $updated = $DB->get_record('local_skillupscopilot_turn', ['id' => $turn->id], '*', MUST_EXIST);
        $this->assertSame('complete', $updated->status);
        $this->assertSame('Draft introduction', $updated->answer);
        $usage = quota::totals(quota::day_start(time()));
        $this->assertEquals(12, $usage['ollama']->inputtokens);
        $this->assertEquals(5, $usage['ollama']->outputtokens);

        $data->requestkey = str_repeat('b', 32);
        try {
            chat::enqueue(0, 0, $GLOBALS['USER'], $data);
            $this->fail('User quota was bypassed');
        } catch (\moodle_exception $exception) {
            $this->assertSame('quotaexceeded', $exception->errorcode);
        }
        $this->assertEquals(1, $DB->count_records('local_skillupscopilot_thread'));
        $this->assertEquals(1, $DB->count_records('local_skillupscopilot_turn'));
    }

    /**
     * Fail closed when configuration changes between queue and dispatch.
     */
    public function test_queued_job_is_cancelled_when_provider_changes(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->configure();
        $threadid = chat::enqueue(0, 0, $USER, (object)[
            'mode' => 'admin', 'provider' => 'ollama', 'prompt' => 'Draft a checklist',
            'requestkey' => str_repeat('c', 32),
        ]);
        set_config('ollama_model', 'different-model', 'local_skillupscopilot');
        $turn = $DB->get_record('local_skillupscopilot_turn', ['threadid' => $threadid], '*', MUST_EXIST);
        $task = new generate();
        $task->set_custom_data(['turnid' => $turn->id]);
        $task->execute();
        $this->assertSame('cancelled', $DB->get_field('local_skillupscopilot_turn', 'status', ['id' => $turn->id]));
    }

    /**
     * External connectors require the institutional switch.
     */
    public function test_external_connector_disabled_and_requires_confirmation(): void {
        global $CFG, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->local_skillupscopilot_providers = ['compatible' => ['url' => 'https://provider.example/v1/chat/completions']];
        set_config('compatible_enabled', 1, 'local_skillupscopilot');
        set_config('compatible_model', 'demo-model', 'local_skillupscopilot');
        set_config('allowexternal', 0, 'local_skillupscopilot');
        $this->assertNull(registry::config('compatible'));
        set_config('allowexternal', 1, 'local_skillupscopilot');
        $this->assertTrue(registry::config('compatible')['external']);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('providerunavailable', 'local_skillupscopilot'));
        chat::enqueue(0, 0, $USER, (object)[
            'mode' => 'admin', 'provider' => 'compatible', 'prompt' => 'Draft a checklist',
            'requestkey' => str_repeat('d', 32),
        ]);
    }

    /**
     * Deleting history preserves the meter until the Privacy API erases its owner.
     */
    public function test_privacy_removes_only_approved_records(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->configure();
        $user = $USER;
        $threadid = chat::enqueue(0, 0, $user, (object)[
            'mode' => 'admin', 'provider' => 'ollama', 'prompt' => 'Draft a checklist',
            'requestkey' => str_repeat('e', 32),
        ]);
        chat::delete(['courseid' => 0, 'userid' => $user->id, 'id' => $threadid]);
        $this->assertFalse($DB->record_exists('local_skillupscopilot_thread', ['id' => $threadid]));
        $this->assertTrue($DB->record_exists('local_skillupscopilot_meter', ['userid' => $user->id]));
        $systemid = \context_system::instance()->id;
        $this->assertEquals([$systemid], provider::get_contexts_for_userid($user->id)->get_contextids());
        $approval = new \core_privacy\local\request\approved_contextlist($user, 'local_skillupscopilot', [$systemid]);
        provider::delete_data_for_user($approval);
        $this->assertFalse($DB->record_exists('local_skillupscopilot_meter', ['userid' => $user->id]));
    }

    /**
     * Enable a local URL for the Moodle HTTP fixture.
     */
    private function configure(): void {
        global $CFG;
        $CFG->local_skillupscopilot_providers = ['ollama' => ['url' => 'http://127.0.0.1:11434/api/chat']];
        set_config('ollama_enabled', 1, 'local_skillupscopilot');
        set_config('ollama_model', 'demo-model', 'local_skillupscopilot');
    }
}
