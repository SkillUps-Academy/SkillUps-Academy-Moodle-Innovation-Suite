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

namespace local_skillupsflow\action;

use local_skillupsflow\security;

/**
 * Fixed Moodle API operations. No node may name an executable function or class.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class moodle implements \local_skillupsflow\contract\action {
    /**
     * Dispatch a validated node.
     */
    public function execute(array $node, \stdClass $run, \stdClass $actor): void {
        [$userid, $courseid] = security::targets($node, $run);
        match ($node['type']) {
            'suspend' => $this->suspend($userid, $node['value'], $actor),
            'visibility' => $this->visibility($courseid, $node['value'], $actor),
            'enrol' => $this->enrol($userid, $courseid, $node['roleid'], $actor),
            'notify' => $this->notify($userid, $node['body'], $actor),
            default => throw new \coding_exception('Unregistered workflow action'),
        };
    }

    /**
     * Set account suspension through the supported user API.
     */
    private function suspend(int $id, int $value, \stdClass $actor): void {
        global $CFG;
        require_capability('moodle/user:update', \context_system::instance(), $actor);
        $target = security::user($id);
        if ($target->id == $actor->id || is_siteadmin($target)) {
            throw new \moodle_exception('protectedtarget', 'local_skillupsflow');
        }
        if ((int)$target->suspended === $value) {
            return;
        }
        $data = (object)['id' => $id, 'suspended' => $value];
        if (method_exists(\core_user::class, 'update_user')) {
            \core_user::update_user($data, false);
        } else {
            require_once($CFG->dirroot . '/user/lib.php');
            user_update_user($data, false);
        }
        if ($value) {
            \core\session\manager::kill_user_sessions($id);
        }
    }

    /**
     * Set course visibility with the Course API.
     */
    private function visibility(int $id, int $value, \stdClass $actor): void {
        global $CFG;
        security::course($id, $actor, 'moodle/course:visibility');
        $course = get_course($id);
        if ((int)$course->visible !== $value) {
            require_once($CFG->dirroot . '/course/lib.php');
            update_course((object)['id' => $id, 'visible' => $value]);
        }
    }

    /**
     * Enrol through a single enabled manual instance with an assignable role.
     */
    private function enrol(int $userid, int $courseid, int $roleid, \stdClass $actor): void {
        global $DB;
        security::user($userid);
        $context = security::course($courseid, $actor, 'enrol/manual:enrol');
        require_capability('moodle/role:assign', $context, $actor);
        if (!array_key_exists($roleid, get_assignable_roles($context, ROLENAME_ALIAS, false, $actor))) {
            throw new \moodle_exception('protectedtarget', 'local_skillupsflow');
        }
        $instances = array_filter(enrol_get_instances($courseid, true), static fn($instance) => $instance->enrol === 'manual');
        if (count($instances) !== 1 || !($plugin = enrol_get_plugin('manual'))) {
            throw new \moodle_exception('targetunavailable', 'local_skillupsflow');
        }
        $instance = reset($instances);
        if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            $plugin->enrol_user($instance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
        }
    }

    /**
     * Use Moodle notifications, with one recorded delivery attempt.
     */
    private function notify(int $userid, string $body, \stdClass $actor): void {
        global $CFG;
        require_once($CFG->libdir . '/messagelib.php');
        $target = security::user($userid);
        require_capability('moodle/site:sendmessage', \context_system::instance(), $actor);
        $subject = get_string('messagetitle', 'local_skillupsflow', null, true);
        $message = new \core\message\message();
        $message->component = 'local_skillupsflow';
        $message->name = 'workflow';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $target;
        $message->subject = $subject->out($target->lang ?: 'en');
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = \core_text::substr($body, 0, 250);
        $message->notification = 1;
        if (!message_send($message)) {
            throw new \moodle_exception('notificationfailed', 'local_skillupsflow');
        }
    }
}
