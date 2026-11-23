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
 * Course dashboard, diagnosis history and repair approvals.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsgradebookguardian\access;
use local_skillupsgradebookguardian\ledger;
use local_skillupsgradebookguardian\repair;
use local_skillupsgradebookguardian\report;
use local_skillupsgradebookguardian\rules;
use local_skillupsgradebookguardian\licensing\entitlements;

$courseid = required_param('courseid', PARAM_INT);
$entryid = optional_param('id', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
access::require($context);
$base = access::url('index', ['courseid' => $courseid]);
$canexport = has_capability(access::CAP . 'export', $context);
$candiagnose = has_capability(access::CAP . 'diagnose', $context);
$canpreview = $candiagnose && access::repairsenabled();
$canapprove = access::canrepair($context);

if (data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $reference = match ($action) {
        'diagnose' => ledger::diagnose($course),
        'preview' => repair::preview($course, $entryid, required_param('itemid', PARAM_INT)),
        'apply', 'revert' => repair::apply($course, $entryid, $action === 'revert'),
        default => throw new moodle_exception('invalidaction', access::COMPONENT),
    };
    redirect(
        access::url('index', ['courseid' => $courseid, 'id' => $reference]),
        access::text('saved_' . $action),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_url($entryid ? access::url('index', ['courseid' => $courseid, 'id' => $entryid]) : $base);
$PAGE->set_context($context);
$PAGE->set_title(access::text('pluginname'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(access::text('pluginname'), $base);

$data = [
    'courseid' => $courseid,
    'entryid' => $entryid,
    'url' => $base->out(false),
    'homeurl' => $base->out(false),
    'sesskey' => sesskey(),
    'candiagnose' => $candiagnose,
];
if (!$entryid) {
    $kind = optional_param('kind', '', PARAM_ALPHA);
    $kinds = ['diagnosis', 'preview', 'apply', 'revert'];
    $kind = in_array($kind, $kinds, true) ? $kind : '';
    $page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
    [$total, $entries] = ledger::history($courseid, $kind, $page);
    $data['history'] = true;
    $data['kinds'] = array_map(
        static fn(string $key): array => ['key' => $key, 'label' => access::text('kind_' . $key), 'selected' => $key === $kind],
        $kinds
    );
    $data['entries'] = array_values(array_map(static fn($entry): array => report::historyrow($entry, $courseid), $entries));
    $data['pager'] = $OUTPUT->paging_bar($total, $page, 20, access::url('index', ['courseid' => $courseid, 'kind' => $kind]));
} else {
    $entry = ledger::get($courseid, $entryid);
    $data['entry'] = report::historyrow($entry, $courseid);
    if ($entry->kind === 'diagnosis') {
        $tab = optional_param('tab', '', PARAM_ALPHA);
        $recordfilter = max(0, optional_param('gradeitem', 0, PARAM_INT));
        $relationships = $entry->payload['relationships'] ?? [];
        $data['findingsurl'] = access::url('index', ['courseid' => $courseid, 'id' => $entryid])->out(false);
        $data['relationshipsurl'] = access::url('index', [
            'courseid' => $courseid, 'id' => $entryid, 'tab' => 'relations',
        ])->out(false);
        $data['onrelationships'] = $tab === 'relations';
        $data['onfindings'] = !$data['onrelationships'];
        $findings = $entry->payload['findings'];
        $severity = optional_param('severity', '', PARAM_ALPHA);
        $rule = optional_param('rule', '', PARAM_ALPHA);
        $baselineid = max(0, optional_param('baseline', 0, PARAM_INT));
        if ($baselineid) {
            $baseline = ledger::get($courseid, $baselineid, 'diagnosis');
            $findings = report::compare($findings, $baseline->payload['findings']);
        }
        $data['diagnosis'] = true;
        $data['counts'] = $entry->payload['counts'];
        $data['baseline'] = $baselineid ?: '';
        $data['comparing'] = (bool)$baselineid;
        $data['severities'] = array_map(
            static fn(string $key): array => [
                'key' => $key, 'label' => access::text('severity_' . $key), 'selected' => $severity === $key,
            ],
            ['error', 'warning', 'info']
        );
        $data['rules'] = array_map(
            static fn(string $key): array => ['key' => $key, 'label' => access::text('rule_' . $key), 'selected' => $rule === $key],
            array_keys(rules::CATALOGUE)
        );
        $findings = array_values(array_filter($findings, static function (array $finding) use ($severity, $rule): bool {
            return (!$severity || rules::CATALOGUE[$finding['rule']][0] === $severity) && (!$rule || $finding['rule'] === $rule);
        }));
        $page = max(0, min(100000, optional_param('page', 0, PARAM_INT)));
        $filters = ['courseid' => $courseid, 'id' => $entryid, 'severity' => $severity, 'rule' => $rule, 'baseline' => $baselineid];
        $data['findings'] = array_map(
            static fn($finding): array => report::finding($finding, $canpreview),
            array_slice($findings, $page * 25, 25)
        );
        $data['pager'] = $OUTPUT->paging_bar(count($findings), $page, 25, access::url('index', $filters));
        if ($data['onrelationships']) {
            if ($recordfilter) {
                $relationships = array_values(array_filter(
                    $relationships,
                    static fn(array $row): bool => $row['item'] === $recordfilter
                ));
            }
            $data['gradeitem'] = $recordfilter ?: '';
            $data['relationships'] = array_map(static function (array $row): array {
                $row['dependencies'] = implode(', ', $row['dependencies']);
                return $row;
            }, array_slice($relationships, $page * 25, 25));
            $data['pager'] = $OUTPUT->paging_bar(count($relationships), $page, 25, access::url('index', [
                'courseid' => $courseid, 'id' => $entryid, 'tab' => 'relations', 'gradeitem' => $recordfilter,
            ]));
        }
        if ($canexport) {
            $data['csv'] = access::url('export', ['courseid' => $courseid, 'id' => $entryid, 'sesskey' => sesskey()])->out(false);
            if (in_array('json_export', entitlements::status()['features'], true)) {
                $data['json'] = access::url('export', [
                    'courseid' => $courseid, 'id' => $entryid, 'format' => 'json', 'sesskey' => sesskey(),
                ])->out(false);
            }
        }
    } else {
        $data['decision'] = true;
        $before = $entry->payload['before'];
        $after = $entry->payload['after'];
        $data['item'] = access::text('target_item', $before['item']);
        $data['activity'] = access::text('target_activity', $before['activity']);
        $data['category'] = access::text('target_category', $before['category']);
        $data['before'] = report::visibility($before['hidden']);
        $data['after'] = report::visibility($after['hidden']);
        $data['relatedurl'] = access::url('index', ['courseid' => $courseid, 'id' => $entry->parentid])->out(false);
        if ($entry->kind === 'preview') {
            $applied = ledger::decision($courseid, $entryid, 'apply');
            $reverted = ledger::decision($courseid, $entryid, 'revert');
            $data['approvalurl'] = $applied ? access::url('index', ['courseid' => $courseid, 'id' => $applied])->out(false) : '';
            $data['recoveryurl'] = $reverted ? access::url('index', ['courseid' => $courseid, 'id' => $reverted])->out(false) : '';
            $data['canapply'] = $canapprove && !$applied && (int)$entry->timecreated > time() - DAYSECS;
            $data['expired'] = !$applied && (int)$entry->timecreated <= time() - DAYSECS;
            $data['canundo'] = $canapprove && $applied && !$reverted;
        }
    }
}
if (has_capability('moodle/grade:manage', $context)) {
    $data['gradebook'] = (new moodle_url('/grade/edit/tree/index.php', ['id' => $courseid]))->out(false);
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template(access::COMPONENT . '/dashboard', $data);
echo $OUTPUT->footer();
