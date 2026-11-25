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
 * Confirm an exact migration state change before scheduling work.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_skillupsmigration\form\fields;
use tool_skillupsmigration\jobs;
use tool_skillupsmigration\support;
use tool_skillupsmigration\ui;

require_login();
require_capability(support::CAP . 'manage', context_system::instance());
$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$job = support::get(support::JOB, $id);
support::category((int)$job->categoryid, (int)$USER->id);
if (!in_array($action, ['simulate', 'execute', 'cancel', 'retry'], true) || ($job->actor != $USER->id && !is_siteadmin())) {
    support::fail('unavailable');
}
ui::page('action', compact('id', 'action'));
$form = new fields(ui::url('action', compact('id', 'action')), ['fields' => [
    'wave' => ['hidden', PARAM_INT, false], 'confirmoperation' => ['advcheckbox', null, false],
]]);
$form->set_data(['wave' => $job->wave]);
$back = ui::url('job', compact('id'));
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    if (empty($data->confirmoperation)) {
        support::fail('confirmrequired');
    }
    jobs::change($id, $action, (int)$data->wave, (int)$USER->id);
    redirect($back);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(ui::t($action));
echo ui::link($back, ui::t('back'));
echo html_writer::tag('p', ui::t($action . 'help'));
$form->display();
echo $OUTPUT->footer();
