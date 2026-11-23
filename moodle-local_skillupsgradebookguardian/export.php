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
 * Authorized course diagnosis downloads.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsgradebookguardian\access;
use local_skillupsgradebookguardian\ledger;
use local_skillupsgradebookguardian\licensing\entitlements;
use local_skillupsgradebookguardian\report;

$courseid = required_param('courseid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHA);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
access::require($context, 'export');
require_sesskey();
$entry = ledger::get($courseid, $id, 'diagnosis');
$filename = 'skillups-gradebook-diagnosis-' . $id;
if ($format === 'json') {
    if (!in_array('json_export', entitlements::status()['features'], true)) {
        throw new moodle_exception('licensefeature', access::COMPONENT);
    }
    require_once($CFG->libdir . '/filelib.php');
    ledger::event($courseid, $id, 'export');
    $json = json_encode(['version' => 1, 'course' => $courseid, 'diagnosis' => $id] + $entry->payload, JSON_THROW_ON_ERROR);
    send_file($json, $filename . '.json', 0, 0, true, true, 'application/json');
}
if ($format !== 'csv') {
    throw new moodle_exception('invalidaction', access::COMPONENT);
}

require_once($CFG->libdir . '/csvlib.class.php');
$csv = new csv_export_writer();
$csv->set_filename($filename);
$csv->add_data(array_map([access::class, 'text'], ['finding', 'target', 'severity', 'treatment', 'evidence', 'recommendation']));
foreach ($entry->payload['findings'] as $finding) {
    $row = report::finding($finding, false);
    $csv->add_data([
        $row['title'], $row['reference'], $row['severity'], $row['treatment'], $row['evidence'], $row['recommendation'],
    ]);
}
ledger::event($courseid, $id, 'export');
$csv->download_file();
