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

namespace local_skillupsexam;

/**
 * Queue and deliver institutional notices through Moodle messaging.
 *
 * @package    local_skillupsexam
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifications {
    /**
     * Queue a notice once per revision. Caller holds the reservation lock.
     *
     * @param \stdClass $exam Convocation
     * @param string $kind Notice type
     */
    public static function enqueue(\stdClass $exam, string $kind): void {
        global $DB;
        $key = ['examid' => $exam->id, 'revision' => $exam->revision, 'kind' => $kind];
        if (!$DB->record_exists(store::QUEUE, $key)) {
            $DB->insert_record(store::QUEUE, (object)($key + ['status' => 'pending', 'nexttry' => time()]));
        }
    }

    /**
     * Generate reminders and process a bounded number of notifications.
     */
    public static function run(): void {
        global $DB;
        if (!get_config(scope::C, 'notifications')) {
            return;
        }
        $now = time();
        store::write(static function () use ($DB, $now): void {
            $pending = $DB->get_records_sql(
                'SELECT e.* FROM {' . store::EXAM . '} e WHERE e.status = :scheduled AND e.starts > :now AND e.starts <= :until
                  AND NOT EXISTS (SELECT 1 FROM {' . store::QUEUE . '} q WHERE q.examid = e.id AND q.revision = e.revision
                                     AND q.kind = :kind) ORDER BY e.starts, e.id',
                ['scheduled' => 'scheduled', 'now' => $now, 'until' => $now + DAYSECS, 'kind' => 'reminder'],
                0,
                60,
            );
            foreach ($pending as $exam) {
                self::enqueue($exam, 'reminder');
            }
        });
        foreach (
            $DB->get_records_select(
                store::QUEUE,
                'status = :status AND nexttry <= :now',
                ['status' => 'pending', 'now' => $now],
                'nexttry, id',
                '*',
                0,
                12
            ) as $job
        ) {
            self::deliver((int)$job->id);
        }
    }

    /**
     * Deliver a batch and persist a cursor after each accepted message.
     *
     * @param int $jobid Queue record ID
     */
    private static function deliver(int $jobid): void {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory(scope::C)->get_lock('notice:' . $jobid, 0);
        if (!$lock) {
            return;
        }
        try {
            $job = $DB->get_record(store::QUEUE, ['id' => $jobid]);
            if (!$job || $job->status !== 'pending' || $job->nexttry > time()) {
                return;
            }
            $exam = $DB->get_record(store::EXAM, ['id' => $job->examid]);
            if (!$exam || $exam->revision != $job->revision || ($job->kind !== 'cancelled' && $exam->ends <= time())) {
                $DB->update_record(store::QUEUE, (object)['id' => $jobid, 'status' => 'obsolete', 'cursorid' => 0]);
                return;
            }
            $course = get_course($exam->courseid);
            $users = [];
            foreach (roster::users($exam, false, true, (int)$job->cursorid) as $user) {
                $users[$user->id] = $user;
                if (count($users) >= 50) {
                    break;
                }
            }
            foreach (array_unique([$exam->teacherid, $exam->supervisor]) as $id) {
                if ($id > $job->cursorid && scope::course($course, 'view', (int)$id)) {
                    $user = $DB->get_record('user', ['id' => $id, 'deleted' => 0, 'suspended' => 0]);
                    if ($user) {
                        $users[$id] = $user;
                    }
                }
            }
            ksort($users, SORT_NUMERIC);
            $examcontext = \context_course::instance($course->id);
            try {
                foreach (array_slice($users, 0, 50, true) as $user) {
                    if (
                        ($course->visible || has_capability('moodle/course:viewhiddencourses', $examcontext, $user->id)) &&
                        scope::module($course, (int)$exam->cmid, (int)$user->id)
                    ) {
                        $a = (object)['date' => userdate($exam->starts, '', $user->timezone)];
                        $body = get_string_manager()->get_string('notice_' . $job->kind, scope::C, $a, $user->lang);
                        $message = new \core\message\message();
                        $message->component = scope::C;
                        $message->name = 'examnotice';
                        $message->userfrom = \core_user::get_noreply_user();
                        $message->userto = $user;
                        $message->subject = get_string_manager()->get_string('pluginname', scope::C, null, $user->lang);
                        $message->fullmessage = $body;
                        $message->fullmessageformat = FORMAT_PLAIN;
                        $message->smallmessage = $body;
                        $message->notification = 1;
                        $message->contexturl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
                        if (!message_send($message)) {
                            throw new \RuntimeException('Exam notice was not accepted.');
                        }
                    }
                    $DB->update_record(store::QUEUE, (object)['id' => $jobid, 'cursorid' => $user->id, 'attempts' => 0]);
                    $job->attempts = 0;
                }
                if (count($users) < 50) {
                    $DB->update_record(store::QUEUE, (object)['id' => $jobid, 'status' => 'done', 'cursorid' => 0]);
                }
            } catch (\Throwable $error) {
                $tries = $job->attempts + 1;
                $DB->update_record(store::QUEUE, (object)[
                    'id' => $jobid, 'status' => $tries >= 8 ? 'failed' : 'pending', 'attempts' => $tries,
                    'nexttry' => time() + min(DAYSECS, 60 * (2 ** $tries)),
                ]);
                mtrace('SkillUps Exam Manager: delivery failed for queue record ' . $jobid);
            }
        } finally {
            $lock->release();
        }
    }
}
