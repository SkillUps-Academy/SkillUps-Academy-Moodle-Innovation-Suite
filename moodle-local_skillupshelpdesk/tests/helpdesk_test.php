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

namespace local_skillupshelpdesk;

use context_system;
use core_privacy\local\request\approved_contextlist;
use moodle_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Integration tests for Helpdesk data, permissions, notifications and SLA.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(access::class)]
#[CoversClass(attachments::class)]
#[CoversClass(catalog::class)]
#[CoversClass(notification::class)]
#[CoversClass(privacy\provider::class)]
#[CoversClass(queries::class)]
#[CoversClass(storage::class)]
#[CoversClass(task\process::class)]
#[CoversClass(task\purge::class)]
#[CoversClass(tickets::class)]
final class helpdesk_test extends \advanced_testcase {
    /**
     * Reset Moodle data and disable real external notification processing.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('notifications', 0, access::COMPONENT);
    }

    /**
     * Return intake data using available seed catalogs.
     *
     * @param string $title Ticket title
     * @param int $draft Attachment draft
     * @return stdClass
     */
    private function intake(string $title = 'Synthetic support request', int $draft = 0): stdClass {
        $data = (object)['title' => $title, 'body' => 'Synthetic issue description', 'courseid' => 0, 'attachments' => $draft];
        foreach (['campus', 'category', 'priority', 'severity'] as $kind) {
            $options = catalog::options($kind);
            $data->{$kind . 'id'} = (int)array_key_first($options);
        }
        return $data;
    }

    /**
     * Create a real system-scoped support role and one technician.
     *
     * @return stdClass
     */
    private function technician(): stdClass {
        $generator = $this->getDataGenerator();
        $roleid = $generator->create_role();
        $context = context_system::instance();
        assign_capability(access::CAP . 'work', CAP_ALLOW, $roleid, $context->id);
        $person = $generator->create_user();
        role_assign($roleid, $person->id, $context->id);
        return $person;
    }

    /**
     * Change assignment and optionally switch to a seeded status.
     *
     * @param int $id Ticket ID
     * @param int $userid Technician user ID
     * @param string $status State name
     */
    private function assign(int $id, int $userid, string $status = 'open'): void {
        global $DB;
        $data = clone tickets::get($id);
        $data->assigneeid = $userid;
        $data->statusid = $DB->get_field(storage::CATALOG, 'id', ['kind' => 'status', 'code' => $status], MUST_EXIST);
        tickets::triage($id, $data);
    }

    /**
     * Return a private user draft containing one file.
     *
     * @param string $filename Source name
     * @param string $content Test content
     * @return int
     */
    private function draft(string $filename, string $content): int {
        global $USER;
        $id = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $id,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $id;
    }

    /**
     * A forged ticket ID does not reveal another requester's data in detail or list views.
     */
    public function test_requester_scope(): void {
        $generator = $this->getDataGenerator();
        $owner = $generator->create_user();
        $visitor = $generator->create_user();
        $this->setUser($owner);
        $id = tickets::create($this->intake());
        $this->assertSame($id, (int)tickets::get($id)->id);
        $this->setUser($visitor);
        [$counts, $rows] = queries::queue([], 0);
        $this->assertSame(0, (int)$counts->totalcount);
        $this->assertSame([], $rows);
        $this->expectExceptionMessage(access::text('accessdenied'));
        tickets::get($id);
    }

    /**
     * Only assigned technicians and supervisors can see staff notes and their attachments.
     */
    public function test_private_notes_and_attachments(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $agent = $this->technician();
        $other = $this->technician();
        $this->setUser($owner);
        $draft = $this->draft('evidence.txt', 'Sample public evidence.');
        $ticket = tickets::create($this->intake('Synthetic portal ticket', $draft));
        $publicpost = $DB->get_field(storage::POST, 'id', ['ticketid' => $ticket]);
        $this->assertTrue(attachments::visible((int)$publicpost));
        $this->assertCount(1, attachments::links((int)$publicpost));

        $this->setAdminUser();
        $this->assign($ticket, (int)$agent->id);
        $this->setUser($agent);
        $note = tickets::reply($ticket, (object)[
            'body' => 'Technical analysis',
            'internal' => 1,
            'attachments' => $this->draft('private.txt', 'Sample staff note.'),
        ]);
        $this->assertTrue(attachments::visible($note));
        $this->assertSame(0, (int)tickets::get($ticket)->firstresponse);
        $this->setUser($owner);
        $this->assertFalse(attachments::visible($note));
        $this->assertCount(1, queries::messages(tickets::get($ticket), 0)[1]);
        $this->assertNotContains('internalnote', array_column(queries::history(tickets::get($ticket), 0)[1], 'action'));
        $this->setUser($other);
        $this->assertFalse(attachments::visible($note));
        $this->assertFalse(attachments::visible((int)$publicpost));
        $this->setAdminUser();
        $this->assign($ticket, (int)$other->id);
        $this->setUser($agent);
        $this->assertFalse(attachments::visible($note));
    }

    /**
     * Reject file content that does not match an allowed extension.
     */
    public function test_file_extension_and_content_validation(): void {
        $this->assertFalse(attachments::valid($this->draft('fake.png', 'Not a PNG file.')));
        $this->assertFalse(attachments::valid($this->draft('document.php', '<?php echo "example";')));
        $this->assertTrue(attachments::valid($this->draft('valid.txt', 'Synthetic validation fixture.')));
    }

    /**
     * Public staff responses establish the response time and resolved tickets can be rated once.
     */
    public function test_sla_and_satisfaction_lifecycle(): void {
        $owner = $this->getDataGenerator()->create_user();
        $agent = $this->technician();
        $this->setUser($owner);
        $id = tickets::create($this->intake());
        $this->assertGreaterThan(tickets::get($id)->timecreated, tickets::get($id)->firstdue);
        $this->setAdminUser();
        $this->assign($id, (int)$agent->id);
        $this->setUser($agent);
        tickets::reply($id, (object)['body' => 'Reproduced and resolved.', 'internal' => 0]);
        $this->assertGreaterThan(0, tickets::get($id)->firstresponse);
        $this->assign($id, (int)$agent->id, 'resolved');
        $this->assertGreaterThan(0, tickets::get($id)->resolvedat);
        $this->setUser($owner);
        tickets::rate($id, 4);
        $this->assertSame(4, (int)tickets::get($id)->rating);
        $this->expectExceptionMessage(access::text('invalidrating'));
        tickets::rate($id, 5);
    }

    /**
     * Prevent non-supervising assigned staff from giving a ticket to someone else.
     */
    public function test_technician_cannot_reassign(): void {
        $agent = $this->technician();
        $other = $this->technician();
        $id = tickets::create($this->intake());
        $this->assign($id, (int)$agent->id);
        $this->setUser($agent);
        $this->expectExceptionMessage(access::text('accessdenied'));
        $this->assign($id, (int)$other->id);
    }

    /**
     * Prevent attaching a course the requester cannot access.
     */
    public function test_course_scope_at_intake(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $generator->create_user();
        $this->setUser($owner);
        $data = $this->intake();
        $data->courseid = $course->id;
        $this->expectExceptionMessage(access::text('accessdenied'));
        tickets::create($data);
    }

    /**
     * The SLA worker records each breach once and skips revoked recipient access.
     */
    public function test_escalation_and_revoked_notification(): void {
        global $DB;
        $this->setAdminUser();
        set_config('notifications', 1, access::COMPONENT);
        $owner = $this->getDataGenerator()->create_user();
        $agent = $this->technician();
        $this->setUser($owner);
        $id = tickets::create($this->intake());
        $this->setAdminUser();
        $this->assign($id, (int)$agent->id);
        $this->assign($id, 0);
        $DB->update_record(storage::TICKET, ['id' => $id, 'firstdue' => time() - 120, 'resolvedue' => time() - 60]);
        $sink = $this->redirectMessages();
        $task = new task\process();
        $task->execute();
        $task->execute();
        $this->assertGreaterThan(0, tickets::get($id)->responsebreach);
        $this->assertGreaterThan(0, tickets::get($id)->resolvebreach);
        $this->assertSame(1, $DB->count_records(storage::AUDIT, ['ticketid' => $id, 'action' => 'responsebreach']));
        $this->assertSame(1, $DB->count_records(storage::AUDIT, ['ticketid' => $id, 'action' => 'resolvebreach']));
        $notices = $sink->get_messages();
        $this->assertNotEmpty($notices);
        foreach ($notices as $notice) {
            $this->assertNotEquals($agent->id, $notice->useridto);
            $this->assertStringNotContainsString('Synthetic issue', $notice->fullmessage);
        }
        $this->assertSame(0, $DB->count_records(storage::OUTBOX));
        $sink->close();
    }

    /**
     * Record a manual escalation once per cooldown window.
     */
    public function test_manual_escalation_is_audited_once(): void {
        global $DB;
        $id = tickets::create($this->intake());
        tickets::escalate($id);
        $this->assertSame(1, $DB->count_records(storage::AUDIT, ['ticketid' => $id, 'action' => 'manualescalation']));
        $this->expectExceptionMessage(access::text('escalationrecent'));
        tickets::escalate($id);
    }

    /**
     * A configured initial state is used on the next ticket.
     */
    public function test_configurable_initial_state(): void {
        $state = catalog::save('status', 0, (object)[
            'name' => 'Awaiting assignment', 'active' => 1, 'initialstate' => 1, 'terminal' => 0,
        ]);
        $id = tickets::create($this->intake());
        $this->assertSame($state, (int)tickets::get($id)->statusid);
        $this->expectExceptionMessage(access::text('initialrequired'));
        catalog::save('status', $state, (object)[
            'name' => 'Awaiting assignment', 'active' => 0, 'initialstate' => 0, 'terminal' => 0,
        ]);
    }

    /**
     * A technician's reports exclude another technician's and own requester-only tickets.
     */
    public function test_report_scope(): void {
        $agent = $this->technician();
        $other = $this->technician();
        $first = tickets::create($this->intake('Assigned first'));
        $this->assign($first, (int)$agent->id);
        $second = tickets::create($this->intake('Assigned second'));
        $this->assign($second, (int)$other->id);
        $this->setUser($agent);
        tickets::create($this->intake('My unrelated request'));
        $result = queries::report('assignee', []);
        $rows = array_values(iterator_to_array($result));
        $result->close();
        $this->assertCount(1, $rows);
        $this->assertEquals($agent->id, $rows[0]->bucket);
        $this->assertEquals(1, $rows[0]->totalcount);
    }

    /**
     * Requester erasure removes the ticket and all private file areas.
     */
    public function test_privacy_erasure(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $id = tickets::create($this->intake('Remove request', $this->draft('test.txt', 'Synthetic personal information.')));
        $post = (int)$DB->get_field(storage::POST, 'id', ['ticketid' => $id], MUST_EXIST);
        $context = context_system::instance();
        $contextids = privacy\provider::get_contexts_for_userid($owner->id)->get_contextids();
        $this->assertContains((int)$context->id, array_map('intval', $contextids));
        privacy\provider::delete_data_for_user(new approved_contextlist($owner, access::COMPONENT, [$context->id]));
        $this->assertFalse($DB->record_exists(storage::TICKET, ['id' => $id]));
        $this->assertSame([], attachments::links($post));
    }

    /**
     * Retention removes only fully expired closed tickets.
     */
    public function test_retention(): void {
        global $DB;
        $id = tickets::create($this->intake());
        $keep = tickets::create($this->intake('Unresolved ticket'));
        $this->assign($id, 0, 'resolved');
        $old = time() - 400 * DAYSECS;
        $DB->update_record(storage::TICKET, ['id' => $id, 'resolvedat' => $old, 'timemodified' => $old]);
        $DB->set_field(storage::TICKET, 'timemodified', $old, ['id' => $keep]);
        (new task\purge())->execute();
        $this->assertFalse($DB->record_exists(storage::TICKET, ['id' => $id]));
        $this->assertTrue($DB->record_exists(storage::TICKET, ['id' => $keep]));
    }
}
