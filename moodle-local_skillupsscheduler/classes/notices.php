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

namespace local_skillupsscheduler;

/**
 * Deliver bounded batches of persistent Moodle notifications.
 *
 * @package    local_skillupsscheduler
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notices {
    /**
     * Process a bounded group of ready notification jobs.
     */
    public static function run(): void {
        global $DB;
        $ready = $DB->get_records_select(store::QUEUE, 'nexttry <= :now AND attempts < 8', ['now' => time()], 'id', 'id', 0, 12);
        foreach (array_keys($ready) as $id) {
            $lock = \core\lock\lock_config::get_lock_factory(scope::C)->get_lock("notice:$id", 0);
            if (!$lock) {
                continue;
            }
            try {
                self::deliver((int)$id);
            } finally {
                $lock->release();
            }
        }
    }

    /**
     * Send one chunk of a queued notification.
     *
     * @param int $id Record ID
     */
    private static function deliver(int $id): void {
        global $DB;
        $job = $DB->get_record(store::QUEUE, ['id' => $id]);
        if (!$job || $job->nexttry > time() || $job->attempts >= 8) {
            return;
        }
        $slot = $DB->get_record(store::SLOT, ['id' => $job->slotid]);
        if (!$slot) {
            $DB->delete_records(store::QUEUE, ['id' => $id]);
            return;
        }
        $site = store::get(store::SITE, (int)$slot->siteid);
        $course = $DB->get_record('course', ['id' => $slot->courseid]);
        if (
            !$course || !scope::course_in_site($site, $course) ||
            ($slot->groupid && !$DB->record_exists('groups', ['id' => $slot->groupid, 'courseid' => $course->id]))
        ) {
            $DB->delete_records(store::QUEUE, ['id' => $id]);
            return;
        }
        [$enrolled, $params] = get_enrolled_sql(
            \context_course::instance($course->id),
            scope::CAP . 'view',
            (int)$slot->groupid,
            true,
        );
        $recipients = $DB->get_fieldset_sql(
            "SELECT u.id FROM {user} u
              WHERE u.id > :cursorid AND u.deleted = 0 AND u.suspended = 0
                AND (u.id IN ($enrolled) OR u.id IN (:oldteacher, :newteacher))
           ORDER BY u.id",
            $params + ['cursorid' => $job->cursorid, 'oldteacher' => $job->oldteacher, 'newteacher' => $job->newteacher],
            0,
            50,
        );
        foreach ($recipients as $userid) {
            $teacher = $userid == $job->oldteacher || $userid == $job->newteacher;
            try {
                $context = \context_course::instance($course->id);
                $teacherallowed = $teacher && scope::teacher((int)$course->id, (int)$userid) &&
                    ($course->visible || has_capability('moodle/course:viewhiddencourses', $context, $userid));
                if ($teacherallowed || scope::participant($course, (int)$slot->groupid, (int)$userid)) {
                    self::send((int)$userid, $job->operation, $slot);
                }
                $DB->update_record(store::QUEUE, (object)['id' => $id, 'cursorid' => $userid, 'attempts' => 0]);
                $job->attempts = 0;
            } catch (\Throwable $error) {
                $attempts = $job->attempts + 1;
                $DB->update_record(store::QUEUE, (object)[
                    'id' => $id, 'attempts' => $attempts, 'nexttry' => time() + (60 * (2 ** $attempts)),
                ]);
                mtrace("Scheduler notification job $id failed at attempt $attempts.");
                return;
            }
        }
        if (count($recipients) < 50) {
            $DB->delete_records(store::QUEUE, ['id' => $id]);
        }
    }

    /**
     * Send a Moodle notification to one recipient.
     *
     * @param int $userid Moodle user ID
     * @param string $operation Change or transaction operation
     * @param \stdClass $slot Session data
     */
    private static function send(int $userid, string $operation, \stdClass $slot): void {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $lang = $user->lang ?: current_language();
        $strings = get_string_manager();
        $title = $strings->get_string('noticetitle', scope::C, null, $lang);
        $body = $strings->get_string('notice_' . $operation, scope::C, null, $lang);
        $body .= "\n\n" . format_string($slot->title, true) . ' · ' .
            userdate($slot->starts, get_string('strftimedatetime', 'langconfig'), $user->timezone);
        $message = new \core\message\message();
        $message->component = scope::C;
        $message->name = 'schedulechange';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $title;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . s($body) . '</p>';
        $message->smallmessage = $title;
        $message->notification = 1;
        $message->contexturl = ($operation === 'cancelled' ? scope::url() : scope::url('session', ['id' => $slot->id]))->out(false);
        $message->contexturlname = $title;
        if (!message_send($message)) {
            throw new \RuntimeException('Moodle messaging did not accept the notification.');
        }
    }
}
