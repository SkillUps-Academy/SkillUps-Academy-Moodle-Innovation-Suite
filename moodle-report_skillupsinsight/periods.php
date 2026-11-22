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
 * Academic period administration.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_skillupsinsight\ui;
use report_skillupsinsight\util;
use report_skillupsinsight\warehouse;

require_login();
$context = context_system::instance();
require_capability(util::CAP . ':manage', $context);
$url = util::url('periods');
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', util::C);
    }
    if ($action === 'createperiod') {
        $name = trim(required_param('name', PARAM_TEXT));
        $begin = required_param('begins', PARAM_RAW_TRIMMED);
        $end = required_param('ends', PARAM_RAW_TRIMMED);
        $parse = static function (string $date): int {
            $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
            if (!$value || $value->format('Y-m-d') !== $date) {
                throw new moodle_exception('invalidperiod', util::C);
            }
            return $value->getTimestamp();
        };
        $from = $parse($begin);
        $to = $parse($end) + DAYSECS;
        if ($name === '' || core_text::strlen($name) > 120 || $from < 0 || $to <= $from || $to - $from > 731 * DAYSECS) {
            throw new moodle_exception('invalidperiod', util::C);
        }
        $lock = util::acquire('period_catalog');
        try {
            if ($DB->count_records(warehouse::PERIOD) >= 200 || $DB->record_exists(warehouse::PERIOD, ['name' => $name])) {
                throw new moodle_exception('invalidperiod', util::C);
            }
            $id = $DB->insert_record(warehouse::PERIOD, ['name' => $name, 'begins' => $from, 'ends' => $to]);
        } finally {
            $lock->release();
        }
    } else {
        $id = required_param('period', PARAM_INT);
        $lock = util::acquire('period_' . $id);
        try {
            $record = $DB->get_record(warehouse::PERIOD, ['id' => $id], '*', MUST_EXIST);
            if ($action === 'freeze' && $record->published && !$record->building) {
                $DB->set_field(warehouse::PERIOD, 'frozen', 1, ['id' => $id]);
            } else if ($action === 'refresh') {
                $DB->update_record(warehouse::PERIOD, ['id' => $id, 'frozen' => 0, 'nextrefresh' => 0]);
            } else {
                throw new moodle_exception('invalidperiod', util::C);
            }
        } finally {
            $lock->release();
        }
    }
    util::audit($context, $action, $id);
    redirect($url, util::str('saved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

ui::header(util::str('periodadmin'), $context, $url);
echo html_writer::tag('p', ui::link('index', [], util::str('dashboard')));
echo html_writer::tag('p', util::str('periodhelp'));
ui::form('periods');
ui::input('name', 'text', ['maxlength' => 120]);
ui::input('begins', 'date');
ui::input('ends', 'date');
ui::button('createperiod');
echo html_writer::end_tag('form');
$table = new html_table();
$table->caption = util::str('periods');
$table->head = [util::str('name'), util::str('begins'), util::str('ends'), util::str('status'), util::str('actions')];
$records = $DB->get_records(warehouse::PERIOD, [], 'begins DESC, id DESC');
foreach ($records as $period) {
    $status = $period->frozen ? 'frozen' : ($period->building ? 'building' : ($period->published ? 'ready' : 'pending'));
    $operation = $period->published && !$period->building && !$period->frozen ? 'freeze' : 'refresh';
    ob_start();
    ui::form('periods', ['period' => $period->id]);
    ui::button($operation, false);
    echo html_writer::end_tag('form');
    $button = ob_get_clean();
    $table->data[] = [
        ui::link('index', ['period' => $period->id], $period->name),
        gmdate('Y-m-d', $period->begins), gmdate('Y-m-d', $period->ends - DAYSECS), util::str($status), $button,
    ];
}
if ($records) {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
}
echo $OUTPUT->footer();
