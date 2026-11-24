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
 * Download minimized operational evidence as CSV.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_skillupsprivacy\access;
use local_skillupsprivacy\licensing\entitlements;
use local_skillupsprivacy\store;

require_login();
access::require('export');
if (!data_submitted()) {
    throw new moodle_exception('invaliddecision', access::C);
}
require_sesskey();
$extended = optional_param('extended', 0, PARAM_BOOL);
if ($extended && !in_array('extended_evidence', entitlements::status()['features'], true)) {
    throw new moodle_exception('restricted', access::C);
}
store::audit('export', result: $extended ? 'extended' : 'standard');
$csv = new csv_export_writer();
$csv->set_filename('skillups-privacy-evidence', '.csv');
$columns = ['case', 'type', 'state', 'retention', 'created_utc', 'due_utc', 'native_status'];
if ($extended) {
    array_push($columns, 'revision', 'case_updated_utc', 'last_native_check_utc');
}
$csv->add_data($columns);
$cases = $DB->get_recordset(store::CASES, ['archived' => 0], 'id ASC');
try {
    foreach ($cases as $case) {
        $row = [
            $case->id, $case->kind, $case->state, $case->retention,
            gmdate(DATE_ATOM, $case->timecreated), gmdate(DATE_ATOM, $case->due), $case->nativestatus,
        ];
        if ($extended) {
            $row[] = $case->revision;
            $row[] = gmdate(DATE_ATOM, $case->timemodified);
            $row[] = $case->checkedat ? gmdate(DATE_ATOM, $case->checkedat) : '';
        }
        $csv->add_data($row);
    }
} finally {
    $cases->close();
}
$csv->download_file();
