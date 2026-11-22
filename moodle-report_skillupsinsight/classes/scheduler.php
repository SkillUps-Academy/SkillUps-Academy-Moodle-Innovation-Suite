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

namespace report_skillupsinsight;

/**
 * Private recurring reports and threshold/target watches.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scheduler {
    /**
     * Reauthorize the owner on each run. Claimed notices do not replay after ambiguous failures.
     */
    public static function run(int $id): void {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/messagelib.php');
        $watch = $DB->get_record(report::WATCH, ['id' => $id]);
        if (!$watch) {
            return;
        }
        $lock = util::lock('owner_' . $watch->userid);
        if (!$lock) {
            return;
        }
        $original = $USER;
        try {
            $watch = $DB->get_record(report::WATCH, ['id' => $id]);
            $now = time();
            if (!$watch || !$watch->enabled || $watch->nextrun > $now) {
                return;
            }
            $due = $now + (int)$watch->intervaldays * DAYSECS;
            $watch->lastchecked = $now;
            $watch->nextrun = $due;
            $owner = \core_user::get_user($watch->userid);
            if (!$owner || $owner->deleted || $owner->suspended || !$owner->confirmed || isguestuser($owner)) {
                self::disable($watch);
                return;
            }
            \core\session\manager::set_user($owner);
            try {
                $preset = report::preset($watch->presetid, $owner->id);
                $config = report::config(json_decode($preset->config, true, 8, JSON_THROW_ON_ERROR));
                $config['run'] = 0;
                $config['comparerun'] = 0;
                $context = scope::context($config['category'], $config['course'], 'schedule');
                $view = report::build($config, 'schedule');
            } catch (\moodle_exception) {
                self::disable($watch);
                return;
            }
            $value = $view['summary'][$watch->metric] ?? null;
            $status = self::status($watch->direction, $value, (float)$watch->target, $view['coverage'] > 0);
            $notify = ($watch->direction === 'digest' && $status === 'ready') ||
                ($status === 'reached' && $watch->laststatus !== 'reached');
            $watch->laststatus = $status;
            if ($notify) {
                $watch->lastnotice = $now;
            }
            $DB->update_record(report::WATCH, $watch);
            util::audit($context, 'scheduled', (int)($view['current']->id ?? 0));
            if ($notify) {
                self::notice($watch, $preset, $owner);
            }
        } finally {
            \core\session\manager::set_user($original);
            $lock->release();
        }
    }

    /**
     * Evaluate a configured threshold whenever a value is available.
     */
    public static function status(string $direction, ?float $value, float $target, bool $coverage): string {
        if (!$coverage || ($direction !== 'digest' && $value === null)) {
            return 'unavailable';
        }
        if ($direction === 'digest') {
            return 'ready';
        }
        $met = $direction === 'above' ? $value >= $target : $value <= $target;
        return $met ? 'reached' : 'unreached';
    }

    /**
     * Disable a job whose owner can no longer access its inputs.
     */
    private static function disable(\stdClass $watch): void {
        global $DB;
        $watch->enabled = 0;
        $watch->laststatus = 'unauthorized';
        $DB->update_record(report::WATCH, $watch);
    }

    /**
     * Deliver a private link, with metrics only inside the authorized Moodle report.
     */
    private static function notice(\stdClass $watch, \stdClass $preset, \stdClass $owner): void {
        $message = new \core\message\message();
        $message->component = util::C;
        $message->name = 'reportdue';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $owner;
        $message->notification = 1;
        $message->subject = util::str($watch->direction === 'digest' ? 'digestsubject' : 'alertsubject');
        $message->fullmessage = util::str('noticemessage');
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->contexturl = util::url('index', ['preset' => $preset->id])->out(false);
        $message->contexturlname = util::str('openreport');
        if (!message_send($message)) {
            throw new \moodle_exception('notificationfailed', util::C);
        }
    }
}
