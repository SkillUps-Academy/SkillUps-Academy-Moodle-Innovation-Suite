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
 * List and edit certificate templates and preview PDF designs.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupscertify\access;
use local_skillupscertify\credential\design;
use local_skillupscertify\credential\pdf;
use local_skillupscertify\form\template;
use local_skillupscertify\store;
use local_skillupscertify\ui;

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$editing = $id || optional_param('create', false, PARAM_BOOL);
$course = access::course($courseid);
require_login($course, false);
require_capability(access::CAP . 'manage', context_course::instance($courseid));
$listurl = access::url('templates', ['courseid' => $courseid]);
$url = new moodle_url($listurl, $editing ? ($id ? ['id' => $id] : ['create' => 1]) : []);
ui::page($course, $url, access::text('templates'));
$data = ui::nav($course);
$form = null;
if ($editing) {
    $record = $id ? store::scoped(store::TEMPLATE, $courseid, $id) : null;
    $revision = $record ? store::revision($record) : null;
    $form = new template($url, ['pathway' => $revision && $revision->source === 'pathway']);
    if ($record) {
        $values = json_decode($revision->design, true, 16, JSON_THROW_ON_ERROR);
        $form->set_data($values + [
            'label' => $record->label, 'revision' => $record->revision, 'enabled' => $record->enabled,
            'source' => $revision->source, 'pathid' => $revision->pathid, 'publicmeta' => $revision->publicmeta,
        ]);
        $data['revision'] = access::text('edition', $record->revision);
    }
    if ($form->is_cancelled()) {
        redirect($listurl);
    }
    if ($submitted = $form->get_data()) {
        try {
            if (!empty($submitted->preview)) {
                require_once($CFG->libdir . '/filelib.php');
                $appearance = design::normalize($submitted);
                $sample = [
                    'recipient' => access::text('samplename'), 'course' => $course->fullname, 'issuer' => $appearance['issuer'],
                    'issuedate' => userdate(time()), 'completiondate' => userdate(time()), 'serial' => access::text('sampleonly'),
                ];
                send_file((new pdf())->render(['design' => $appearance, 'fields' => $sample], ''), 'preview.pdf', 0, 0, true);
            }
            $saved = store::save_template($course, $id, $submitted);
            redirect(
                new moodle_url($listurl, ['id' => $saved]),
                access::text('templatesaved'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (\moodle_exception $failure) {
            $data['error'] = $failure->getMessage();
        }
    }
    $data['editing'] = true;
    $data['returnurl'] = $listurl->out(false);
    $data['sample'] = access::text('samplename');
    $data['examplecourse'] = format_string($course->fullname, true, ['escape' => false]);
    $PAGE->requires->js_call_amd(access::C . '/certify', 'preview', ['skillups-certify-preview']);
} else {
    $data['createurl'] = (new moodle_url($url, ['create' => 1]))->out(false);
    foreach ($DB->get_records(store::TEMPLATE, ['courseid' => $courseid], 'label ASC') as $record) {
        $data['rows'][] = [
            'label' => $record->label,
            'edition' => access::text('edition', $record->revision),
            'state' => access::text($record->enabled ? 'active' : 'inactive'),
            'url' => (new moodle_url($url, ['id' => $record->id]))->out(false),
        ];
    }
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::C . '/templates', $data);
$form?->display();
echo $OUTPUT->footer();
