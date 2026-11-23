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
 * Conversation, assignment, history and satisfaction survey.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_skillupshelpdesk\access;
use local_skillupshelpdesk\attachments;
use local_skillupshelpdesk\catalog;
use local_skillupshelpdesk\form\conversation;
use local_skillupshelpdesk\form\triage;
use local_skillupshelpdesk\queries;
use local_skillupshelpdesk\tickets;
use local_skillupshelpdesk\ui;

$id = required_param('id', PARAM_INT);
$url = access::url('ticket', ['id' => $id]);
require_login();
ui::page($url, access::text('ticketnumber', $id));
$ticket = tickets::get($id);
$staff = access::staff($ticket);
$canrate = $ticket->resolvedat && !$ticket->rating && $ticket->requesterid == $USER->id && access::can('request');
if (data_submitted() && optional_param('rate', 0, PARAM_BOOL)) {
    require_sesskey();
    tickets::rate($id, required_param('rating', PARAM_INT));
    redirect($url, access::text('ratingsaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if (data_submitted() && optional_param('escalate', 0, PARAM_BOOL)) {
    require_sesskey();
    tickets::escalate($id);
    redirect($url, access::text('escalationsaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$reply = null;
$triage = null;
$templatechoices = [];
if (!$ticket->resolvedat) {
    $reply = new conversation($url, ['new' => false, 'staff' => $staff]);
    if ($staff) {
        $triage = new triage($url, ['ticket' => $ticket]);
        if ($data = $triage->get_data()) {
            tickets::triage($id, $data);
            redirect($url, access::text('ticketupdated'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $templateid = optional_param('template', 0, PARAM_INT);
        if ($templateid && !data_submitted()) {
            $reply->set_data(['body' => catalog::entry('template', $templateid)->body]);
        }
        $templatechoices = ui::options(catalog::options('template'), $templateid);
    }
    if ($data = $reply->get_data()) {
        tickets::reply($id, $data);
        redirect($url, access::text('commentsaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
$tab = optional_param('tab', 'messages', PARAM_ALPHA);
$page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
$context = ui::nav() + [
    'id' => $id,
    'subject' => $ticket->title,
    'status' => ui::bucket('status', (int)$ticket->statusid),
    'resolved' => (bool)$ticket->resolvedat,
    'canrate' => $canrate,
    'canescalate' => $staff && !$ticket->resolvedat,
    'rated' => $ticket->rating ? access::text('ratingvalue', $ticket->rating) : '',
    'sesskey' => sesskey(),
    'detailurl' => $url->out(false),
    'historyurl' => access::url('ticket', ['id' => $id, 'tab' => 'history'])->out(false),
    'is_history' => $tab === 'history',
    'is_messages' => $tab !== 'history',
    'templates' => $templatechoices,
    'metadata' => [
        ['label' => access::text('kind_category'), 'value' => ui::bucket('category', (int)$ticket->categoryid)],
        ['label' => access::text('kind_campus'), 'value' => ui::bucket('campus', (int)$ticket->campusid)],
        ['label' => access::text('kind_course'), 'value' => ui::bucket('course', (int)$ticket->courseid)],
        ['label' => access::text('kind_priority'), 'value' => ui::bucket('priority', (int)$ticket->priorityid)],
        ['label' => access::text('kind_severity'), 'value' => ui::bucket('severity', (int)$ticket->severityid)],
        ['label' => access::text('kind_assignee'), 'value' => ui::person((int)$ticket->assigneeid)],
        ['label' => access::text('firstdue'), 'value' => ui::date((int)$ticket->firstdue)],
        ['label' => access::text('resolvedue'), 'value' => ui::date((int)$ticket->resolvedue)],
        ['label' => access::text('firstresponse'), 'value' => ui::date((int)$ticket->firstresponse)],
        ['label' => access::text('resolvedat'), 'value' => ui::date((int)$ticket->resolvedat)],
    ],
];
if ($tab === 'history') {
    [$count, $history] = queries::history($ticket, $page);
    $context['events'] = [];
    foreach ($history as $event) {
        $details = json_decode($event->details, true, 12, JSON_THROW_ON_ERROR);
        $changes = [];
        if ($event->action === 'triage') {
            if ($event->formerassignee || $event->newassignee) {
                $details['assigneeid'] = [(int)$event->formerassignee, (int)$event->newassignee];
            }
            foreach ([...tickets::DIMENSIONS, 'assignee'] as $kind) {
                if (isset($details[$kind . 'id'])) {
                    [$from, $to] = $details[$kind . 'id'];
                    $changes[] = [
                        'field' => access::text('kind_' . $kind),
                        'from' => ui::bucket($kind, $from),
                        'to' => ui::bucket($kind, $to),
                    ];
                }
            }
        }
        $context['events'][] = [
            'action' => access::text('action_' . $event->action),
            'actor' => $event->userid ? ui::person((int)$event->userid) : access::text('systemactor'),
            'time' => ui::date((int)$event->timecreated),
            'changes' => $changes,
        ];
    }
    $context['pager'] = $OUTPUT->paging_bar($count, $page, 25, access::url('ticket', ['id' => $id, 'tab' => 'history']));
} else {
    [$count, $posts] = queries::messages($ticket, $page);
    $context['posts'] = [];
    foreach ($posts as $post) {
        $context['posts'][] = [
            'body' => $post->body !== '' ? $post->body : access::text('messageerased'),
            'actor' => $post->userid ? ui::person((int)$post->userid) : access::text('anonymous'),
            'time' => ui::date((int)$post->timecreated),
            'internal' => (bool)$post->internal,
            'attachments' => attachments::links((int)$post->id),
        ];
    }
    $context['pager'] = $OUTPUT->paging_bar($count, $page, 20, $url);
    $context['canloadtemplates'] = (bool)$templatechoices;
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::COMPONENT . '/ticket', $context);
if ($tab !== 'history' && $reply) {
    echo $OUTPUT->heading(access::text('comment'), 3);
    $reply->display();
}
if ($tab !== 'history' && $triage) {
    echo $OUTPUT->heading(access::text('triage'), 3);
    $triage->display();
}
echo $OUTPUT->footer();
