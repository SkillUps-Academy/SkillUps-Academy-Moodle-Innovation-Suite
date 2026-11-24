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
 * Register or revise a connector profile.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsintegration\access;
use local_skillupsintegration\form\source;
use local_skillupsintegration\problem;
use local_skillupsintegration\profile;

require_login();
access::require('view');
access::require('manage');
$id = optional_param('id', 0, PARAM_INT);
$url = access::url('connector', ['id' => $id]);
admin_externalpage_setup('skillupsintegration', '', null, $url);
$PAGE->set_cacheable(false);
$current = $id ? profile::get($id) : null;
$form = new source($url, ['id' => $id, 'revision' => $current->revision ?? 0]);
if ($current) {
    $options = profile::settings($current);
    unset($current->secretref, $current->options);
    $current->endpoint = $options['endpoint'];
    $current->mapping = $options['mapping'] ? json_encode($options['mapping'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
    $form->set_data($current);
} else {
    $form->set_data((object)['type' => 'inbound', 'users' => 1, 'policy' => 'preserve']);
}
if ($form->is_cancelled()) {
    redirect(access::url());
}
if ($input = $form->get_data()) {
    $input->id = $id;
    if ($current) {
        $input->type = $current->type;
    }
    try {
        profile::save($input);
        redirect(access::url(), access::text('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (problem $failure) {
        \core\notification::error($failure->getMessage());
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(access::text($id ? 'editconnector' : 'addconnector'));
echo $OUTPUT->box(access::text('configintro'));
$form->display();
echo $OUTPUT->footer();
