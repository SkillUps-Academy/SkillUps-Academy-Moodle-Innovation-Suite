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

/**
 * Integrate the activity lifecycle, calendar, files and gradebook with Moodle.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_skillupsattendance\activity;
use mod_skillupsattendance\justification;
use mod_skillupsattendance\session;
use mod_skillupsattendance\store;

/**
 * Describe the native features this activity supports.
 *
 * @param string $feature Feature constant
 * @return bool|string|null
 */
function skillupsattendance_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO, FEATURE_GROUPS, FEATURE_GROUPINGS, FEATURE_SHOW_DESCRIPTION, FEATURE_BACKUP_MOODLE2,
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_ASSESSMENT,
        default => null,
    };
}

/**
 * Create an activity from native form values.
 *
 * @param stdClass $data Form values
 * @param moodleform_mod|null $mform Native form
 * @return int
 */
function skillupsattendance_add_instance($data, $mform = null): int {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    return (int)$DB->insert_record(store::ACTIVITY, $data);
}

/**
 * Update activity settings. Marks and grades are independent explicit operations.
 *
 * @param stdClass $data Form values
 * @param moodleform_mod|null $mform Native form
 * @return bool
 */
function skillupsattendance_update_instance($data, $mform = null): bool {
    global $DB;
    $data->timemodified = time();
    $data->alertdirty = 1;
    $data->id = $data->instance;
    $DB->update_record(store::ACTIVITY, $data);
    return true;
}

/**
 * Delete activity-owned records and files through supported subsystem APIs.
 *
 * @param int $id Activity instance
 * @return bool
 */
function skillupsattendance_delete_instance(int $id): bool {
    global $DB, $CFG;
    if (!$DB->record_exists(store::ACTIVITY, ['id' => $id])) {
        return false;
    }
    require_once($CFG->libdir . '/gradelib.php');
    $activity = activity::by_instance($id);
    return store::atomic($id, static function () use ($DB, $activity, $id): bool {
        $list = $DB->get_records(store::SESSION, ['attendanceid' => $id], '', 'id,attendanceid,eventid');
        get_file_storage()->delete_area_files($activity->context->id, activity::C, 'evidence');
        foreach ($list as $record) {
            session::remove_calendar($record);
        }
        foreach (array_chunk(array_keys($list), 500) as $chunk) {
            foreach ([store::MARK, store::FAIL, store::JUST] as $table) {
                $DB->delete_records_list($table, 'sessionid', $chunk);
            }
        }
        foreach ([store::AUDIT, store::ALERT, store::SESSION] as $table) {
            $DB->delete_records($table, ['attendanceid' => $id]);
        }
        grade_update(
            'mod/skillupsattendance',
            $activity->course->id,
            'mod',
            'skillupsattendance',
            $id,
            0,
            null,
            ['deleted' => 1],
        );
        $DB->delete_records(store::ACTIVITY, ['id' => $id]);
        return true;
    });
}

/**
 * Respect module authorization for an attendance calendar entry.
 *
 * @param calendar_event $event Native entry
 * @param int $userid Viewer
 * @return bool
 */
function mod_skillupsattendance_core_calendar_is_event_visible(calendar_event $event, $userid = 0): bool {
    global $DB, $USER;
    $record = $DB->get_record(store::SESSION, ['eventid' => $event->id, 'attendanceid' => $event->instance]);
    if (!$record || $record->state === 'cancelled') {
        return false;
    }
    $activity = activity::by_instance((int)$event->instance);
    $userid = (int)($userid ?: $USER->id);
    return $activity->can('view', $userid) && session::visible($activity, $record, $userid);
}

/**
 * Serve only an authorized student's or teacher's justification evidence.
 *
 * @param stdClass $course Course record
 * @param stdClass $cm Course module
 * @param context $context Context from File API
 * @param string $filearea File area
 * @param array $args Item ID and name
 * @param bool $forcedownload Download request
 * @param array $options File delivery options
 * @return bool
 */
function skillupsattendance_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    if (
        $context->contextlevel !== CONTEXT_MODULE || $context->instanceid != $cm->id || $filearea !== 'evidence' ||
        count($args) !== 2 || !ctype_digit((string)$args[0])
    ) {
        return false;
    }
    $activity = activity::open($cm->id);
    try {
        [$request, $record] = justification::read($activity, (int)$args[0]);
    } catch (moodle_exception $error) {
        return false;
    }
    if (!justification::may_view($activity, $request, $record)) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, activity::C, 'evidence', $request->id, '/', $args[1]);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, ['cacheability' => 'private'] + $options);
    return true;
}
