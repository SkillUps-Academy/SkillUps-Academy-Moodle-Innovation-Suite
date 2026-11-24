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
 * Open or decide a justification in its own course and group scope.
 *
 * @package    mod_skillupsattendance
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- activity::open enforces native course and module login.
require_once(__DIR__ . '/../../config.php');

use mod_skillupsattendance\activity;
use mod_skillupsattendance\justification;

$activity = activity::open(required_param('id', PARAM_INT));
$id = required_param('request', PARAM_INT);
[$request, $record] = justification::read($activity, $id);
if (!justification::may_view($activity, $request, $record)) {
    activity::fail('unavailable');
}
$url = $activity->url('review', ['request' => $id]);
$activity->page($url, activity::text('reviewrequest'));
if (data_submitted()) {
    require_sesskey();
    try {
        justification::decide($activity, $id, required_param('decision', PARAM_ALPHA), required_param('response', PARAM_TEXT));
        redirect($url, activity::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $error) {
        if ($error->module !== activity::C) {
            throw $error;
        }
        \core\notification::error($error->getMessage());
    }
}
$files = [];
$items = get_file_storage()->get_area_files($activity->context->id, activity::C, 'evidence', $id, 'filename ASC', false);
foreach ($items as $file) {
    $fileurl = moodle_url::make_pluginfile_url(
        $activity->context->id,
        activity::C,
        'evidence',
        $id,
        '/',
        $file->get_filename(),
        true,
    );
    $files[] = ['name' => $file->get_filename(), 'url' => $fileurl->out(false)];
}
echo $OUTPUT->header();
echo $OUTPUT->heading(activity::text('reviewrequest'));
echo $OUTPUT->render_from_template('mod_skillupsattendance/review', [
    'back' => $activity->url('session', ['session' => $record->id])->out(false), 'url' => $url->out(false), 'sesskey' => sesskey(),
    'name' => $record->name, 'userid' => $request->userid, 'when' => userdate($request->timecreated),
    'reason' => $request->reason, 'statement' => $request->statement, 'state' => activity::text('just_' . $request->state),
    'files' => $files, 'response' => $request->response, 'date' => $request->timereviewed ? userdate($request->timereviewed) : '',
    'reviewer' => $request->reviewer, 'review' => $request->state === 'pending' && $record->state !== 'cancelled'
        && $activity->can('review') && $activity->staff_group($record->groupid),
]);
echo $OUTPUT->footer();
