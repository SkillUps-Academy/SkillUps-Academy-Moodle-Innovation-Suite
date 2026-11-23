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
 * Optional privacy-preserving course aggregate export.
 *
 * @package    local_skillupsgamification
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_skillupsgamification\access;
use local_skillupsgamification\licensing\entitlements;
use local_skillupsgamification\store;

$courseid = required_param('courseid', PARAM_INT);
[$course, $context] = access::course($courseid);
require_login($course);
require_capability(access::CAP . 'view', $context);
access::manager($courseid);
require_sesskey();
if (!in_array('aggregate_export', entitlements::status()['features'], true)) {
    access::deny();
}
require_once($CFG->libdir . '/csvlib.class.php');
$goal = store::GOAL;
$grant = store::GRANT;
$rows = $DB->get_records_sql(
    "SELECT g.id, g.name, g.kind, COUNT(r.id) AS claimed
       FROM {{$goal}} g LEFT JOIN {{$grant}} r ON r.goalid = g.id AND r.courseid = g.courseid
      WHERE g.courseid = :course GROUP BY g.id, g.name, g.kind ORDER BY g.id",
    ['course' => $courseid]
);
$csv = new csv_export_writer();
$csv->set_filename('skillups-gamification-course-' . $courseid);
$csv->add_data([access::text('name'), access::text('kind'), access::text('totalclaims')]);
foreach ($rows as $row) {
    $title = preg_match('/^[\s]*[=+\-@]|^[\t\r\n]/u', $row->name) ? "'" . $row->name : $row->name;
    $csv->add_data([$title, access::text('goal_' . $row->kind), $row->claimed]);
}
store::audit($courseid, 'export');
$csv->download_file();
