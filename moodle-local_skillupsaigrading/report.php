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
 * Authorized aggregate report download.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The adapter validates login for the assignment and course.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_skillupsaigrading\access;
use local_skillupsaigrading\activity\assign;
use local_skillupsaigrading\licensing\entitlements;
use local_skillupsaigrading\ui;

$id = required_param('id', PARAM_INT);
$activity = new assign($id, 'review', true);
require_sesskey();
if (!data_submitted() || !in_array('aggregate_export', entitlements::status()['features'], true)) {
    access::fail('accessdenied');
}
[$stats] = ui::statistics($activity);
$report = new csv_export_writer();
$report->set_filename('skillups-grading-summary');
$report->add_data([
    ui::str('metrictotal'), ui::str('metricai'), ui::str('metricapproved'), ui::str('metricrejected'),
    ui::str('metricfailed'), ui::str('metriccompared'), ui::str('metricmae'),
]);
$report->add_data([
    $stats->total, $stats->ai, $stats->approved, $stats->rejected, $stats->failed, $stats->compared,
    $stats->compared ? sprintf('%.5F', $stats->error / $stats->compared) : '',
]);
\local_skillupsaigrading\event\aggregate_exported::create(['context' => $activity->context])->trigger();
$report->download_file();
