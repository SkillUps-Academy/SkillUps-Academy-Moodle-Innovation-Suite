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
 * Display the configured rules and decision boundaries.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsquality\catalog;
use report_skillupsquality\scope;
use report_skillupsquality\ui;

require_login();
$courseid = optional_param('course', 0, PARAM_INT);
$course = $courseid ? scope::course($courseid) : null;
if ($course) {
    require_login($course);
} else {
    require_capability('report/skillupsquality:batch', context_system::instance());
}
ui::header(ui::url('catalog', ['course' => $courseid]), scope::text('catalog'), $course);
$policy = catalog::policy();
$rows = [];
foreach (catalog::rules() as $code => $rule) {
    $metadata = $rule->definition();
    $rows[] = [
        s($code),
        s(scope::text($metadata['description'])),
        s(scope::text('kind_' . $metadata['kind'])),
        s(isset($policy['rules'][$code]) ? scope::text('severity_' . $policy['rules'][$code]) : scope::text('disabled')),
        s(scope::text($metadata['condition'])),
        s(scope::text($metadata['recommendation'])),
    ];
}
ui::table('catalog', ['ruleid', 'rule', 'kind', 'severity', 'condition', 'recommendation'], $rows);
echo $OUTPUT->footer();
