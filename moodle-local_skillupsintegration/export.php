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
 * Download credential-free operational evidence under a signed entitlement.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_skillupsintegration\access;
use local_skillupsintegration\licensing\entitlements;
use local_skillupsintegration\problem;
use local_skillupsintegration\store;

require_login();
access::require('view');
access::require('manage');
if (!data_submitted()) {
    throw new problem('permission');
}
require_sesskey();
if (!in_array('extended_evidence', entitlements::status()['features'], true)) {
    throw new problem('permission');
}
store::audit('exported', 0, actorid: (int)$USER->id);
$csv = new csv_export_writer();
$csv->set_filename('skillups-integration-audit');
$csv->add_data(['source_id', 'run_id', 'operator_id', 'action', 'recorded_utc']);
$records = $DB->get_recordset(store::AUDIT, [], 'id ASC');
try {
    foreach ($records as $row) {
        $csv->add_data([$row->sourceid, $row->runid, $row->actorid, $row->action, gmdate(DATE_ATOM, $row->timecreated)]);
    }
} finally {
    $records->close();
}
$csv->download_file();
