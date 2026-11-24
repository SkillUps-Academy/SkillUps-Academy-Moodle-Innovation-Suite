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

namespace mod_skillupsattendance;

use stdClass;

/**
 * Manage student justification requests and their private File API evidence.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class justification {
    /**
     * Return the configured institutional justifications.
     *
     * @param activity $activity Module
     * @return array
     */
    public static function reasons(activity $activity): array {
        $lines = preg_split('/\R/u', trim($activity->record->reasons ?? ''));
        $lines = array_map('trim', $lines ?: []);
        $lines = array_values(array_unique(array_filter($lines, static fn(string $line): bool => $line !== '')));
        return $lines ?: [activity::text('reasonhealth'), activity::text('reasoninstitution'), activity::text('reasonother')];
    }

    /**
     * Return evidence constraints for drafts and the stored area.
     *
     * @param activity $activity Module
     * @return array
     */
    public static function file_options(activity $activity): array {
        global $CFG;
        return [
            'subdirs' => 0, 'maxfiles' => 3,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes, $activity->course->maxbytes, 10485760),
            'accepted_types' => ['.pdf', '.jpg', '.jpeg', '.png'],
        ];
    }

    /**
     * Add a request for the current student, storing selected draft files privately.
     *
     * @param activity $activity Activity
     * @param int $sid Session
     * @param string $reason Configured label
     * @param string $statement Student note
     * @param int $draft Form draft file item
     * @return int
     */
    public static function submit(activity $activity, int $sid, string $reason, string $statement, int $draft): int {
        global $USER, $DB, $CFG;
        $activity->require('recordown');
        if (!in_array($reason, self::reasons($activity), true) || \core_text::strlen($statement) > 1000) {
            activity::fail('badreason');
        }
        require_once($CFG->libdir . '/filelib.php');
        $save = static function () use ($DB, $USER, $activity, $sid, $reason, $statement, $draft): int {
            $record = store::session($activity, $sid);
            $criteria = ['sessionid' => $sid, 'userid' => $USER->id];
            $mark = $DB->get_record(store::MARK, $criteria);
            $pending = $DB->record_exists(store::JUST, $criteria + ['state' => 'pending']);
            if (
                !$activity->eligible($USER->id, $record->groupid) || $record->state === 'cancelled' || time() < $record->ends ||
                ($mark && !in_array($mark->state, ['late', 'absent', 'pending'], true)) ||
                $pending || $DB->count_records(store::JUST, $criteria) >= 5
            ) {
                activity::fail('justunavailable');
            }
            if ($draft) {
                self::check_files($activity, $draft, (int)$USER->id);
            }
            $id = (int)$DB->insert_record(store::JUST, $criteria + [
                'reason' => $reason, 'statement' => clean_param($statement, PARAM_TEXT),
                'state' => 'pending', 'timecreated' => time(),
            ]);
            if ($draft) {
                $options = self::file_options($activity);
                file_save_draft_area_files($draft, $activity->context->id, activity::C, 'evidence', $id, $options);
            }
            store::audit($activity, 'requested', $sid, (int)$USER->id);
            return $id;
        };
        return store::atomic($activity->record->id, $save);
    }

    /**
     * Decide a pending justification. Approved requests set the attendance outcome to excused.
     *
     * @param activity $activity Module
     * @param int $id Request
     * @param string $state Approved or rejected
     * @param string $response Teacher explanation
     */
    public static function decide(activity $activity, int $id, string $state, string $response): void {
        global $DB, $USER;
        $activity->require('review');
        $response = trim(clean_param($response, PARAM_TEXT));
        if (!in_array($state, ['approved', 'rejected'], true) || $response === '' || \core_text::strlen($response) > 1000) {
            activity::fail('badreason');
        }
        store::atomic($activity->record->id, static function () use ($DB, $USER, $activity, $id, $state, $response): void {
            [$request, $session] = self::read($activity, $id);
            if (
                $request->state !== 'pending' || $session->state === 'cancelled' ||
                !$activity->staff_group($session->groupid) || !$activity->eligible($request->userid, $session->groupid)
            ) {
                activity::fail('justunavailable');
            }
            $DB->update_record(store::JUST, [
                'id' => $id, 'state' => $state, 'response' => $response, 'reviewer' => $USER->id, 'timereviewed' => time(),
            ]);
            if ($state === 'approved') {
                $mark = $DB->get_record(store::MARK, ['sessionid' => $session->id, 'userid' => $request->userid]);
                marks::save_row($activity, $session->id, $request->userid, 'excused', 'justified', $mark ?: null);
            }
            store::audit($activity, 'decided', $session->id, $request->userid, 'pending', $state);
        });
    }

    /**
     * Resolve a request within the same module.
     *
     * @param activity $activity Module
     * @param int $id Request
     * @return array
     */
    public static function read(activity $activity, int $id): array {
        global $DB;
        $request = $DB->get_record(store::JUST, ['id' => $id]);
        if (!$request) {
            activity::fail('unavailable');
        }
        return [$request, store::session($activity, $request->sessionid)];
    }

    /**
     * Validate the viewer for a single request and its files.
     *
     * @param activity $activity Module
     * @param stdClass $request Request
     * @param stdClass $session Session
     * @return bool
     */
    public static function may_view(activity $activity, stdClass $request, stdClass $session): bool {
        global $USER;
        return ($request->userid == $USER->id && $activity->eligible((int)$USER->id, $session->groupid)) ||
            ($activity->can('review') && $activity->staff_group($session->groupid));
    }

    /**
     * Validate bounded file count, bytes and detectable file contents.
     *
     * @param activity $activity Activity
     * @param int $draft Draft selection
     * @param int $userid Owner
     */
    private static function check_files(activity $activity, int $draft, int $userid): void {
        $context = \context_user::instance($userid);
        $files = get_file_storage()->get_area_files($context->id, 'user', 'draft', $draft, 'id ASC', false);
        $options = self::file_options($activity);
        if (count($files) > $options['maxfiles']) {
            activity::fail('badfile');
        }
        foreach ($files as $file) {
            $handle = $file->get_content_file_handle();
            try {
                $prefix = fread($handle, 12);
            } finally {
                fclose($handle);
            }
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $prefix = $prefix === false ? '' : $prefix;
            $detected = match ($ext) {
                'pdf' => str_starts_with($prefix, '%PDF-'),
                'png' => str_starts_with($prefix, "\x89PNG\r\n\x1a\n"),
                'jpg', 'jpeg' => str_starts_with($prefix, "\xff\xd8\xff"),
                default => false,
            };
            if (!$detected || $file->get_filesize() > $options['maxbytes'] || $file->get_filepath() !== '/') {
                activity::fail('badfile');
            }
        }
    }
}
