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
 * SkillUps AI Copilot index.
 *
 * @package    local_skillupscopilot
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupscopilot\access;
use local_skillupscopilot\ai\registry;
use local_skillupscopilot\chat;
use local_skillupscopilot\event\operation_recorded;
use local_skillupscopilot\form\prompt;
use local_skillupscopilot\sources;

$component = 'local_skillupscopilot';
$courseid = optional_param('courseid', 0, PARAM_INT);
$threadid = optional_param('threadid', 0, PARAM_INT);
$offset = max(0, optional_param('page', 0, PARAM_INT));
$turnpage = max(0, optional_param('turnpage', 0, PARAM_INT));
require_login($courseid ? get_course($courseid) : null, false);
$context = access::require_chat($courseid);
$base = new moodle_url('/local/skillupscopilot/index.php', ['courseid' => $courseid]);
$url = new moodle_url($base, ['threadid' => $threadid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout($courseid ? 'incourse' : 'standard');
$PAGE->set_title(get_string('pluginname', $component));
$PAGE->set_heading($courseid ? $COURSE->fullname : get_string('pluginname', $component));
if ($threadid) {
    chat::thread($threadid, $courseid, $USER->id);
}

$action = optional_param('action', '', PARAM_ALPHA);
if ($threadid && $action === 'delete') {
    if (data_submitted()) {
        require_sesskey();
        chat::delete(['id' => $threadid, 'userid' => $USER->id, 'courseid' => $courseid]);
        operation_recorded::record($context, 'conversationdeleted', $threadid);
        redirect($base, get_string('deleted', $component), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdelete', $component),
        new moodle_url($url, ['action' => 'delete', 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}
if ($threadid && $action === 'rate' && data_submitted()) {
    require_sesskey();
    $rating = required_param('rating', PARAM_INT);
    $turnid = required_param('turnid', PARAM_INT);
    $turn = $DB->get_record('local_skillupscopilot_turn', ['id' => $turnid, 'threadid' => $threadid,
        'status' => 'complete'], '*', MUST_EXIST);
    if (
        !in_array($rating, [-1, 1], true) || !in_array($turn->mode, access::modes($courseid, $USER), true) ||
        sources::titles($turn->manifest, $courseid, $USER) === null
    ) {
        throw new moodle_exception('accessdenied', $component);
    }
    $DB->set_field('local_skillupscopilot_turn', 'rating', $rating, ['id' => $turnid, 'threadid' => $threadid]);
    operation_recorded::record($context, 'rated', $turnid);
    redirect(
        new moodle_url($url, ['turnpage' => $turnpage]),
        get_string('ratingsaved', $component),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$providers = [];
$external = false;
foreach (registry::IDS as $id) {
    if ($config = registry::config($id)) {
        $providers[$id] = get_string($id, $component) . ' · ' . $config['model'];
        $external = $external || $config['external'];
    }
}
$modes = [];
foreach (access::modes($courseid, $USER) as $mode) {
    $modes[$mode] = get_string('mode_' . $mode, $component);
}
$pending = $threadid && $DB->record_exists_select(
    'local_skillupscopilot_turn',
    'threadid = ? AND status IN (?, ?)',
    [$threadid, 'pending', 'running']
);
$form = $providers && !$pending ? new prompt($url, ['modes' => $modes, 'providers' => $providers,
    'requestkey' => bin2hex(random_bytes(16)), 'external' => $external]) : null;
if ($form && ($data = $form->get_data())) {
    try {
        $id = chat::enqueue($courseid, $threadid, $USER, $data);
        redirect(new moodle_url($base, ['threadid' => $id]));
    } catch (moodle_exception $exception) {
        if ($exception->module !== $component) {
            throw $exception;
        }
        \core\notification::error(get_string($exception->errorcode, $component));
    }
}

$perpage = 15;
$scope = ['userid' => $USER->id, 'courseid' => $courseid];
$threads = [];
foreach (
    $DB->get_records(
        'local_skillupscopilot_thread',
        $scope,
        'timemodified DESC, id DESC',
        '*',
        $offset * $perpage,
        $perpage
    ) as $thread
) {
    $threads[] = [
        'url' => (new moodle_url($base, ['threadid' => $thread->id]))->out(false),
        'label' => get_string('conversationlabel', $component, (object)[
            'id' => $thread->id, 'date' => userdate($thread->timecreated),
        ]),
        'active' => (int)$thread->id === $threadid,
    ];
}
$turns = [];
$turnpaging = '';
if ($threadid) {
    $records = $DB->get_records(
        'local_skillupscopilot_turn',
        ['threadid' => $threadid],
        'id DESC',
        '*',
        $turnpage * $perpage,
        $perpage
    );
    foreach (array_reverse($records) as $turn) {
        $titles = isset($modes[$turn->mode]) ? sources::titles($turn->manifest, $courseid, $USER) : null;
        $visible = $turn->status === 'complete' && $titles !== null;
        $turns[] = [
            'id' => $turn->id, 'prompt' => $turn->prompt, 'date' => userdate($turn->timecreated),
            'answer' => $visible ? $turn->answer : null, 'sources' => $visible ? $titles : [],
            'status' => $visible ? '' : get_string(
                'status_' . ($titles === null ? 'restricted' : $turn->status),
                $component
            ),
            'positive' => (int)$turn->rating === 1, 'negative' => (int)$turn->rating === -1,
        ];
    }
    $turnpaging = $OUTPUT->paging_bar(
        $DB->count_records('local_skillupscopilot_turn', ['threadid' => $threadid]),
        $turnpage,
        $perpage,
        $url,
        'turnpage'
    );
}
$manager = has_capability('local/skillupscopilot:manage', context_system::instance());
echo $OUTPUT->header();
echo $OUTPUT->render_from_template($component . '/chat', [
    'base' => $base->out(false), 'url' => $url->out(false), 'sesskey' => sesskey(), 'threads' => $threads, 'turns' => $turns,
    'turnpage' => $turnpage, 'pending' => $pending, 'unavailable' => !$providers, 'form' => $form?->render(),
    'threadpaging' => $OUTPUT->paging_bar(
        $DB->count_records('local_skillupscopilot_thread', $scope),
        $offset,
        $perpage,
        $url
    ),
    'turnpaging' => $turnpaging,
    'deleteurl' => $threadid ? (new moodle_url($url, ['action' => 'delete']))->out(false) : null,
    'sourceurl' => $courseid && has_capability('local/skillupscopilot:managesources', $context) ?
        (new moodle_url('/local/skillupscopilot/sources.php', ['courseid' => $courseid]))->out(false) : null,
    'adminurl' => $manager ? (new moodle_url('/local/skillupscopilot/admin.php'))->out(false) : null,
    'sitechat' => !$courseid,
]);
echo $OUTPUT->footer();
