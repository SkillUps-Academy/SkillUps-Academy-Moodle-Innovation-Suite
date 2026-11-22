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
 * Secure download for an authorized analytics view.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsinsight\licensing\entitlements;
use report_skillupsinsight\report;
use report_skillupsinsight\scope;
use report_skillupsinsight\util;

require_login();
require_sesskey();
if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', util::C);
}
$config = report::config(json_decode(required_param('config', PARAM_RAW), true, 8, JSON_THROW_ON_ERROR));
$format = required_param('format', PARAM_ALPHA);
if (!in_array($format, ['csv', 'excel', 'pdf'], true)) {
    throw new moodle_exception('invalidrequest', util::C);
}
$context = scope::context($config['category'], $config['course'], 'export');
$PAGE->set_context($context);
$view = report::build($config, 'export');
if (!$view['run']) {
    throw new moodle_exception('awaiting', util::C);
}
$expanded = in_array('aggregate_export', entitlements::status()['features'], true);
$limit = $format === 'pdf' ? max(1, intdiv(1000, count($config['metrics']))) : ($expanded ? 5000 : 1000);
if (count($view['rows']) > $limit) {
    throw new moodle_exception('exportlimit', util::C, '', $limit);
}
$selected = $config['metrics'];
$columns = [util::str('dimension'), util::str('period'), util::str('coverage'), util::str('capturedate')];
if ($view['comparison']) {
    $columns[] = util::str('compare');
    $columns[] = util::str('comparisoncoverage');
}
foreach ($selected as $metric) {
    $columns[] = util::str('metric_' . $metric);
    if ($view['comparison']) {
        $columns[] = util::str('priormetric', util::str('metric_' . $metric));
        $columns[] = util::str('deltametric', util::str('metric_' . $metric));
    }
}
$rows = (static function () use ($view, $selected): Generator {
    foreach ($view['rows'] as $row) {
        $record = [
            report::sheettext($row['label']), report::sheettext($view['current']->name),
            $row['coverage'] . '/' . $row['scope'], report::sheettext(userdate($view['run']->completed)),
        ];
        if ($view['comparison']) {
            $record[] = report::sheettext($view['previous']->name);
            $record[] = $row['priorcoverage'] . '/' . $row['scope'];
        }
        $current = report::values($row['current'], $row['coverage']);
        $prior = report::values($row['prior'], $row['priorcoverage']);
        foreach ($selected as $metric) {
            $value = $current[$metric];
            $record[] = $value === null ? '' : round($value, 4);
            if ($view['comparison']) {
                $previous = $prior[$metric];
                $record[] = $previous === null ? '' : round($previous, 4);
                $record[] = $value !== null && $previous !== null ? round($value - $previous, 4) : '';
            }
        }
        yield $record;
    }
})();
if ($format === 'pdf') {
    $columns = [util::str('dimension'), util::str('period'), util::str('metric'), util::str('metricvalue')];
    if ($view['comparison']) {
        $columns[] = util::str('compare');
        $columns[] = util::str('metricdelta');
    }
    $rows = (static function () use ($view, $selected): Generator {
        foreach ($view['rows'] as $row) {
            $current = report::values($row['current'], $row['coverage']);
            $prior = report::values($row['prior'], $row['priorcoverage']);
            foreach ($selected as $metric) {
                $value = $current[$metric];
                $record = [s($row['label']), s($view['current']->name . ' · ' . $row['coverage'] . '/' . $row['scope']),
                    s(util::str('metric_' . $metric)), s(report::display($value, $metric))];
                if ($view['comparison']) {
                    $previous = $prior[$metric];
                    $delta = $value !== null && $previous !== null ? $value - $previous : null;
                    $record[] = s($view['previous']->name . ' · ' . report::display($previous, $metric));
                    $record[] = s(report::display($delta, $metric, true));
                }
                yield $record;
            }
        }
    })();
}
util::audit($context, 'export_' . $format, $view['current']->id);
\core\dataformat::download_data('skillups-insight-' . $view['current']->id, $format, $columns, $rows);
exit;
