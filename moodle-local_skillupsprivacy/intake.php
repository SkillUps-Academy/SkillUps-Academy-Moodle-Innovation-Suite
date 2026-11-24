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
 * Register an internal privacy case.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsprivacy\access;
use local_skillupsprivacy\bridge;
use local_skillupsprivacy\form\intake;
use local_skillupsprivacy\store;
use local_skillupsprivacy\workflow;

require_login();
access::require('manage');
$nativeid = max(0, optional_param('nativeid', 0, PARAM_INT));
$url = access::url('intake', ['nativeid' => $nativeid]);
admin_externalpage_setup('skillupsprivacy', '', null, $url);
$PAGE->set_cacheable(false);
$native = $nativeid ? bridge::read($nativeid) : null;
if ($nativeid && (!$native || !$native['kind'])) {
    throw new moodle_exception('nativeinvalid', access::C);
}
$form = new intake($url, ['nativeid' => $nativeid]);
$form->set_data((object)[
    'userid' => $native['userid'] ?? '', 'kind' => $native['kind'] ?? 'access',
    'ownerid' => $USER->id, 'contextid' => 0, 'due' => workflow::default_due(),
]);
if ($form->is_cancelled()) {
    redirect(access::url());
}
if ($data = $form->get_data()) {
    $data->nativeid = $nativeid;
    try {
        $id = workflow::create($data);
    } catch (moodle_exception $error) {
        if ($error->module !== access::C) {
            throw $error;
        }
        \core\notification::error($error->getMessage());
    }
    if (isset($id)) {
        redirect(access::url('case', ['id' => $id]), access::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
store::audit('viewintake');
echo $OUTPUT->header();
echo $OUTPUT->heading(access::text('register'));
echo $OUTPUT->box(access::text('intakehelp'));
$form->display();
echo $OUTPUT->footer();
