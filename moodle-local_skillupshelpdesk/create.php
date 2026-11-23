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
 * Open a new support request through Moodle Forms.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\form\conversation;
use local_skillupshelpdesk\tickets;
use local_skillupshelpdesk\ui;

$url = access::url('create');
require_login();
ui::page($url, access::text('openticket'));
access::require(access::can('request'));
$form = new conversation($url, ['new' => true]);
if ($form->is_cancelled()) {
    redirect(access::url());
}
if ($data = $form->get_data()) {
    $id = tickets::create($data);
    redirect(access::url('ticket', ['id' => $id]), access::text('ticketcreated'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::COMPONENT . '/nav', ui::nav());
echo $OUTPUT->heading(access::text('openticket'));
$form->display();
echo $OUTPUT->footer();
