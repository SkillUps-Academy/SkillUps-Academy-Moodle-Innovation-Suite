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
 * Show the privacy register, native inbox, inventory and operational reports.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_skillupsprivacy\access;
use local_skillupsprivacy\bridge;
use local_skillupsprivacy\dashboard;
use local_skillupsprivacy\store;
use local_skillupsprivacy\workflow;

require_login();
access::require();
$view = optional_param('view', 'cases', PARAM_ALPHA);
$page = max(0, min(1000000, optional_param('page', 0, PARAM_INT)));
$view = in_array($view, ['cases', 'native', 'inventory', 'policy', 'reports', 'audit'], true) ? $view : 'cases';
$url = access::url('index', ['view' => $view]);
admin_externalpage_setup('skillupsprivacy', '', null, $url);
$PAGE->set_cacheable(false);
$system = context_system::instance();
$data = ['tabs' => [], $view => true];
foreach (['cases', 'native', 'inventory', 'policy', 'reports', 'audit'] as $tab) {
    if (
        ($tab === 'audit' && !access::allowed($USER->id, 'decide')) ||
            (in_array($tab, ['inventory', 'policy'], true) && !access::allowed($USER->id, 'inventory')) ||
            ($tab === 'policy' && !has_capability('tool/dataprivacy:managedataregistry', $system))
    ) {
        continue;
    }
    $data['tabs'][] = [
        'label' => access::text('view_' . $tab), 'active' => $tab === $view,
        'url' => access::url('index', ['view' => $tab])->out(false),
    ];
}
$total = 0;
switch ($view) {
    case 'cases':
        $filters = [
            'kind' => optional_param('kind', '', PARAM_ALPHA),
            'state' => optional_param('state', '', PARAM_ALPHA),
            'mine' => optional_param('mine', 0, PARAM_BOOL),
            'dueonly' => optional_param('dueonly', 0, PARAM_BOOL),
            'caseid' => max(0, optional_param('caseid', 0, PARAM_INT)),
        ];
        $filters['kind'] = in_array($filters['kind'], workflow::KINDS, true) ? $filters['kind'] : '';
        $filters['state'] = in_array($filters['state'], workflow::STATES, true) ? $filters['state'] : '';
        $url->params(array_filter($filters));
        [$data['rows'], $total] = dashboard::cases($filters, $page);
        $data += $filters + dashboard::statistics();
        $data['kinds'] = dashboard::options('kind', workflow::KINDS, $filters['kind']);
        $data['states'] = dashboard::options('state', workflow::STATES, $filters['state']);
        $data['registerurl'] = access::allowed($USER->id, 'manage') ? access::url('intake')->out(false) : '';
        store::audit('viewregistry');
        break;
    case 'native':
        [$rows, $total] = bridge::inbox($page);
        $ids = array_column($rows, 'id');
        $linked = $ids ? $DB->get_records_list(store::CASES, 'nativeid', $ids, '', 'nativeid,id') : [];
        $manage = access::allowed($USER->id, 'manage');
        foreach ($rows as &$row) {
            $row['status'] = dashboard::native_status($row['status']);
            $row['kind'] = access::text('kind_' . $row['kind']);
            $row['created'] = userdate($row['created']);
            $id = $row['id'];
            $row['tracked'] = isset($linked[$id]);
            $row['url'] = $row['tracked'] ? access::url('case', ['id' => $linked[$id]->id])->out(false) : '';
            if (!$row['tracked'] && $manage) {
                $row['url'] = access::url('intake', ['nativeid' => $id])->out(false);
            }
        }
        unset($row);
        $data += ['rows' => array_values($rows), 'nativeurl' => bridge::url('datarequests')->out(false)];
        store::audit('viewnative');
        break;
    case 'inventory':
        access::require('inventory');
        $query = \core_text::substr(trim(optional_param('q', '', PARAM_TEXT)), 0, 100);
        [$data['rows'], $total] = dashboard::inventory($query, $page);
        $url->param('q', $query);
        $data['q'] = $query;
        store::audit('viewinventory');
        break;
    case 'policy':
        access::require('inventory');
        require_capability('tool/dataprivacy:managedataregistry', $system);
        $contextid = max(0, optional_param('contextid', 0, PARAM_INT));
        $url->param('contextid', $contextid);
        $context = bridge::context($contextid);
        $data['contextid'] = $contextid;
        $data['policyurl'] = bridge::url('dataregistry')->out(false);
        $data['policycontext'] = $context ? $context->get_context_name(false, true, false) : '';
        $data['purpose'] = $context ? bridge::retention($context) : null;
        store::audit('viewpolicy');
        break;
    case 'reports':
        $data += dashboard::statistics();
        $data['exporturl'] = access::allowed($USER->id, 'export') ? access::url('export')->out(false) : '';
        $data['sesskey'] = sesskey();
        $license = \local_skillupsprivacy\licensing\entitlements::status();
        $data['edition'] = access::text('edition_' . $license['state']);
        $data['licenseend'] = $license['expires'] ? userdate($license['expires']) : '';
        $data['extended'] = in_array('extended_evidence', $license['features'], true);
        store::audit('viewreport');
        break;
    case 'audit':
        access::require('decide');
        $data['rows'] = [];
        $conditions = "caseid = 0 OR operation = 'viewcase'";
        $records = $DB->get_records_select(store::AUDIT, $conditions, [], 'id DESC', '*', $page * dashboard::PAGE, dashboard::PAGE);
        foreach ($records as $row) {
            $data['rows'][] = [
                'actor' => $row->actorid ?: access::text('systemactor'), 'timecreated' => userdate($row->timecreated),
                'operation' => access::text('op_' . $row->operation),
                'caseid' => $row->caseid ?: null,
            ];
        }
        $total = $DB->count_records_select(store::AUDIT, $conditions);
        store::audit('viewaudit');
        break;
}
$data['indexurl'] = access::url()->out(false);
$data['paging'] = $OUTPUT->paging_bar($total, $page, dashboard::PAGE, $url);
echo $OUTPUT->header();
echo $OUTPUT->heading(access::text('pluginname'));
echo $OUTPUT->render_from_template('local_skillupsprivacy/dashboard', $data);
echo $OUTPUT->footer();
