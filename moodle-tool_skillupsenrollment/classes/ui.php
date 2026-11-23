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

namespace tool_skillupsenrollment;

use html_writer;
use moodle_url;

/**
 * Render bounded, authorized operation and report pages.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui {
    /**
     * Build a native admin URL.
     */
    public static function url(string $page, array $parameters = []): moodle_url {
        global $CFG;
        return new moodle_url('/' . $CFG->admin . '/tool/skillupsenrollment/' . $page . '.php', $parameters);
    }

    /**
     * Prepare the system administration page before processing Moodle forms.
     */
    public static function setup(string $page, string $title, array $params = []): void {
        global $PAGE, $USER;
        require_capability('tool/skillupsenrollment:operate', \context_system::instance());
        if (!support::can_operate((int)$USER->id)) {
            throw new \moodle_exception('denied', support::C);
        }
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(self::url($page, $params));
        $PAGE->set_pagelayout('admin');
        $PAGE->set_title(support::text($title));
        $PAGE->set_heading(support::text($title));
    }

    /**
     * Print native navigation and the page header.
     */
    public static function header(): void {
        global $OUTPUT;
        echo $OUTPUT->header();
        $links = [];
        foreach (['index' => 'pluginname', 'import' => 'import', 'create' => 'single', 'report' => 'reports'] as $page => $key) {
            $links[] = html_writer::link(self::url($page), s(support::text($key)));
        }
        echo html_writer::tag('nav', implode(' ', $links), [
            'class' => 'd-flex gap-3 flex-wrap mb-4', 'aria-label' => support::text('pluginname'),
        ]);
    }

    /**
     * Render standard scrollable tables.
     */
    public static function table(string $caption, array $columns, array $rows): void {
        $table = new \html_table();
        $table->caption = support::text($caption);
        $table->head = array_map([support::class, 'text'], $columns);
        $table->data = $rows;
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /**
     * Format state badges with localized text.
     */
    public static function state(string $value): string {
        $color = match ($value) {
            'done', 'applied', 'noop' => 'text-bg-success',
            'failed', 'invalid' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
        return html_writer::span(s(support::text('state_' . $value)), 'badge ' . $color);
    }

    /**
     * Hide historic personal evidence if the operator has since lost the course scope.
     */
    public static function row_visible(\stdClass $row): bool {
        global $USER, $DB, $CFG;
        if (!$row->courseid) {
            return true;
        }
        $data = json_decode($row->payload, true, 10, JSON_THROW_ON_ERROR);
        if (!empty($data['method'])) {
            $context = \context_course::instance((int)$row->courseid, IGNORE_MISSING);
            if (
                !$context || (!has_capability('enrol/' . $data['method'] . ':manage', $context)
                && !($data['method'] === 'manual' && $row->action === 'enrol'
                    && has_capability('enrol/manual:enrol', $context)))
            ) {
                return false;
            }
        }
        if (support::can_report((int)$row->courseid, (int)$USER->id, (int)$row->userid)) {
            return true;
        }
        $before = $data['before'] ?? [];
        require_once($CFG->dirroot . '/group/lib.php');
        return $row->state === 'pending' && $row->action === 'enrol' && $row->groupid && empty($before['enrolled'])
            && support::can_report((int)$row->courseid, (int)$USER->id)
            && $DB->record_exists('groups', ['id' => $row->groupid, 'courseid' => $row->courseid])
            && groups_is_member($row->groupid, $USER->id);
    }

    /**
     * Describe relevant prior state and the requested outcome.
     */
    public static function simulation(\stdClass $row): string {
        $data = json_decode($row->payload, true, 10, JSON_THROW_ON_ERROR);
        if (!isset($data['before'])) {
            return '—';
        }
        $before = $data['before'];
        $status = !$before['enrolled'] ? 'missingenrolment'
            : ($before['status'] === ENROL_USER_ACTIVE ? 'activeenrolment' : 'suspendedenrolment');
        $description = [
            support::text('methodname', $data['method'] . ' #' . $row->instanceid),
            support::text($status),
            support::text(mutation::satisfied($row->action, $before) ? 'forecastnoop' : 'forecastchange'),
        ];
        if ($row->roleid) {
            $description[] = support::text('rolename', $row->roleid);
        }
        if ($row->groupid) {
            $description[] = support::text('groupname', $row->groupid);
        }
        return implode('', array_map(static fn($item) => html_writer::tag('div', s($item)), $description));
    }

    /**
     * Export only rows visible in the current context.
     */
    public static function download(string $where, array $params, string $format, string $name): void {
        global $DB;
        $heading = array_map([support::class, 'text'], [
            'job', 'line', 'courseid', 'userid', 'action', 'method', 'instanceid', 'role', 'groupid', 'state', 'attempts', 'reason',
        ]);
        $records = $DB->get_recordset_select(jobs::ROW, $where, $params, 'id DESC', '*', 0, 5000);
        $rows = (static function () use ($records): \Generator {
            try {
                foreach ($records as $row) {
                    if (!self::row_visible($row)) {
                        continue;
                    }
                    $data = json_decode($row->payload, true, 10, JSON_THROW_ON_ERROR);
                    yield [
                        (int)$row->jobid, (int)$row->position, $row->courseid ?: '', $row->userid ?: '',
                        support::text('action_' . $row->action), $data['method'] ?? '', $row->instanceid ?: '',
                        $row->roleid ?: '', $row->groupid ?: '', support::text('state_' . $row->state),
                        (int)$row->attempts, $row->errorcode ? support::text($row->errorcode) : '',
                    ];
                }
            } finally {
                $records->close();
            }
        })();
        \core\dataformat::download_data($name, $format, $heading, $rows);
    }

    /**
     * Select output formats available in the active license.
     */
    public static function excel_enabled(): bool {
        return in_array('aggregate_export', licensing\entitlements::status()['features'], true);
    }
}
